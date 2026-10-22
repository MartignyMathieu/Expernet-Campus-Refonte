<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Support\HtmlAssainisseur;
use Database\Seeders\ContenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContenuSeeder::class);
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

    public function test_l_offre_de_formation_continue(): void
    {
        $this->getJson('/api/formation-continue')->assertOk()->assertJsonCount(14, 'domaines')->assertJsonCount(4, 'chiffres')
            ->assertJsonStructure(['domaines' => [['slug', 'libelle', 'icone', 'stages']]]);
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
}
