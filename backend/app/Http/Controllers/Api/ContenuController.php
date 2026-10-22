<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\FaqQuestion;
use App\Models\FcDomaine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Contenus publics repris de l'ancien site : FAQ, blog, formation continue. */
class ContenuController extends Controller
{
    public function faq()
    {
        return response()->json(FaqQuestion::where('publiee', true)->orderBy('ordre')->get(['id', 'question', 'reponse', 'theme']));
    }

    public function articles(Request $request)
    {
        $filtres = $request->validate(['categorie' => ['nullable', Rule::in(array_keys(Article::CATEGORIES))]]);

        $articles = Article::query()
            ->when($filtres['categorie'] ?? null, fn ($q, $c) => $q->where('categorie', $c))
            ->orderByDesc('publie_le')
            ->paginate(12, ['id', 'slug', 'titre', 'resume', 'image', 'categorie', 'temps_lecture', 'publie_le']);

        return response()->json($articles->toArray() + ['categories' => Article::CATEGORIES]);
    }

    public function article(Article $article)
    {
        $suggestions = Article::whereKeyNot($article->id)->where('categorie', $article->categorie)
            ->orderByDesc('publie_le')->limit(3)->get(['slug', 'titre', 'image', 'publie_le']);

        return response()->json(['article' => $article, 'suggestions' => $suggestions, 'categorie_libelle' => Article::CATEGORIES[$article->categorie] ?? $article->categorie]);
    }

    public function formationContinue()
    {
        return response()->json([
            'chiffres' => config('expernet.formation_continue.chiffres'),
            'site' => config('expernet.formation_continue.site'),
            'domaines' => FcDomaine::with('stages:id,fc_domaine_id,intitule')->orderBy('ordre')->get(['id', 'slug', 'libelle', 'icone']),
        ]);
    }
}
