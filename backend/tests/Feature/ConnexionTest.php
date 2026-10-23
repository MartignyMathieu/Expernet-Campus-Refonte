<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

/** Parcours de connexion : secret MFA illisible, réinitialisation par l'administration ou en ligne de commande. */
class ConnexionTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->construireScenario();
    }

    public function test_un_secret_mfa_chiffre_avec_une_autre_cle_donne_un_message_clair(): void
    {
        // Simule un secret enregistré avec une ancienne APP_KEY
        DB::table('users')->where('id', $this->formateur->id)->update(['two_factor_secret' => 'eyJpdiI6ImFuY2llbm5lLWNsZSJ9']);

        $this->postJson('/api/auth/login', ['email' => $this->formateur->email, 'password' => 'MotDePasse-Test-123'])->assertOk()->assertJsonPath('two_factor', true);
        $this->postJson('/api/auth/two-factor-challenge', ['code' => '123456'])
            ->assertStatus(409)->assertJsonPath('code', 'MFA_ILLISIBLE');
        $this->assertGuest();
    }

    public function test_l_administration_reinitialise_la_mfa_d_un_compte(): void
    {
        $this->actingAs($this->admin)->postJson("/api/admin/utilisateurs/{$this->formateur->id}/mfa/reinitialiser")->assertOk();
        $this->assertFalse($this->formateur->fresh()->aMfaActive());

        // Le formateur se reconnecte : pas de second facteur demandé, mais l'activation redevient obligatoire
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => $this->formateur->email, 'password' => 'MotDePasse-Test-123'])->assertOk()->assertJsonPath('two_factor', false);
        $this->getJson('/api/planning')->assertForbidden()->assertJsonPath('code', 'MFA_REQUIRED');
    }

    public function test_seule_l_administration_reinitialise_et_jamais_son_propre_compte(): void
    {
        $this->actingAs($this->formateur)->postJson("/api/admin/utilisateurs/{$this->etudiant->id}/mfa/reinitialiser")->assertForbidden();
        $this->actingAs($this->admin)->postJson("/api/admin/utilisateurs/{$this->admin->id}/mfa/reinitialiser")->assertStatus(422);
    }

    public function test_commande_de_secours_en_ligne_de_commande(): void
    {
        $this->artisan('mfa:reinitialiser', ['email' => $this->formateur->email, '--force' => true])->assertSuccessful();
        $this->assertNull($this->formateur->fresh()->two_factor_secret);
        $this->artisan('mfa:reinitialiser', ['email' => 'inconnu@exemple.test', '--force' => true])->assertFailed();
    }

    public function test_chacun_peut_desactiver_sa_propre_mfa_avec_son_mot_de_passe(): void
    {
        $this->actingAs($this->formateur)->postJson('/api/me/mfa/desactiver', ['password' => 'faux'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertTrue($this->formateur->fresh()->aMfaActive());

        $this->actingAs($this->formateur)->postJson('/api/me/mfa/desactiver', ['password' => 'MotDePasse-Test-123'])
            ->assertOk()->assertJsonPath('obligatoire', true);
        $this->assertFalse($this->formateur->fresh()->aMfaActive());

        // Rôle où la MFA est obligatoire : l'espace est bloqué jusqu'à la réactivation
        $this->actingAs($this->formateur->fresh())->getJson('/api/planning')->assertForbidden()->assertJsonPath('code', 'MFA_REQUIRED');
    }

    public function test_un_etudiant_peut_desactiver_une_mfa_facultative_et_garder_l_acces(): void
    {
        $this->etudiant->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($this->etudiant)->postJson('/api/me/mfa/desactiver', ['password' => 'MotDePasse-Test-123'])
            ->assertOk()->assertJsonPath('obligatoire', false);
        $this->actingAs($this->etudiant->fresh())->getJson('/api/planning')->assertOk();
    }

    public function test_desactiver_exige_une_session_et_une_mfa_active(): void
    {
        $this->postJson('/api/me/mfa/desactiver', ['password' => 'MotDePasse-Test-123'])->assertUnauthorized();
        $this->actingAs($this->etudiant)->postJson('/api/me/mfa/desactiver', ['password' => 'MotDePasse-Test-123'])->assertStatus(422);
    }
}
