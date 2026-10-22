<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\FaqQuestion;
use App\Models\FcDomaine;
use App\Models\FcStage;
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

    /** Catalogue de formation continue : informations de l'organisme, domaines et résumé de chaque stage. */
    public function formationContinue()
    {
        $config = config('expernet.formation_continue');
        $colonnes = ['id', 'fc_domaine_id', 'slug', 'intitule', 'sous_theme', 'duree', 'tarifs', 'prochaine_session', 'ordre'];

        return response()->json([
            'chiffres' => $config['chiffres'],
            'site' => $config['site'],
            'contact' => $config['contact'],
            'liens' => $config['liens'],
            'locaux' => $config['locaux'],
            'formats' => $config['formats'],
            'accessibilite' => $config['accessibilite'],
            'domaines' => FcDomaine::with(['stages' => fn ($q) => $q->select($colonnes)])->orderBy('ordre')->get(['id', 'slug', 'libelle', 'icone']),
        ]);
    }

    /** Fiche complète d'un stage ; les modalités standard s'appliquent quand la fiche n'en précise pas. */
    public function stageFormationContinue(FcStage $stage)
    {
        $modalites = config('expernet.formation_continue.modalites');
        $stage->load('domaine:id,slug,libelle,icone');

        $similaires = FcStage::where('fc_domaine_id', $stage->fc_domaine_id)->whereKeyNot($stage->id)
            ->orderByRaw('prochaine_session is null')->orderBy('prochaine_session')->orderBy('ordre')->limit(3)
            ->get(['slug', 'intitule', 'duree', 'tarifs', 'prochaine_session']);

        return response()->json([
            'stage' => array_merge($stage->toArray(), [
                'moyens' => $stage->moyens ?: $modalites['moyens'],
                'methodes' => $stage->methodes ?: $modalites['methodes'],
                'evaluation' => $stage->evaluation ?: $modalites['evaluation'],
            ]),
            'similaires' => $similaires,
            'contact' => config('expernet.formation_continue.contact'),
            'liens' => config('expernet.formation_continue.liens'),
        ]);
    }
}
