<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Support\HtmlAssainisseur;
use Database\Seeders\ContenuSeeder;
use Database\Seeders\FormationContinueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ContenuSeeder::class, FormationContinueSeeder::class]);
    }

    public function test_la_faq_publique_est_servie(): void
    {
        $this->getJson('/api/faq')->assertOk()->assertJsonCount(9)->assertJsonStructure([['question', 'reponse', 'theme']]);
    }

    public function test_le_blog_est_pagine_trie_et_filtrable(): void
    {
        $r = $this->getJson('/api/articles')->assertOk()->assertJsonCount(12, 'data');
        $this->assertSame('2026-09-15', substr($r->json('data.0.publie_le'), 0, 10));

        $evenements = $this->getJson('/api/articles?categorie=evenements')->assertOk()->json('data');
        $this->assertCount(3, $evenements);
        $this->getJson('/api/articles?categorie=inconnue')->assertStatus(422);
    }

    public function test_un_article_et_ses_suggestions(): void
    {
        $this->getJson('/api/articles/comment-trouver-son-alternance-en-2026')->assertOk()
            ->assertJsonPath('article.titre', 'Comment trouver son alternance en 2026 ?')
            ->assertJsonPath('categorie_libelle', 'Conseils')
            ->assertJsonCount(3, 'suggestions');
        $this->getJson('/api/articles/inexistant')->assertNotFound();
    }

    public function test_l_offre_de_formation_continue_reprend_tout_le_catalogue(): void
    {
        $r = $this->getJson('/api/formation-continue')->assertOk()->assertJsonCount(14, 'domaines')->assertJsonCount(4, 'chiffres')
            ->assertJsonCount(3, 'formats')->assertJsonCount(3, 'locaux')
            ->assertJsonPath('contact.email', 'formation@expernet.re')
            ->assertJsonStructure(['domaines' => [['slug', 'libelle', 'icone', 'stages' => [['slug', 'intitule', 'duree', 'tarifs', 'prochaine_session']]]]]);

        $this->assertSame(149, collect($r->json('domaines'))->sum(fn ($d) => count($d['stages'])));
        // Le résumé du catalogue ne transporte pas les programmes complets
        $this->assertArrayNotHasKey('programme', $r->json('domaines.0.stages.0'));
    }

    public function test_la_fiche_d_un_stage_est_complete(): void
    {
        $this->getJson('/api/formation-continue/administration-windows-11')->assertOk()
            ->assertJsonPath('stage.intitule', 'Administration Windows 11')
            ->assertJsonPath('stage.domaine.slug', 'microsoft-technique')
            ->assertJsonPath('stage.duree', '5 jours')
            ->assertJsonPath('stage.tarifs.intra', 6500)
            ->assertJsonPath('stage.programme.0', '## Présentation et installation de Windows 11')
            ->assertJsonCount(5, 'stage.objectifs')
            ->assertJsonCount(3, 'similaires');
    }

    public function test_les_modalites_standard_completent_une_fiche_qui_n_en_precise_pas(): void
    {
        $standard = config('expernet.formation_continue.modalites.moyens');
        $this->getJson('/api/formation-continue/administration-windows-11')->assertJsonPath('stage.moyens', $standard);

        // Une fiche aux modalités spécifiques les conserve
        $vmware = $this->getJson('/api/formation-continue/vmware-vsphere-niveau-1')->json('stage.moyens');
        $this->assertNotSame($standard, $vmware);
    }

    public function test_un_stage_inconnu_renvoie_404(): void
    {
        $this->getJson('/api/formation-continue/inexistant')->assertNotFound();
    }

    public function test_le_seeder_de_formation_continue_est_rejouable(): void
    {
        $this->seed(FormationContinueSeeder::class);
        $this->assertSame(149, \App\Models\FcStage::count());
    }

    public function test_l_assainisseur_supprime_scripts_attributs_et_liens_dangereux(): void
    {
        $sale = '<p onclick="x()">Texte <strong>gras</strong></p><script>alert(1)</script><img src=x onerror=alert(1)>'
            .'<a href="javascript:alert(1)">piège</a><a href="https://expernet.re" style="color:red">ok</a><iframe src="//x"></iframe>';
        $propre = HtmlAssainisseur::nettoyer($sale);

        $this->assertStringNotContainsString('script', $propre);
        $this->assertStringNotContainsString('onerror', $propre);
        $this->assertStringNotContainsString('onclick', $propre);
        $this->assertStringNotContainsString('javascript:', $propre);
        $this->assertStringNotContainsString('iframe', $propre);
        $this->assertStringContainsString('<strong>gras</strong>', $propre);
        $this->assertStringContainsString('href="https://expernet.re"', $propre);
    }

    public function test_import_wordpress_via_l_api_rest(): void
    {
        Http::fake(['*/wp-json/wp/v2/posts*' => Http::sequence()
            ->push([[
                'slug' => 'nouvel-article', 'date' => '2026-10-01T10:00:00', 'link' => 'https://exemple.test/nouvel-article/',
                'title' => ['rendered' => 'Un nouvel article'], 'excerpt' => ['rendered' => '<p>Résumé</p>'],
                'content' => ['rendered' => '<p>Contenu</p><script>alert(1)</script>'],
                '_embedded' => ['wp:term' => [[['name' => 'Evènements']]], 'wp:featuredmedia' => [['source_url' => 'https://exemple.test/img.png']]],
            ]])
            ->push([])]);

        $this->artisan('blog:import-wordpress', ['site' => 'https://exemple.test'])->assertSuccessful();

        $article = Article::where('slug', 'nouvel-article')->firstOrFail();
        $this->assertSame('evenements', $article->categorie);
        $this->assertSame('<p>Contenu</p>', $article->contenu);
    }

    public function test_formation_continue_a_propos_actualites_et_planning(): void
    {
        $this->getJson('/api/formation-continue/a-propos')->assertOk()
            ->assertJsonCount(4, 'histoire')->assertJsonPath('histoire.0.annee', 2002)
            ->assertJsonCount(7, 'equipe')->assertJsonPath('certification.nom', 'Qualiopi')
            ->assertJsonCount(3, 'locaux');

        $actualites = $this->getJson('/api/formation-continue/actualites')->assertOk()->json();
        $this->assertCount(26, $actualites);
        $this->assertStringStartsWith('https://expernet.re/', $actualites[0]['url_source']);

        \App\Models\FcStage::query()->limit(2)->update(['prochaine_session' => today()->addDays(10)->toDateString()]);
        \App\Models\FcStage::query()->skip(2)->limit(1)->get()->each->update(['prochaine_session' => today()->subDay()->toDateString()]);
        $sessions = $this->getJson('/api/formation-continue/planning')->assertOk()->assertJsonStructure(['note', 'locaux', 'sessions'])->json('sessions');
        $this->assertNotEmpty($sessions);
        foreach ($sessions as $session) {
            $this->assertGreaterThanOrEqual(today()->toDateString(), $session['prochaine_session']);
        }
        $this->assertSame(collect($sessions)->pluck('prochaine_session')->sort()->values()->all(), collect($sessions)->pluck('prochaine_session')->all());
    }
}
