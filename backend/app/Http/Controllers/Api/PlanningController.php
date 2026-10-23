<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Seance;
use App\Models\User;
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
