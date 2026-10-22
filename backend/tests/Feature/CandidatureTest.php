<?php

namespace Tests\Feature;

use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
    }

    private function valide(array $surcharge = []): array
    {
        return array_merge([
            'formation' => 'cda-bac4', 'prenom' => 'Kélia', 'nom' => 'Payet', 'email' => 'kelia@exemple.re',
            'telephone' => '0692 12 34 56', 'message' => 'Motivée !', 'consentement_rgpd' => true,
        ], $surcharge);
    }

    public function test_une_candidature_valide_est_enregistree(): void
    {
        $this->postJson('/api/candidatures', $this->valide())->assertCreated();
        $this->assertDatabaseHas('candidatures', ['email' => 'kelia@exemple.re', 'statut' => 'recue']);
    }

    public function test_le_consentement_rgpd_est_obligatoire(): void
    {
        $this->postJson('/api/candidatures', $this->valide(['consentement_rgpd' => false]))
            ->assertStatus(422)->assertJsonValidationErrors('consentement_rgpd');
    }

    public function test_validation_stricte_des_champs(): void
    {
        $this->postJson('/api/candidatures', $this->valide(['email' => 'pas-un-email', 'telephone' => '12', 'prenom' => '<script>', 'formation' => 'inconnue']))
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'telephone', 'prenom', 'formation']);
        $this->assertDatabaseCount('candidatures', 0);
    }

    public function test_le_pot_de_miel_bloque_les_robots(): void
    {
        $this->postJson('/api/candidatures', $this->valide(['site_web' => 'http://spam.example']))
            ->assertStatus(422)->assertJsonValidationErrors('site_web');
    }

    public function test_les_envois_sont_limites_par_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/candidatures', $this->valide(['email' => "c$i@exemple.re"]))->assertCreated();
        }
        $this->postJson('/api/candidatures', $this->valide(['email' => 'c6@exemple.re']))->assertStatus(429);
    }

    public function test_formulaire_de_contact(): void
    {
        $this->postJson('/api/contact', ['nom' => 'Jean', 'email' => 'jean@exemple.re', 'sujet' => 'Info', 'message' => 'Bonjour, une question.', 'consentement_rgpd' => true])->assertCreated();
        $this->postJson('/api/contact', ['nom' => 'J', 'email' => 'x'])->assertStatus(422);
    }
}
