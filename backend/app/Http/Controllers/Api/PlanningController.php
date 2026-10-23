<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Devoir;
use App\Models\Role;
use App\Models\Seance;
use App\Models\User;
use App\Services\Journal;
use App\Services\Notificateur;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Emploi du temps : séances de l'étudiant (via ses promotions) ou du formateur (séances animées). */
class PlanningController extends Controller
{
    /** Période maximale consultable en une requête (vue mois + semaines débordantes, export). */
    private const JOURS_MAX = 124;

    public function index(Request $request)
    {
        [$du, $au] = $this->periode($request, now()->startOfWeek(), now()->addWeeks(2)->endOfWeek());
        $user = $request->user();
        $seances = $this->seances($user, $du, $au);

        $mesEmargements = $user->hasRole(Role::ETUDIANT)
            ? $user->emargements()->whereIn('seance_id', $seances->pluck('id'))->get()->keyBy('seance_id')
            : collect();

        return response()->json(['du' => $du, 'au' => $au, 'seances' => $seances->map(fn (Seance $s) => [
            'id' => $s->id,
            'date' => $s->date->toDateString(),
            'creneau' => $s->creneau,
            'heure_debut' => substr($s->heure_debut, 0, 5),
            'heure_fin' => substr($s->heure_fin, 0, 5),
            'lieu' => $s->lieu,
            'module' => $s->module->intitule,
            'module_id' => $s->module_id,
            'promotion_id' => $s->promotion_id,
            'formateur' => $s->formateur->nomComplet(),
            'promotion' => $s->promotion->libelle,
            'cahier' => [
                'contenu_vu' => $s->contenu_vu,
                'travail_a_faire' => $s->travail_a_faire,
                'travail_pour' => $s->travail_pour?->toDateString(),
                'modifiable' => $user->hasRole(Role::ADMIN) || $s->formateur_id === $user->id,
            ],
            'emargement' => [
                'ouvert' => $s->fenetreOuverte(),
                'cloture' => $s->cloturee_at !== null,
                'mon_statut' => $mesEmargements->get($s->id)?->statut,
            ],
        ])->values()]);
    }

    /**
     * Export iCalendar (RFC 5545) : le planning s'importe dans Outlook, Google Agenda ou Apple Calendrier.
     * Par défaut : de la semaine précédente à deux mois.
     */
    public function ics(Request $request)
    {
        [$du, $au] = $this->periode($request, now()->subWeek()->startOfWeek(), now()->addMonths(2)->endOfWeek());
        $user = $request->user();
        $tz = config('app.timezone');
        $echapper = fn (?string $t) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n'], (string) $t);
        $horodatage = fn (Seance $s, string $heure) => Carbon::parse($s->date->toDateString().' '.$heure, $tz)->format('Ymd\THis');

        $lignes = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Expernet Campus//Planning ENT//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$echapper('Expernet Campus - '.$user->nomComplet()), 'X-WR-TIMEZONE:'.$tz,
            // Fuseau sans changement d'heure (La Réunion, Mayotte) : un seul décalage suffit.
            'BEGIN:VTIMEZONE', 'TZID:'.$tz, 'BEGIN:STANDARD', 'DTSTART:19700101T000000',
            'TZOFFSETFROM:'.now($tz)->format('O'), 'TZOFFSETTO:'.now($tz)->format('O'), 'END:STANDARD', 'END:VTIMEZONE',
        ];
        foreach ($this->seances($user, $du, $au) as $s) {
            $lignes = array_merge($lignes, [
                'BEGIN:VEVENT',
                'UID:seance-'.$s->id.'@expernet-campus.re',
                'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
                'DTSTART;TZID='.$tz.':'.$horodatage($s, $s->heure_debut),
                'DTEND;TZID='.$tz.':'.$horodatage($s, $s->heure_fin),
                'SUMMARY:'.$echapper($s->module->intitule),
                'LOCATION:'.$echapper($s->lieu),
                'DESCRIPTION:'.$echapper('Promotion '.$s->promotion->libelle.' · Formateur : '.$s->formateur->nomComplet()),
                'END:VEVENT',
            ]);
        }
        $lignes[] = 'END:VCALENDAR';

        return response(implode("\r\n", array_map([$this, 'plier'], $lignes))."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="planning-expernet.ics"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** RFC 5545 : une ligne de plus de 75 octets se poursuit sur la suivante, précédée d'une espace. */
    private function plier(string $ligne): string
    {
        $morceaux = [];
        while (strlen($ligne) > 75) {
            $morceau = mb_strcut($ligne, 0, $morceaux ? 74 : 75, 'UTF-8');
            $morceaux[] = $morceau;
            $ligne = substr($ligne, strlen($morceau));
        }
        $morceaux[] = $ligne;

        return implode("\r\n ", $morceaux);
    }

    /** Cahier de textes d'une séance : contenu vu et travail à faire (formateur de la séance ou administration). */
    public function cahier(Request $request, Seance $seance)
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::ADMIN) || $seance->formateur_id === $user->id, 403, 'Vous n\'animez pas cette séance.');
        $donnees = $request->validate([
            'contenu_vu' => ['nullable', 'string', 'max:5000'],
            'travail_a_faire' => ['nullable', 'string', 'max:2000'],
            'travail_pour' => ['nullable', 'date', 'required_with:travail_a_faire'],
        ], ['travail_pour.required_with' => 'Indiquez pour quand le travail est à faire.']);

        $avant = $seance->travail_a_faire;
        $nettoyer = fn ($t) => isset($t) && trim($t) !== '' ? trim(strip_tags($t)) : null;
        $seance->forceFill([
            'contenu_vu' => $nettoyer($donnees['contenu_vu'] ?? null),
            'travail_a_faire' => $nettoyer($donnees['travail_a_faire'] ?? null),
            'travail_pour' => ! empty($donnees['travail_a_faire']) ? ($donnees['travail_pour'] ?? null) : null,
            'cahier_maj_at' => now(),
        ])->save();
        $seance->loadMissing(['promotion', 'module:id,intitule']);

        Journal::ecrire('cahier.modification', $seance, "Cahier de textes mis à jour : {$seance->module?->intitule} du ".$seance->date->format('d/m/Y'));
        if ($seance->travail_a_faire && $seance->travail_a_faire !== $avant) {
            Notificateur::envoyer($seance->promotion->etudiants()->get(), 'travail', 'Travail à faire : '.$seance->module?->intitule,
                'Pour le '.$seance->travail_pour?->locale('fr')->isoFormat('dddd D MMMM').' : '.mb_strimwidth($seance->travail_a_faire, 0, 140, '…'), '/ent/etudiant');
        }

        return response()->json([
            'contenu_vu' => $seance->contenu_vu, 'travail_a_faire' => $seance->travail_a_faire,
            'travail_pour' => $seance->travail_pour?->toDateString(), 'modifiable' => true,
        ]);
    }

    /** Ce que l'apprenant a à faire : travail du cahier de textes et devoirs non échus, par date. */
    public function aFaire(Request $request)
    {
        $user = $request->user();
        $promotions = $user->promotions()->pluck('promotions.id');

        $travaux = Seance::with('module:id,intitule')->whereIn('promotion_id', $promotions)
            ->whereNotNull('travail_a_faire')->whereDate('travail_pour', '>=', today())
            ->orderBy('travail_pour')->limit(20)->get()
            ->map(fn (Seance $s) => [
                'type' => 'travail', 'id' => $s->id, 'pour' => $s->travail_pour->toDateString(), 'module' => $s->module?->intitule,
                'module_id' => $s->module_id, 'texte' => $s->travail_a_faire, 'donne_le' => $s->date->toDateString(),
            ]);

        $devoirs = Devoir::with('module:id,intitule')->whereIn('promotion_id', $promotions)->where('echeance', '>=', now())
            ->withExists(['rendus as rendu' => fn ($q) => $q->where('auteur_id', $user->id)])
            ->orderBy('echeance')->limit(20)->get()
            ->map(fn (Devoir $d) => [
                'type' => 'devoir', 'id' => $d->id, 'pour' => $d->echeance->toIso8601String(), 'module' => $d->module?->intitule,
                'module_id' => $d->module_id, 'texte' => $d->titre, 'rendu' => (bool) $d->rendu,
            ]);

        return response()->json($travaux->concat($devoirs)->sortBy('pour')->values());
    }

    /** @return array{0: string, 1: string} */
    private function periode(Request $request, Carbon $duDefaut, Carbon $auDefaut): array
    {
        $periode = $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
        ]);
        $du = $periode['du'] ?? $duDefaut->toDateString();
        $au = $periode['au'] ?? $auDefaut->toDateString();
        abort_if(Carbon::parse($du)->diffInDays(Carbon::parse($au)) > self::JOURS_MAX, 422, 'Période trop longue (4 mois maximum).');

        return [$du, $au];
    }

    /** Séances visibles par l'utilisateur sur la période (cloisonnement par rôle). */
    private function seances(User $user, string $du, string $au): Collection
    {
        return Seance::query()
            ->with(['module:id,intitule,code', 'formateur:id,prenom,nom', 'promotion:id,libelle'])
            ->whereDate('date', '>=', $du)->whereDate('date', '<=', $au)
            ->when($user->hasRole(Role::ETUDIANT), fn ($q) => $q->whereIn('promotion_id', $user->promotions()->pluck('promotions.id')))
            ->when($user->hasRole(Role::FORMATEUR), fn ($q) => $q->where('formateur_id', $user->id))
            ->orderBy('date')->orderBy('heure_debut')
            ->get();
    }
}
