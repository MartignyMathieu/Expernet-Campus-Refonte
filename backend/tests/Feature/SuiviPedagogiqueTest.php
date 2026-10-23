<?php

namespace Tests\Feature;

use App\Models\Emargement;
use App\Models\JournalAudit;
use App\Models\Note;
use App\Notifications\NotificationEnt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ScenarioScolaire;
use Tests\TestCase;

/** Devoirs et correction, justificatifs d'absence, cahier de textes, notifications, journal d'audit, santé. */
class SuiviPedagogiqueTest extends TestCase
{
    use RefreshDatabase, ScenarioScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->construireScenario();
    }

    private function publierDevoir(array $surcharge = []): int
    {
        return $this->actingAs($this->formateur)->postJson("/api/promotions/{$this->promotion->id}/devoirs", $surcharge + [
            'titre' => 'TP API REST', 'consignes' => 'Exposer une ressource CRUD.', 'module_id' => $this->module->id,
            'echeance' => now()->addDays(3)->toIso8601String(), 'coefficient' => 2,
        ])->assertCreated()->json('id');
    }

    public function test_publication_d_un_devoir_et_droits(): void
    {
        Notification::fake();
        $this->publierDevoir();
        Notification::assertSentTo([$this->etudiant, $this->autreEtudiant], NotificationEnt::class, fn ($n, $canaux) => $n->categorie === 'devoir' && in_array('mail', $canaux, true));
        Notification::assertNotSentTo($this->etudiantHorsPromo, NotificationEnt::class);

        $url = "/api/promotions/{$this->promotion->id}/devoirs";
        $donnees = ['titre' => 'X', 'module_id' => $this->module->id, 'echeance' => now()->addDay()->toIso8601String()];
        $this->actingAs($this->etudiant)->postJson($url, $donnees)->assertForbidden();
        $this->actingAs($this->autreFormateur)->postJson($url, $donnees)->assertForbidden();
        $this->actingAs($this->formateur)->postJson($url, ['echeance' => now()->subDay()->toIso8601String()] + $donnees)->assertStatus(422)->assertJsonValidationErrors('echeance');

        $this->assertDatabaseHas('journal_audit', ['action' => 'devoir.creation', 'user_id' => $this->formateur->id]);
    }

    public function test_rendu_remplacable_puis_correction_reportee_dans_les_notes(): void
    {
        $id = $this->publierDevoir();
        $rendre = fn ($nom) => $this->actingAs($this->etudiant)->post("/api/devoirs/{$id}/rendu", ['fichier' => UploadedFile::fake()->create($nom, 20, 'application/pdf')], ['Accept' => 'application/json']);

        $rendre('v1.pdf')->assertCreated()->assertJsonPath('en_retard', false);
        $rendu = $rendre('v2.pdf')->assertOk()->assertJsonPath('nom_original', 'v2.pdf')->json('id');
        $this->assertSame(1, \App\Models\Document::where('devoir_id', $id)->count());

        // Liste : l'apprenant voit son rendu, l'équipe voit l'avancement
        $this->actingAs($this->etudiant)->getJson("/api/promotions/{$this->promotion->id}/devoirs")->assertJsonPath('0.mon_rendu.id', $rendu);
        $this->actingAs($this->formateur)->getJson("/api/promotions/{$this->promotion->id}/devoirs")->assertJsonPath('0.nb_rendus', 1)->assertJsonPath('0.effectif', 2);
        $this->actingAs($this->autreEtudiant)->getJson("/api/devoirs/{$id}")->assertJsonPath('mon_rendu', null);
        $this->actingAs($this->formateur)->getJson("/api/devoirs/{$id}")->assertJsonCount(2, 'apprenants')->assertJsonPath('peut_corriger', true);

        // Correction : seuls les enseignants du module, note reportée avec le coefficient du devoir
        $this->actingAs($this->autreFormateur)->postJson("/api/rendus/{$rendu}/correction", ['note' => 12])->assertForbidden();
        $this->actingAs($this->etudiant)->postJson("/api/rendus/{$rendu}/correction", ['note' => 20])->assertForbidden();
        $this->actingAs($this->formateur)->postJson("/api/rendus/{$rendu}/correction", ['note' => 21])->assertStatus(422);
        $this->actingAs($this->formateur)->postJson("/api/rendus/{$rendu}/correction", ['note' => 14.5, 'commentaire' => 'Bonne structure.'])
            ->assertOk()->assertJsonPath('note', 14.5)->assertJsonPath('commentaire', 'Bonne structure.');

        $note = Note::where('etudiant_id', $this->etudiant->id)->firstOrFail();
        $this->assertSame([14.5, 2.0, 'TP API REST'], [(float) $note->valeur, (float) $note->coefficient, $note->intitule]);

        // Recorrection : la même note est mise à jour, pas dupliquée
        $this->actingAs($this->formateur)->postJson("/api/rendus/{$rendu}/correction", ['note' => 16])->assertOk();
        $this->assertSame(1, Note::where('etudiant_id', $this->etudiant->id)->count());
        $this->assertSame(16.0, (float) $note->fresh()->valeur);
        $this->assertSame(2, JournalAudit::where('action', 'rendu.correction')->count());

        // Un rendu corrigé ne peut plus être remplacé ni supprimé par l'apprenant
        $rendre('v3.pdf')->assertStatus(422);
        $this->actingAs($this->etudiant)->deleteJson("/api/documents/{$rendu}")->assertForbidden();
    }

    public function test_un_rendu_apres_l_echeance_est_signale_en_retard(): void
    {
        $id = $this->publierDevoir(['echeance' => now()->addHour()->toIso8601String()]);
        $this->travel(2)->hours();
        $this->actingAs($this->etudiant)->post("/api/devoirs/{$id}/rendu", ['fichier' => UploadedFile::fake()->create('tard.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('en_retard', true);
    }

    public function test_justificatif_rattache_a_l_absence_puis_valide(): void
    {
        $this->seance->forceFill(['cloturee_at' => now()])->save();
        $absence = Emargement::create(['seance_id' => $this->seance->id, 'user_id' => $this->etudiant->id, 'statut' => 'absent']);
        $url = "/api/emargements/{$absence->id}/justificatif";

        $this->actingAs($this->autreEtudiant)->post($url, ['fichier' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->etudiant)->post($url, ['fichier' => UploadedFile::fake()->create('c.exe', 10)], ['Accept' => 'application/json'])->assertStatus(422);
        $doc = $this->actingAs($this->etudiant)->post($url, ['fichier' => UploadedFile::fake()->create('certificat.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->json('justificatif.id');

        $this->assertSame($doc, $absence->fresh()->justificatif_id);
        $fiche = $this->actingAs($this->formateur)->getJson("/api/promotions/{$this->promotion->id}/etudiants/{$this->etudiant->id}/assiduite")->json('detail_absences.0');
        $this->assertSame('certificat.pdf', $fiche['justificatif']['nom']);
        $this->actingAs($this->formateur)->get($fiche['justificatif']['url'])->assertOk();

        $this->actingAs($this->formateur)->patchJson("/api/emargements/{$absence->id}/justification", ['justifiee' => true, 'motif' => 'Certificat médical'])->assertOk();
        $this->assertSame(['justificatif.depot', 'absence.justification'], JournalAudit::orderBy('id')->pluck('action')->all());
        $this->assertSame(1, $this->etudiant->fresh()->unreadNotifications()->get()->where('data.categorie', 'absence')->count());
    }

    public function test_cahier_de_textes_et_travail_a_faire(): void
    {
        $url = "/api/seances/{$this->seance->id}/cahier";
        $donnees = ['contenu_vu' => 'Routes et contrôleurs.', 'travail_a_faire' => 'Finir le TP 2.', 'travail_pour' => today()->addDays(2)->toDateString()];

        $this->actingAs($this->autreFormateur)->patchJson($url, $donnees)->assertForbidden();
        $this->actingAs($this->etudiant)->patchJson($url, $donnees)->assertForbidden();
        $this->actingAs($this->formateur)->patchJson($url, ['travail_a_faire' => 'Sans date'])->assertStatus(422)->assertJsonValidationErrors('travail_pour');
        $this->actingAs($this->formateur)->patchJson($url, $donnees)->assertOk()->assertJsonPath('travail_a_faire', 'Finir le TP 2.');

        $this->actingAs($this->etudiant)->getJson('/api/planning')
            ->assertJsonPath('seances.0.cahier.contenu_vu', 'Routes et contrôleurs.')->assertJsonPath('seances.0.cahier.modifiable', false);
        $this->publierDevoir();
        $aFaire = $this->actingAs($this->etudiant)->getJson('/api/me/a-faire')->assertOk()->json();
        $this->assertSame(['travail', 'devoir'], array_column($aFaire, 'type'));
        $this->assertSame(1, $this->etudiant->fresh()->notifications()->get()->where('data.categorie', 'travail')->count());
    }

    public function test_notifications_de_l_ent(): void
    {
        $this->publierDevoir();
        $reponse = $this->actingAs($this->etudiant)->getJson('/api/notifications')->assertOk()->assertJsonPath('non_lues', 1);
        $id = $reponse->json('notifications.0.id');
        $this->assertSame('/ent/etudiant/devoirs', $reponse->json('notifications.0.lien'));

        $this->actingAs($this->etudiant)->postJson("/api/notifications/{$id}/lue")->assertNoContent();
        $this->actingAs($this->etudiant)->getJson('/api/notifications')->assertJsonPath('non_lues', 0)->assertJsonPath('notifications.0.lue', true);
        $this->actingAs($this->autreEtudiant)->postJson("/api/notifications/{$id}/lue")->assertNotFound();
        $this->actingAs($this->autreEtudiant)->postJson('/api/notifications/lues')->assertNoContent();
        $this->actingAs($this->autreEtudiant)->getJson('/api/notifications')->assertJsonPath('non_lues', 0);
    }

    public function test_journal_d_audit_reserve_a_l_administration(): void
    {
        $this->actingAs($this->admin)->patchJson("/api/admin/utilisateurs/{$this->etudiant->id}", ['actif' => false])->assertOk();
        $this->actingAs($this->formateur)->postJson('/api/notes', [
            'module_id' => $this->module->id, 'etudiant_id' => $this->autreEtudiant->id, 'intitule' => 'QCM', 'valeur' => 11, 'date_evaluation' => today()->toDateString(),
        ])->assertCreated();

        $this->actingAs($this->formateur)->getJson('/api/admin/journal')->assertForbidden();
        $journal = $this->actingAs($this->admin)->getJson('/api/admin/journal')->assertOk();
        $this->assertSame(['note.creation', 'compte.modification'], array_column($journal->json('data'), 'action'));
        $this->assertSame($this->admin->id, $journal->json('data.1.auteur.id'));
        $this->actingAs($this->admin)->getJson('/api/admin/journal?action=note.')->assertJsonCount(1, 'data');
        $this->actingAs($this->admin)->getJson('/api/admin/journal?recherche=QCM')->assertJsonCount(1, 'data');
    }

    public function test_page_de_sante(): void
    {
        $this->getJson('/api/sante')->assertOk()->assertJsonPath('statut', 'ok')
            ->assertJsonPath('verifications.base_de_donnees.ok', true)->assertJsonPath('verifications.stockage.ok', true);
    }

    public function test_creation_d_un_administrateur_en_ligne_de_commande(): void
    {
        $this->artisan('expernet:creer-admin', ['email' => 'direction@exemple.re', '--prenom' => 'Anne', '--nom' => 'Direction'])
            ->expectsQuestion('Mot de passe (12 caractères minimum, majuscules, minuscules et chiffres)', 'faible')
            ->assertFailed();
        $this->artisan('expernet:creer-admin', ['email' => 'direction@exemple.re', '--prenom' => 'Anne', '--nom' => 'Direction'])
            ->expectsQuestion('Mot de passe (12 caractères minimum, majuscules, minuscules et chiffres)', 'MotDePasseSolide2026')
            ->assertSuccessful();

        $admin = \App\Models\User::where('email', 'direction@exemple.re')->firstOrFail();
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertDatabaseHas('journal_audit', ['action' => 'compte.creation', 'cible_id' => $admin->id]);
    }
}
