<?php

namespace Tests\Concerns;

use App\Models\Campus;
use App\Models\Filiere;
use App\Models\Formation;
use App\Models\Module;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RbacSeeder;

/** Construit un petit établissement de test : 1 formation, 1 promotion, 1 formateur, 2 étudiants, 1 séance aujourd'hui. */
trait ScenarioScolaire
{
    protected User $admin;
    protected User $formateur;
    protected User $autreFormateur;
    protected User $etudiant;
    protected User $autreEtudiant;
    protected User $etudiantHorsPromo;
    protected Promotion $promotion;
    protected Module $module;
    protected Seance $seance;

    protected function construireScenario(): void
    {
        $this->seed(RbacSeeder::class);

        $filiere = Filiere::create(['code' => 'informatique', 'libelle' => 'Informatique']);
        $campus = Campus::create(['nom' => 'Le Port']);
        $formation = Formation::create([
            'filiere_id' => $filiere->id, 'slug' => 'cda-bac4', 'intitule' => 'Concepteur Développeur d\'Applications',
            'accroche' => 'Accroche', 'niveau_rncp' => 6, 'code_rncp' => 'RNCP37873', 'eligible_cpf' => true,
        ]);

        $this->admin = User::factory()->role(Role::ADMIN)->avecMfa()->create();
        $this->formateur = User::factory()->role(Role::FORMATEUR)->avecMfa()->create();
        $this->autreFormateur = User::factory()->role(Role::FORMATEUR)->avecMfa()->create();
        $this->etudiant = User::factory()->role(Role::ETUDIANT)->create();
        $this->autreEtudiant = User::factory()->role(Role::ETUDIANT)->create();
        $this->etudiantHorsPromo = User::factory()->role(Role::ETUDIANT)->create();

        $this->promotion = Promotion::create(['formation_id' => $formation->id, 'campus_id' => $campus->id, 'libelle' => 'CDA 2026', 'date_debut' => '2026-09-01', 'date_fin' => '2028-07-01']);
        $this->promotion->etudiants()->attach([$this->etudiant->id, $this->autreEtudiant->id], ['date_inscription' => '2026-09-01']);

        $this->module = Module::create(['formation_id' => $formation->id, 'code' => 'WEB', 'intitule' => 'Back-end']);
        $this->seance = $this->creerSeance(today());
    }

    protected function creerSeance($date, string $creneau = 'matin', ?User $formateur = null): Seance
    {
        return Seance::create([
            'promotion_id' => $this->promotion->id, 'module_id' => $this->module->id,
            'formateur_id' => ($formateur ?? $this->formateur)->id, 'date' => $date->toDateString(),
            'creneau' => $creneau, 'heure_debut' => '08:30', 'heure_fin' => '12:00',
        ]);
    }

    protected function signaturePng(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }
}
