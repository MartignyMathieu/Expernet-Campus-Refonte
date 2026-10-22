<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

class AuthMfaTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->construireScenario();
    }

    public function test_les_routes_ent_exigent_une_session(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/planning')->assertUnauthorized();
    }

    public function test_connexion_sans_mfa_ouvre_la_session(): void
    {
        $this->postJson('/api/auth/login', ['email' => $this->etudiant->email, 'password' => 'MotDePasse-Test-123'])
            ->assertOk()->assertJsonPath('two_factor', false);
        $this->assertAuthenticatedAs($this->etudiant);
    }

    public function test_connexion_avec_mfa_demande_un_second_facteur(): void
    {
        $this->postJson('/api/auth/login', ['email' => $this->formateur->email, 'password' => 'MotDePasse-Test-123'])
            ->assertOk()->assertJsonPath('two_factor', true);
        $this->assertGuest();
    }

    public function test_mauvais_mot_de_passe_refuse(): void
    {
        $this->postJson('/api/auth/login', ['email' => $this->etudiant->email, 'password' => 'faux'])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $this->etudiant->update(['actif' => false]);
        $this->postJson('/api/auth/login', ['email' => $this->etudiant->email, 'password' => 'MotDePasse-Test-123'])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_la_connexion_est_limitee_contre_la_force_brute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => $this->etudiant->email, 'password' => 'faux']);
        }
        $this->postJson('/api/auth/login', ['email' => $this->etudiant->email, 'password' => 'faux'])->assertStatus(429);
    }

    public function test_un_formateur_sans_mfa_est_bloque_avec_le_code_mfa_required(): void
    {
        $sansMfa = User::factory()->role(Role::FORMATEUR)->create();

        $this->actingAs($sansMfa)->getJson('/api/me')->assertOk()->assertJsonPath('mfa.obligatoire', true)->assertJsonPath('mfa.active', false);
        $this->actingAs($sansMfa)->getJson('/api/planning')->assertForbidden()->assertJsonPath('code', 'MFA_REQUIRED');
    }

    public function test_un_admin_sans_mfa_est_bloque(): void
    {
        $sansMfa = User::factory()->role(Role::ADMIN)->create();
        $this->actingAs($sansMfa)->getJson('/api/admin/tableau-de-bord')->assertForbidden()->assertJsonPath('code', 'MFA_REQUIRED');
    }

    public function test_un_formateur_avec_mfa_accede_a_son_espace(): void
    {
        $this->actingAs($this->formateur)->getJson('/api/planning')->assertOk();
    }

    public function test_l_etudiant_n_a_pas_l_obligation_de_mfa(): void
    {
        $this->actingAs($this->etudiant)->getJson('/api/planning')->assertOk();
    }

    public function test_activation_de_la_mfa_fournit_un_qr_code(): void
    {
        $sansMfa = User::factory()->role(Role::FORMATEUR)->create();
        $this->actingAs($sansMfa)->postJson('/api/auth/user/two-factor-authentication')->assertOk();
        $this->actingAs($sansMfa)->getJson('/api/auth/user/two-factor-qr-code')->assertOk()->assertJsonStructure(['svg', 'url']);

        // Tant que la MFA n'est pas confirmée par un code, l'accès reste bloqué.
        $this->actingAs($sansMfa)->getJson('/api/planning')->assertForbidden();
    }

    public function test_le_profil_n_expose_aucun_secret(): void
    {
        $json = $this->actingAs($this->formateur)->getJson('/api/me')->assertOk()->json();
        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('two_factor_secret', $json);
    }
}
