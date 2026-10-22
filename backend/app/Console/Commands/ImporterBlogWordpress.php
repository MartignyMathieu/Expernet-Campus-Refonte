<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Support\HtmlAssainisseur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Migration du blog WordPress existant vers la nouvelle plateforme, via l'API REST WordPress.
 * Exemple : php artisan blog:import-wordpress https://expernet-campus.re --limite=50
 */
class ImporterBlogWordpress extends Command
{
    protected $signature = 'blog:import-wordpress {site : URL du site WordPress} {--limite=100 : nombre maximum d\'articles}';

    protected $description = 'Importe les articles d\'un blog WordPress (titre, date, catégorie, image, contenu assaini)';

    public function handle(): int
    {
        $site = rtrim($this->argument('site'), '/');
        $limite = (int) $this->option('limite');
        $importes = 0;

        for ($page = 1; $importes < $limite; $page++) {
            $reponse = Http::timeout(20)->acceptJson()->get($site.'/wp-json/wp/v2/posts', [
                'per_page' => min(50, $limite), 'page' => $page, '_embed' => 'wp:featuredmedia,wp:term',
            ]);
            if (! $reponse->ok() || $reponse->json() === []) {
                break;
            }

            foreach ($reponse->json() as $post) {
                if ($importes >= $limite) {
                    break;
                }
                $contenu = HtmlAssainisseur::nettoyer($post['content']['rendered'] ?? '');
                $texte = trim(html_entity_decode(strip_tags($contenu)));
                $categories = collect($post['_embedded']['wp:term'][0] ?? [])->pluck('name')->map(fn ($n) => Str::lower(Str::ascii($n)));

                Article::updateOrCreate(['slug' => Str::limit(Str::slug(urldecode($post['slug'])), 150, '')], [
                    'titre' => Str::limit(html_entity_decode(strip_tags($post['title']['rendered'] ?? '')), 195),
                    'resume' => Str::limit(html_entity_decode(strip_tags($post['excerpt']['rendered'] ?? $texte)), 390),
                    'contenu' => $contenu,
                    'image' => $post['_embedded']['wp:featuredmedia'][0]['source_url'] ?? null,
                    'categorie' => match (true) {
                        $categories->contains(fn ($c) => str_contains($c, 'evenement')) => 'evenements',
                        $categories->contains(fn ($c) => str_contains($c, 'success')) => 'vie-du-campus',
                        default => 'conseils',
                    },
                    'temps_lecture' => max(1, (int) round(str_word_count($texte) / 200)),
                    'publie_le' => substr($post['date'] ?? now()->toDateString(), 0, 10),
                    'url_source' => $post['link'] ?? null,
                ]);
                $importes++;
            }
        }

        $this->info("$importes article(s) importé(s) depuis $site.");

        return self::SUCCESS;
    }
}
