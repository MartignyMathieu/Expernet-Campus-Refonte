<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

class MessagerieTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->construireScenario();
    }

    private function ouvrir(User $de, User $vers)
    {
        return $this->actingAs($de)->postJson('/api/messagerie/conversations', ['user_id' => $vers->id]);
    }

    public function test_l_etudiant_voit_ses_formateurs_et_sa_promotion_dans_ses_contacts(): void
    {
        $ids = collect($this->actingAs($this->etudiant)->getJson('/api/messagerie/contacts')->assertOk()->json())->pluck('id');

        $this->assertTrue($ids->contains($this->formateur->id));
        $this->assertTrue($ids->contains($this->autreEtudiant->id));
        $this->assertTrue($ids->contains($this->admin->id));
        $this->assertFalse($ids->contains($this->etudiantHorsPromo->id), 'Un étudiant d\'une autre promotion est hors périmètre.');
        $this->assertFalse($ids->contains($this->etudiant->id));
    }

    public function test_une_conversation_privee_est_unique_par_paire(): void
    {
        $id = $this->ouvrir($this->etudiant, $this->formateur)->assertCreated()->json('id');
        $this->ouvrir($this->formateur, $this->etudiant)->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_impossible_d_ecrire_hors_perimetre(): void
    {
        $this->ouvrir($this->etudiant, $this->etudiantHorsPromo)->assertForbidden();
        $this->ouvrir($this->etudiant, $this->etudiant)->assertStatus(422);
    }

    public function test_echange_de_messages_et_compteur_de_non_lus(): void
    {
        $id = $this->ouvrir($this->etudiant, $this->formateur)->json('id');

        $this->actingAs($this->etudiant)->postJson("/api/messagerie/conversations/$id/messages", ['contenu' => 'Bonjour, une question sur le TP'])
            ->assertCreated()->assertJsonPath('moi', true);

        $this->actingAs($this->formateur)->getJson('/api/messagerie/non-lus')->assertJsonPath('non_lus', 1);
        $this->actingAs($this->etudiant)->getJson('/api/messagerie/non-lus')->assertJsonPath('non_lus', 0);

        $this->actingAs($this->formateur)->getJson("/api/messagerie/conversations/$id/messages")->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.contenu', 'Bonjour, une question sur le TP');
        $this->actingAs($this->formateur)->getJson('/api/messagerie/non-lus')->assertJsonPath('non_lus', 0);
    }

    public function test_seuls_les_participants_lisent_et_ecrivent(): void
    {
        $id = $this->ouvrir($this->etudiant, $this->formateur)->json('id');

        $this->actingAs($this->autreEtudiant)->getJson("/api/messagerie/conversations/$id/messages")->assertForbidden();
        $this->actingAs($this->autreEtudiant)->postJson("/api/messagerie/conversations/$id/messages", ['contenu' => 'intrusion'])->assertForbidden();
    }

    public function test_le_contenu_est_valide_et_debarrasse_du_html(): void
    {
        $id = $this->ouvrir($this->etudiant, $this->formateur)->json('id');
        $url = "/api/messagerie/conversations/$id/messages";

        $this->actingAs($this->etudiant)->postJson($url, ['contenu' => ''])->assertStatus(422);
        $this->actingAs($this->etudiant)->postJson($url, ['contenu' => str_repeat('a', 2001)])->assertStatus(422);
        $this->actingAs($this->etudiant)->postJson($url, ['contenu' => '<p></p>'])->assertStatus(422);
        $this->actingAs($this->etudiant)->postJson($url, ['contenu' => '<script>alert(1)</script>'])->assertCreated()->assertJsonPath('contenu', 'alert(1)');
        $this->actingAs($this->etudiant)->postJson($url, ['contenu' => '<b>Salut</b> <img src=x onerror=alert(1)>'])
            ->assertCreated()->assertJsonPath('contenu', 'Salut');
    }

    public function test_un_etudiant_ne_peut_pas_creer_de_groupe(): void
    {
        $this->actingAs($this->etudiant)->postJson('/api/messagerie/groupes', ['nom' => 'Mon groupe', 'membres' => [$this->autreEtudiant->id]])->assertForbidden();
    }

    public function test_le_formateur_cree_un_groupe_avec_toute_sa_promotion(): void
    {
        $r = $this->actingAs($this->formateur)->postJson('/api/messagerie/groupes', ['nom' => 'Projet fil rouge', 'promotion_id' => $this->promotion->id])
            ->assertCreated()->assertJsonPath('type', 'groupe')->assertJsonPath('gestionnaire', true);

        $this->assertCount(3, $r->json('membres'));   // formateur + 2 étudiants de la promotion
        $groupe = Conversation::findOrFail($r->json('id'));
        $this->assertTrue($groupe->aParticipant($this->etudiant));
        $this->actingAs($this->etudiant)->getJson('/api/messagerie/conversations')->assertOk()->assertJsonPath('0.titre', 'Projet fil rouge');
    }

    public function test_le_formateur_ne_peut_pas_ajouter_hors_perimetre_ni_viser_une_promotion_ou_il_n_enseigne_pas(): void
    {
        $this->actingAs($this->formateur)->postJson('/api/messagerie/groupes', ['nom' => 'Groupe X', 'membres' => [$this->etudiantHorsPromo->id]])->assertForbidden();
        $this->actingAs($this->autreFormateur)->postJson('/api/messagerie/groupes', ['nom' => 'Groupe X', 'promotion_id' => $this->promotion->id])->assertForbidden();
    }

    public function test_seul_le_gestionnaire_modifie_les_membres(): void
    {
        $id = $this->actingAs($this->formateur)->postJson('/api/messagerie/groupes', ['nom' => 'Binômes', 'membres' => [$this->etudiant->id]])->json('id');

        $this->actingAs($this->autreFormateur)->patchJson("/api/messagerie/groupes/$id/membres", ['ajouter' => [$this->autreEtudiant->id]])->assertForbidden();

        $this->actingAs($this->formateur)->patchJson("/api/messagerie/groupes/$id/membres", ['ajouter' => [$this->autreEtudiant->id], 'retirer' => [$this->etudiant->id]])
            ->assertOk()->assertJsonCount(2, 'membres');
        $this->actingAs($this->etudiant)->getJson("/api/messagerie/conversations/$id/messages")->assertForbidden();
    }

    public function test_un_message_peut_porter_des_pieces_jointes_et_des_emoticones(): void
    {
        Storage::fake('local');
        $id = $this->ouvrir($this->etudiant, $this->formateur)->json('id');

        $r = $this->actingAs($this->etudiant)->post("/api/messagerie/conversations/$id/messages", [
            'contenu' => 'Voici mon rapport 📄👍',
            'fichiers' => [UploadedFile::fake()->create('rapport.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('schema.png', 300, 200)],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('contenu', 'Voici mon rapport 📄👍')
            ->assertJsonCount(2, 'pieces_jointes')
            ->assertJsonPath('pieces_jointes.0.nom', 'rapport.pdf')
            ->assertJsonPath('pieces_jointes.1.image', true);

        $url = $r->json('pieces_jointes.0.url');
        $this->assertArrayNotHasKey('chemin', $r->json('pieces_jointes.0'), 'Le chemin de stockage n\'est jamais exposé.');

        // Le destinataire l'ouvre en ligne (PDF) ou le télécharge
        $this->actingAs($this->formateur)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $this->actingAs($this->formateur)->get($url.'?telechargement=1')->headers->get('Content-Disposition'));
    }

    public function test_un_message_peut_ne_contenir_qu_un_fichier_et_l_extrait_l_indique(): void
    {
        Storage::fake('local');
        $id = $this->ouvrir($this->etudiant, $this->formateur)->json('id');
        $this->actingAs($this->etudiant)->post("/api/messagerie/conversations/$id/messages", ['fichiers' => [UploadedFile::fake()->create('notes.txt', 2, 'text/plain')]], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('contenu', '');

        $this->actingAs($this->formateur)->getJson('/api/messagerie/conversations')->assertJsonPath('0.dernier_message.extrait', '📎 notes.txt');
    }

    public function test_seuls_les_participants_accedent_aux_pieces_jointes(): void
    {
        Storage::fake('local');
        $id = $this->ouvrir($this->etudiant, $this->formateur)->json('id');
        $url = $this->actingAs($this->etudiant)->post("/api/messagerie/conversations/$id/messages", ['fichiers' => [UploadedFile::fake()->create('perso.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])
            ->json('pieces_jointes.0.url');

        $this->actingAs($this->autreEtudiant)->get($url)->assertForbidden();
    }

    public function test_les_fichiers_sont_controles(): void
    {
        Storage::fake('local');
        $id = $this->ouvrir($this->etudiant, $this->formateur)->json('id');
        $envoyer = fn (array $donnees) => $this->actingAs($this->etudiant)->post("/api/messagerie/conversations/$id/messages", $donnees, ['Accept' => 'application/json']);

        $envoyer(['fichiers' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]])->assertStatus(422)->assertJsonValidationErrors('fichiers.0');
        $envoyer(['fichiers' => [UploadedFile::fake()->create('lourd.pdf', 11000, 'application/pdf')]])->assertStatus(422);
        $envoyer(['fichiers' => array_map(fn ($i) => UploadedFile::fake()->create("f$i.txt", 1, 'text/plain'), range(1, 6))])->assertStatus(422)->assertJsonValidationErrors('fichiers');
        $envoyer(['contenu' => '   '])->assertStatus(422);
        $this->assertDatabaseCount('message_pieces_jointes', 0);
    }

    public function test_la_messagerie_exige_une_session(): void
    {
        $this->getJson('/api/messagerie/conversations')->assertUnauthorized();
    }
}
