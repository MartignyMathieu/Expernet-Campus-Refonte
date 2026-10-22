<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'intitule' => $this->intitule,
            'accroche' => $this->accroche,
            'filiere' => $this->whenLoaded('filiere', fn () => ['code' => $this->filiere->code, 'libelle' => $this->filiere->libelle]),
            'niveau_rncp' => $this->niveau_rncp,
            'niveau_libelle' => $this->libelleNiveau(),
            'code_rncp' => $this->code_rncp,
            'certificateur' => $this->certificateur,
            'eligible_cpf' => $this->eligible_cpf,
            'rythme' => $this->rythme,
            'prochaine_rentree' => $this->prochaine_rentree?->toDateString(),
            'campus' => $this->whenLoaded('campus', fn () => $this->campus->pluck('nom')),
            $this->mergeWhen($this->relationLoaded('elements'), fn () => [
                'objectifs' => $this->elements->where('type', 'objectif')->pluck('contenu')->values(),
                'prerequis' => $this->elements->where('type', 'prerequis')->pluck('contenu')->values(),
                'modalites' => $this->elements->where('type', 'modalite')->pluck('contenu')->values(),
                'debouches' => $this->elements->where('type', 'debouche')->pluck('contenu')->values(),
            ]),
        ];
    }
}
