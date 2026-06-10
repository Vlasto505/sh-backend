<?php

use App\Models\Article;
use App\Models\User;

function editor(): User
{
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u;
}

it('lets an editor create a published article with an auto slug', function () {
    $this->actingAs(editor())->post('/content', [
        'title' => 'Otvárame jarnú výzvu', 'excerpt' => 'Info', 'body' => 'Telo článku.', 'is_published' => true,
    ])->assertSessionHasNoErrors();

    $article = Article::first();
    expect($article->slug)->toBe('otvarame-jarnu-vyzvu')
        ->and($article->is_published)->toBeTrue()
        ->and($article->published_at)->not->toBeNull();
});

it('lets an editor update and delete an article by id', function () {
    $author = editor();
    $article = Article::create(['slug' => 'povodny', 'title' => 'Pôvodný', 'is_published' => true, 'published_at' => now(), 'author_id' => $author->id]);

    $this->actingAs($author)->put("/content/{$article->id}", [
        'title' => 'Upravený titulok', 'is_published' => true,
    ])->assertSessionHasNoErrors();
    expect($article->fresh()->title)->toBe('Upravený titulok');

    $this->actingAs($author)->delete("/content/{$article->id}")->assertSessionHasNoErrors();
    expect(Article::find($article->id))->toBeNull();
});

it('forbids a student from managing content', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $this->actingAs($student)->get('/content')->assertForbidden();
    $this->actingAs($student)->post('/content', ['title' => 'X', 'is_published' => false])->assertForbidden();
});

it('shows only published articles on the public news page', function () {
    $author = editor();
    Article::create(['slug' => 'pub', 'title' => 'Publikovaný', 'is_published' => true, 'published_at' => now(), 'author_id' => $author->id]);
    Article::create(['slug' => 'draft', 'title' => 'Koncept', 'is_published' => false, 'author_id' => $author->id]);

    $this->get('/news')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Public/News')
        ->where('articles.data', fn ($data) => collect($data)->pluck('slug')->contains('pub')
            && ! collect($data)->pluck('slug')->contains('draft'))
    );
});

it('returns 404 for an unpublished article detail', function () {
    $author = editor();
    Article::create(['slug' => 'hidden', 'title' => 'Skryté', 'is_published' => false, 'author_id' => $author->id]);

    $this->get('/news/hidden')->assertNotFound();
});

it('includes published articles in the sitemap', function () {
    $author = editor();
    Article::create(['slug' => 'v-sitemape', 'title' => 'X', 'is_published' => true, 'published_at' => now(), 'author_id' => $author->id]);

    $response = $this->get('/sitemap.xml');
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/xml');
    expect($response->getContent())->toContain('/news/v-sitemape');
});
