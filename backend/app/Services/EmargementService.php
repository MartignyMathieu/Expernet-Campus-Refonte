<?php

namespace App\Services;

use App\Exceptions\EmargementException;
use App\Models\Emargement;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Cœur métier de l'émargement numérique.
 *
 * Cycle : le formateur OUVRE la séance (code à 6 chiffres, fenêtre limitée)
 *       → chaque étudiant SIGNE (code + tracé) → le formateur CLÔTURE
 *       → les non-signataires sont marqués absents.
 *
 * Preuve : empreinte SHA-256 = hash(seance|etudiant|horodatage|hash(image signature)).
 * Il s'agit d'une signature électronique « simple » au sens du règlement eIDAS.
 */
class EmargementService
{
    public const TAILLE_MAX_SIGNATURE = 200_000; // octets

    /** @return string le code en clair, affiché une seule fois au formateur */
    public function ouvrir(Seance $seance, User $formateur): string
    {
        if ($seance->formateur_id !== $formateur->id) {
            throw new EmargementException('Vous n\'animez pas cette séance.', 'NOT_SEANCE_OWNER');
        }
        if ($seance->cloturee_at !== null) {
            throw new EmargementException('La séance est déjà clôturée.', 'SEANCE_CLOSED');
        }
        if (! $seance->date->isSameDay(now())) {
            throw new EmargementException('L\'émargement ne peut être ouvert que le jour de la séance.', 'NOT_SEANCE_DAY');
        }

        $code = (string) random_int(100000, 999999);

        $seance->forceFill([
            'code_emargement_hash' => Hash::make($code),
            'emargement_ouvert_at' => now(),
            'emargement_expire_at' => now()->addMinutes(config('expernet.emargement.duree_minutes')),
        ])->save();

        return $code;
    }

    public function signer(Seance $seance, User $etudiant, string $code, string $signatureDataUrl, ?string $ip, ?string $userAgent): Emargement
    {
        if (! $seance->promotion->etudiants()->whereKey($etudiant->id)->exists()) {
            throw new EmargementException('Vous n\'êtes pas inscrit dans la promotion de cette séance.', 'NOT_ENROLLED');
        }
        if (! $seance->fenetreOuverte()) {
            throw new EmargementException('L\'émargement n\'est pas ouvert pour cette séance.', 'WINDOW_CLOSED');
        }
        if (! Hash::check($code, (string) $seance->code_emargement_hash)) {
            throw new EmargementException('Code d\'émargement incorrect.', 'BAD_CODE');
        }

        $png = $this->decoderSignature($signatureDataUrl);

        return DB::transaction(function () use ($seance, $etudiant, $png, $ip, $userAgent) {
            // Verrou applicatif + contrainte UNIQUE(seance_id, user_id) en base : double protection.
            $existe = Emargement::where('seance_id', $seance->id)->where('user_id', $etudiant->id)->lockForUpdate()->exists();
            if ($existe) {
                throw new EmargementException('Vous avez déjà signé cette séance.', 'ALREADY_SIGNED');
            }

            $horodatage = now()->toImmutable();
            $chemin = sprintf('signatures/%d/%d_%s.png', $seance->id, $etudiant->id, $horodatage->format('YmdHis'));
            Storage::disk('local')->put($chemin, $png);

            return Emargement::create([
                'seance_id' => $seance->id,
                'user_id' => $etudiant->id,
                'statut' => Emargement::PRESENT,
                'signe_at' => $horodatage,
                'signature_path' => $chemin,
                'empreinte' => self::calculerEmpreinte($seance->id, $etudiant->id, $horodatage->toIso8601String(), hash('sha256', $png)),
                'ip' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ]);
        });
    }

    /** Clôture : crée une ligne « absent » pour chaque inscrit n'ayant pas signé. */
    public function cloturer(Seance $seance, User $formateur): int
    {
        if ($seance->formateur_id !== $formateur->id) {
            throw new EmargementException('Vous n\'animez pas cette séance.', 'NOT_SEANCE_OWNER');
        }
        if ($seance->cloturee_at !== null) {
            throw new EmargementException('La séance est déjà clôturée.', 'SEANCE_CLOSED');
        }

        return DB::transaction(function () use ($seance) {
            $signataires = $seance->emargements()->pluck('user_id');
            $absents = $seance->promotion->etudiants()->whereNotIn('users.id', $signataires)->pluck('users.id');

            foreach ($absents as $id) {
                Emargement::create(['seance_id' => $seance->id, 'user_id' => $id, 'statut' => Emargement::ABSENT]);
            }

            $seance->forceFill(['cloturee_at' => now(), 'code_emargement_hash' => null])->save();

            return $absents->count();
        });
    }

    /** Recalcule l'empreinte à partir de la signature stockée : prouve l'intégrité. */
    public function verifier(Emargement $emargement): bool
    {
        if ($emargement->statut !== Emargement::PRESENT || ! $emargement->signature_path
            || ! Storage::disk('local')->exists($emargement->signature_path)) {
            return false;
        }

        $attendu = self::calculerEmpreinte(
            $emargement->seance_id,
            $emargement->user_id,
            $emargement->signe_at->toIso8601String(),
            hash('sha256', Storage::disk('local')->get($emargement->signature_path)),
        );

        return hash_equals($emargement->empreinte, $attendu);
    }

    public static function calculerEmpreinte(int $seanceId, int $userId, string $horodatageIso, string $hashImage): string
    {
        return hash('sha256', implode('|', [$seanceId, $userId, $horodatageIso, $hashImage]));
    }

    private function decoderSignature(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new EmargementException('Format de signature invalide (PNG attendu).', 'BAD_SIGNATURE');
        }

        $binaire = base64_decode(substr($dataUrl, 22), true);

        if ($binaire === false || ! str_starts_with($binaire, "\x89PNG\r\n\x1a\n")) {
            throw new EmargementException('Signature illisible.', 'BAD_SIGNATURE');
        }
        if (strlen($binaire) > self::TAILLE_MAX_SIGNATURE) {
            throw new EmargementException('Signature trop volumineuse.', 'BAD_SIGNATURE');
        }

        return $binaire;
    }
}
