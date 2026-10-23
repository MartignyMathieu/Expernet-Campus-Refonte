<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['chemin'];

    protected $appends = ['apercu'];

    protected $casts = ['corrige_at' => 'datetime', 'note' => 'float'];

    /**
     * Espaces documentaires d'une promotion : libellé, description et rôles autorisés à y déposer.
     * « rendus » est l'espace des apprenants (devoirs, justificatifs) : chacun n'y voit que ses propres dépôts.
     */
    public const ESPACES = [
        'cours' => ['libelle' => 'Cours & supports', 'description' => 'Supports de cours, diaporamas, ressources des séances.', 'icone' => 'lecture_article', 'depot' => ['formateur', 'admin']],
        'pedagogie' => ['libelle' => 'Pédagogie', 'description' => 'Programme, référentiel du titre, livret d\'apprentissage, progression.', 'icone' => 'diplome', 'depot' => ['formateur', 'admin']],
        'exercices' => ['libelle' => 'TP & exercices', 'description' => 'Énoncés de travaux pratiques, exercices et corrigés.', 'icone' => 'code', 'depot' => ['formateur', 'admin']],
        'examens' => ['libelle' => 'Examens', 'description' => 'Convocations, consignes d\'épreuve, annales et grilles d\'évaluation.', 'icone' => 'examens', 'depot' => ['formateur', 'admin']],
        'administratif' => ['libelle' => 'Administratif', 'description' => 'Règlement intérieur, calendrier de formation, conventions.', 'icone' => 'entreprise', 'depot' => ['admin']],
        'stage' => ['libelle' => 'Stage & alternance', 'description' => 'Offres, conventions de stage, documents entreprise.', 'icone' => 'alternance', 'depot' => ['formateur', 'admin']],
        'rendus' => ['libelle' => 'Mes rendus', 'description' => 'Devoirs et justificatifs déposés par les apprenants.', 'icone' => 'envoyer', 'depot' => ['etudiant']],
    ];

    public const RENDUS = 'rendus';

    public static function peutDeposer(string $role, string $espace): bool
    {
        return in_array($role, self::ESPACES[$espace]['depot'] ?? [], true);
    }

    /** Formats affichables dans le portail sans téléchargement. */
    public const APERCU_EN_LIGNE = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public const APERCU_TEXTE = ['text/plain', 'text/markdown', 'text/x-markdown', 'text/csv'];

    /** pdf | image | texte | null (aperçu indisponible : documents Office, archives…) */
    public function getApercuAttribute(): ?string
    {
        return match (true) {
            $this->mime === 'application/pdf' => 'pdf',
            in_array($this->mime, self::APERCU_EN_LIGNE, true) => 'image',
            in_array($this->mime, self::APERCU_TEXTE, true) => 'texte',
            default => null,
        };
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function devoir(): BelongsTo
    {
        return $this->belongsTo(Devoir::class);
    }

    public function correcteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par');
    }
}
