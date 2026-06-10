<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicContentController extends Controller
{
    public function newsIndex(): Response
    {
        $articles = Article::published()
            ->orderByDesc('published_at')
            ->paginate(9)
            ->through(fn (Article $a) => [
                'title'        => $a->title,
                'slug'         => $a->slug,
                'excerpt'      => $a->excerpt,
                'cover_image_url' => $a->cover_image_url,
                'published_at' => $a->published_at,
            ]);

        return Inertia::render('Public/News', [
            'articles' => $articles,
        ]);
    }

    public function newsShow(Article $article): Response
    {
        abort_unless($article->is_published && $article->published_at, 404);

        return Inertia::render('Public/Article', [
            'article' => [
                'title'            => $article->title,
                'excerpt'          => $article->excerpt,
                'body'             => $article->body,
                'cover_image_url'  => $article->cover_image_url,
                'published_at'     => $article->published_at,
                'meta_title'       => $article->meta_title ?: $article->title,
                'meta_description' => $article->meta_description ?: $article->excerpt,
            ],
        ]);
    }

    /**
     * Dynamic sitemap.xml for SEO (spec 6.1).
     */
    public function sitemap(): HttpResponse
    {
        $staticPaths = ['/', '/program-a', '/program-b', '/partners', '/about', '/contact', '/news', '/privacy'];

        $urls = collect($staticPaths)->map(fn ($p) => [
            'loc'     => url($p),
            'lastmod' => now()->toDateString(),
        ]);

        foreach (Article::published()->get(['slug', 'updated_at']) as $a) {
            $urls->push([
                'loc'     => url("/news/{$a->slug}"),
                'lastmod' => $a->updated_at?->toDateString(),
            ]);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= "  <url><loc>{$u['loc']}</loc><lastmod>{$u['lastmod']}</lastmod></url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
