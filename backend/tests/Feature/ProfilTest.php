<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

/** Profil : photo (ré-encodée, servie aux seuls connectés), coordonnées, mot de passe, assiduité personnelle. */
class ProfilTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->construireScenario();
    }

    public function test_la_photo_est_recadree_en_carre_et_reencodee_en_jpeg(): void
    {
        $url = $this->actingAs($this->etudiant)->post('/api/me/avatar', ['photo' => UploadedFile::fake()->image('moi.png', 800, 500)], ['Accept' => 'application/json'])
            ->assertCreated()->json('avatar_url');

        $this->assertStringStartsWith("/api/utilisateurs/{$this->etudiant->id}/avatar?v=", $url);
        $chemin = $this->etudiant->fresh()->avatar_path;
        Storage::disk('local')->assertExists($chemin);
        [$largeur, $hauteur, $type] = getimagesizefromstring(Storage::disk('local')->get($chemin));
        $this->assertSame([400, 400, IMAGETYPE_JPEG], [$largeur, $hauteur, $type]);

        $this->actingAs($this->etudiant)->getJson('/api/me')->assertJsonPath('avatar_url', $url);
    }

    public function test_une_nouvelle_photo_remplace_l_ancienne_et_peut_etre_supprimee(): void
    {
        $this->actingAs($this->etudiant)->post('/api/me/avatar', ['photo' => UploadedFile::fake()->image('a.jpg', 300, 300)], ['Accept' => 'application/json'])->assertCreated();
        $ancien = $this->etudiant->fresh()->avatar_path;
        $this->actingAs($this->etudiant)->post('/api/me/avatar', ['photo' => UploadedFile::fake()->image('b.jpg', 300, 300)], ['Accept' => 'application/json'])->assertCreated();
        Storage::disk('local')->assertMissing($ancien);

        $nouveau = $this->etudiant->fresh()->avatar_path;
        $this->actingAs($this->etudiant)->deleteJson('/api/me/avatar')->assertOk()->assertJsonPath('avatar_url', null);
        Storage::disk('local')->assertMissing($nouveau);
        $this->assertNull($this->etudiant->fresh()->avatar_path);
    }

    public function test_seules_les_images_raisonnables_sont_acceptees(): void
    {
        $this->actingAs($this->etudiant)->postJson('/api/me/avatar', ['photo' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->actingAs($this->etudiant)->postJson('/api/me/avatar', ['photo' => UploadedFile::fake()->image('mini.png', 40, 40)])
            ->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_la_photo_n_est_visible_que_des_utilisateurs_connectes(): void
    {
        Storage::disk('local')->put('avatars/x.jpg', UploadedFile::fake()->image('a.jpg', 200, 200)->getContent());
        $this->etudiant->forceFill(['avatar_path' => 'avatars/x.jpg'])->save();

        $this->getJson("/api/utilisateurs/{$this->etudiant->id}/avatar")->assertUnauthorized();
        $this->actingAs($this->formateur)->get("/api/utilisateurs/{$this->etudiant->id}/avatar")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->formateur)->get("/api/utilisateurs/{$this->autreEtudiant->id}/avatar")->assertNotFound();
    }

    public function test_coordonnees_du_profil(): void
    {
        $this->actingAs($this->etudiant)->patchJson('/api/me/profil', ['telephone' => '0692 12 34 56', 'bio' => '<b>Passionné</b> de back-end'])
            ->assertOk()->assertJsonPath('bio', 'Passionné de back-end');
        $this->actingAs($this->etudiant)->getJson('/api/me')->assertJsonPath('telephone', '0692 12 34 56');

        $this->actingAs($this->etudiant)->patchJson('/api/me/profil', ['telephone' => 'appelez-moi'])->assertStatus(422)->assertJsonValidationErrors('telephone');
    }

    public function test_changement_de_mot_de_passe(): void
    {
        $this->etudiant->forceFill(['password' => 'AncienMotDePasse1'])->save();

        $this->actingAs($this->etudiant)->putJson('/api/me/mot-de-passe', [
            'mot_de_passe_actuel' => 'mauvais', 'password' => 'NouveauMotDePasse2', 'password_confirmation' => 'NouveauMotDePasse2',
        ])->assertStatus(422)->assertJsonValidationErrors('mot_de_passe_actuel');

        $this->actingAs($this->etudiant)->putJson('/api/me/mot-de-passe', [
            'mot_de_passe_actuel' => 'AncienMotDePasse1', 'password' => 'court', 'password_confirmation' => 'court',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->actingAs($this->etudiant)->putJson('/api/me/mot-de-passe', [
            'mot_de_passe_actuel' => 'AncienMotDePasse1', 'password' => 'NouveauMotDePasse2', 'password_confirmation' => 'NouveauMotDePasse2',
        ])->assertOk();
        $this->assertTrue(Hash::check('NouveauMotDePasse2', $this->etudiant->fresh()->password));
    }

    public function test_l_apprenant_consulte_son_assiduite(): void
    {
        $this->actingAs($this->etudiant)->getJson('/api/me/assiduite')->assertOk()
            ->assertJsonPath('0.promotion.id', $this->promotion->id)
            ->assertJsonStructure([['taux', 'seuil', 'presences', 'absences', 'excuses', 'detail_absences']]);
        $this->actingAs($this->formateur)->getJson('/api/me/assiduite')->assertForbidden();
    }

    public function test_l_equipe_justifie_une_absence(): void
    {
        $this->seance->forceFill(['cloturee_at' => now()])->save();
        $absence = \App\Models\Emargement::create(['seance_id' => $this->seance->id, 'user_id' => $this->etudiant->id, 'statut' => 'absent']);
        $url = "/api/emargements/{$absence->id}/justification";

        $this->actingAs($this->etudiant)->patchJson($url, ['justifiee' => true])->assertForbidden();
        $this->actingAs($this->autreFormateur)->patchJson($url, ['justifiee' => true])->assertForbidden();
        $this->actingAs($this->formateur)->patchJson($url, ['justifiee' => true, 'motif' => 'Certificat médical'])->assertOk()->assertJsonPath('statut', 'excuse');
        $this->assertSame($this->formateur->id, $absence->fresh()->justifie_par);

        $bilan = $this->actingAs($this->etudiant)->getJson('/api/me/assiduite')->json('0');
        $this->assertSame(100.0, (float) $bilan['taux']);
        $this->assertSame('Certificat médical', $bilan['detail_absences'][0]['motif']);

        $this->actingAs($this->admin)->getJson("/api/promotions/{$this->promotion->id}/etudiants/{$this->etudiant->id}/assiduite")
            ->assertOk()->assertJsonPath('excuses', 1)->assertJsonPath('etudiant.id', $this->etudiant->id);
        $this->actingAs($this->admin)->getJson("/api/promotions/{$this->promotion->id}/etudiants/{$this->etudiantHorsPromo->id}/assiduite")->assertNotFound();

        $this->actingAs($this->admin)->patchJson($url, ['justifiee' => false])->assertOk()->assertJsonPath('statut', 'absent');

        $present = \App\Models\Emargement::create(['seance_id' => $this->creerSeance(today(), 'apres_midi')->id, 'user_id' => $this->autreEtudiant->id, 'statut' => 'present']);
        $this->actingAs($this->formateur)->patchJson("/api/emargements/{$present->id}/justification", ['justifiee' => true])->assertStatus(422);
    }
}
