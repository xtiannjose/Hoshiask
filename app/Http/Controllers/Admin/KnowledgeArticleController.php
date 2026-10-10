<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KnowledgeArticleCategory;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KnowledgeArticleController extends Controller
{
    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = KnowledgeArticle::query()->with('createdBy:id,name')->orderByDesc('created_at');

        if (! $user->canManageTickets()) {
            $query->published();
        }

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('category') && $request->string('category')->toString() !== 'all') {
            $query->where('category', $request->string('category')->toString());
        }

        $articles = $query->paginate(20)->withQueryString();
        $categories = KnowledgeArticleCategory::cases();

        return view('admin.knowledge-base.index', compact('articles', 'categories'));
    }

    public function show(Request $request, KnowledgeArticle $knowledgeArticle)
    {
        $user = $request->user();
        if (! $knowledgeArticle->is_published && ! $user->canManageTickets()) {
            abort(404);
        }

        return view('admin.knowledge-base.show', compact('knowledgeArticle'));
    }

    public function create(Request $request)
    {
        $this->authorizeManage($request);

        return view('admin.knowledge-base.create', ['categories' => KnowledgeArticleCategory::cases()]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);

        $validated = $this->validatedArticleData($request);

        $article = KnowledgeArticle::create([
            'created_by_user_id' => (int) $request->user()->id,
            'category' => $validated['category'],
            'title' => $validated['title'],
            'slug' => KnowledgeArticle::generateAvailableSlug($validated['title']),
            'body' => $validated['body'],
            'is_published' => $validated['is_published'],
            'published_at' => $validated['is_published'] ? now() : null,
        ]);

        $this->systemLogs->record(
            'knowledge_article.created',
            'Created a knowledge base article.',
            [
                'category' => 'knowledge_base',
                'target_type' => KnowledgeArticle::class,
                'target_id' => $article->id,
                'metadata' => ['title' => $article->title, 'is_published' => $article->is_published],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.knowledge-base.index')->with('success', 'Article created.');
    }

    public function edit(Request $request, KnowledgeArticle $knowledgeArticle)
    {
        $this->authorizeManage($request);

        return view('admin.knowledge-base.edit', ['article' => $knowledgeArticle, 'categories' => KnowledgeArticleCategory::cases()]);
    }

    public function update(Request $request, KnowledgeArticle $knowledgeArticle)
    {
        $this->authorizeManage($request);

        $validated = $this->validatedArticleData($request);
        $wasPublished = $knowledgeArticle->is_published;

        $knowledgeArticle->update([
            'category' => $validated['category'],
            'title' => $validated['title'],
            'slug' => KnowledgeArticle::generateAvailableSlug($validated['title'], $knowledgeArticle->id),
            'body' => $validated['body'],
            'is_published' => $validated['is_published'],
            'published_at' => $validated['is_published'] && ! $wasPublished ? now() : $knowledgeArticle->published_at,
        ]);

        $this->systemLogs->record(
            'knowledge_article.updated',
            'Updated a knowledge base article.',
            [
                'category' => 'knowledge_base',
                'target_type' => KnowledgeArticle::class,
                'target_id' => $knowledgeArticle->id,
                'metadata' => ['title' => $knowledgeArticle->title, 'is_published' => $knowledgeArticle->is_published],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.knowledge-base.index')->with('success', 'Article updated.');
    }

    public function destroy(Request $request, KnowledgeArticle $knowledgeArticle)
    {
        $this->authorizeManage($request);

        $title = $knowledgeArticle->title;
        $id = $knowledgeArticle->id;
        $knowledgeArticle->delete();

        $this->systemLogs->record(
            'knowledge_article.deleted',
            'Deleted a knowledge base article.',
            [
                'category' => 'knowledge_base',
                'target_type' => KnowledgeArticle::class,
                'target_id' => $id,
                'metadata' => ['title' => $title],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.knowledge-base.index')->with('success', 'Article deleted.');
    }

    private function validatedArticleData(Request $request): array
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(KnowledgeArticleCategory::values())],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $validated['is_published'] = $request->boolean('is_published');

        return $validated;
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->canManageTickets()) {
            abort(403);
        }
    }
}
