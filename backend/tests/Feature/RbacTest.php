<?php

namespace Tests\Feature;

use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->construireScenario();
    }

    public function test_la_matrice_des_permissions_est_appliquee(): void
    {
        foreach (RbacSeeder::MATRICE as $role => $permissions) {
            $user = ['admin' => $this->admin, 'formateur' => $this->formateur, 'etudiant' => $this->etudiant][$role];
            foreach (array_keys(RbacSeeder::PERMISSIONS) as $permission) {
                $this->assertSame(in_array($permission, $permissions, true), $user->hasPermission($permission), "$role / $permission");
            }
        }
    }

    public function test_seul_l_admin_accede_a_l_administration(): void
    {
        $this->actingAs($this->etudiant)->getJson('/api/admin/utilisateurs')->assertForbidden();
        $this->actingAs($this->formateur)->getJson('/api/admin/utilisateurs')->assertForbidden();
        $this->actingAs($this->admin)->getJson('/api/admin/utilisateurs')->assertOk();
    }

    public function test_un_etudiant_ne_voit_pas_le_tableau_d_assiduite(): void
    {
        $this->actingAs($this->etudiant)->getJson("/api/promotions/{$this->promotion->id}/assiduite")->assertForbidden();
    }

    public function test_un_formateur_ne_voit_que_les_promotions_ou_il_enseigne(): void
    {
        $this->actingAs($this->formateur)->getJson("/api/promotions/{$this->promotion->id}/assiduite")->assertOk();
        $this->actingAs($this->autreFormateur)->getJson("/api/promotions/{$this->promotion->id}/assiduite")->assertForbidden();
    }

    public function test_le_planning_est_cloisonne_par_role(): void
    {
        $this->actingAs($this->etudiant)->getJson('/api/planning')->assertOk()->assertJsonCount(1, 'seances');
        $this->actingAs($this->etudiantHorsPromo)->getJson('/api/planning')->assertOk()->assertJsonCount(0, 'seances');
        $this->actingAs($this->autreFormateur)->getJson('/api/planning')->assertOk()->assertJsonCount(0, 'seances');
    }

    public function test_l_admin_cree_un_compte_avec_un_mot_de_passe_robuste(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/utilisateurs', [
            'prenom' => 'Nina', 'nom' => 'Test', 'email' => 'nina@exemple.test', 'role' => 'formateur', 'password' => 'faible',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->actingAs($this->admin)->postJson('/api/admin/utilisateurs', [
            'prenom' => 'Nina', 'nom' => 'Test', 'email' => 'nina@exemple.test', 'role' => 'formateur', 'password' => 'Robuste-Mot2Passe',
        ])->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'nina@exemple.test']);
    }

    public function test_l_admin_ne_peut_pas_modifier_son_propre_role(): void
    {
        $this->actingAs($this->admin)->patchJson("/api/admin/utilisateurs/{$this->admin->id}", ['role' => 'etudiant'])->assertStatus(422);
    }

    public function test_un_compte_desactive_perd_l_acces_immediatement(): void
    {
        $this->actingAs($this->admin)->patchJson("/api/admin/utilisateurs/{$this->etudiant->id}", ['actif' => false])->assertOk();
        $this->actingAs($this->etudiant->fresh())->getJson('/api/planning')->assertUnauthorized();
    }
}
