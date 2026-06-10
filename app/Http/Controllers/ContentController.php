<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsAudit;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    use LogsAudit;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('content.view'), 403);

        $articles = Article::with('author:id,name')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (Article $a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'slug'         => $a->slug,
                'is_published' => $a->is_published,
                'published_at' => $a->published_at,
                'author'       => $a->author?->name,
                'excerpt'      => $a->excerpt,
                'body'         => $a->body,
                'cover_image_url'  => $a->cover_image_url,
                'meta_title'       => $a->meta_title,
                'meta_description' => $a->meta_description,
            ]);

        return Inertia::render('Content/Index', [
            'articles' => $articles,
            'can' => [
                'create' => $request->user()->can('content.create'),
                'edit'   => $request->user()->can('content.edit'),
                'delete' => $request->user()->can('content.delete'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('content.create'), 403);

        $data = $this->validateArticle($request);

        $article = Article::create([
            ...$data,
            'slug'         => $this->uniqueSlug($data['title']),
            'author_id'    => $request->user()->id,
            'published_at' => $data['is_published'] ? now() : null,
        ]);

        $this->audit($request, 'article.created', Article::class, $article->id, ['title' => $article->title]);

        return back()->with('success', 'Článok bol vytvorený.');
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        abort_unless($request->user()->can('content.edit'), 403);

        $data = $this->validateArticle($request);

        // Stamp publish time the first time it goes live.
        $publishedAt = $article->published_at;
        if ($data['is_published'] && ! $publishedAt) {
            $publishedAt = now();
        }
        if (! $data['is_published']) {
            $publishedAt = null;
        }

        $article->update([...$data, 'published_at' => $publishedAt]);

        $this->audit($request, 'article.updated', Article::class, $article->id, ['title' => $article->title]);

        return back()->with('success', 'Článok bol upravený.');
    }

    public function destroy(Request $request, Article $article): RedirectResponse
    {
        abort_unless($request->user()->can('content.delete'), 403);

        $article->delete();
        $this->audit($request, 'article.deleted', Article::class, $article->id, ['title' => $article->title]);

        return back()->with('success', 'Článok bol zmazaný.');
    }

    private function validateArticle(Request $request): array
    {
        return $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'excerpt'          => ['nullable', 'string', 'max:500'],
            'body'             => ['nullable', 'string', 'max:50000'],
            'cover_image_url'  => ['nullable', 'url', 'max:500'],
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_published'     => ['required', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'clanok';
        $slug = $base;
        $i = 2;
        while (DB::table('articles')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
