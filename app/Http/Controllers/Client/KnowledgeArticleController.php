<?php

namespace App\Http\Controllers\Client;

use App\Enums\KnowledgeArticleCategory;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use Illuminate\Http\Request;

class KnowledgeArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = KnowledgeArticle::query()->published()->orderByDesc('published_at');

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('category') && $request->string('category')->toString() !== 'all') {
            $query->where('category', $request->string('category')->toString());
        }

        $articles = $query->paginate(20)->withQueryString();
        $categories = KnowledgeArticleCategory::cases();

        return view('client.knowledge-base.index', compact('articles', 'categories'));
    }

    public function show(KnowledgeArticle $knowledgeArticle)
    {
        abort_unless($knowledgeArticle->is_published, 404);

        return view('client.knowledge-base.show', compact('knowledgeArticle'));
    }
}
