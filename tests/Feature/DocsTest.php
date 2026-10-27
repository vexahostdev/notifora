<?php

beforeEach(fn () => $this->withoutVite());

it('serves the docs index and every section page publicly', function () {
    $this->get('/docs')->assertOk()->assertSee('Dokumentasi Teknis Notifora')->assertSee('Ctrl K');

    foreach (array_keys(config('docs.sections')) as $slug) {
        $this->get("/docs/$slug")->assertOk()->assertSee(config("docs.sections.$slug.title"));
    }
});

it('has an article for every navigation entry and no orphan articles', function () {
    foreach (config('docs.sections') as $slug => $section) {
        $html = $this->get("/docs/$slug")->getContent();
        preg_match_all('/<article id="([^"]+)" data-title="([^"]*)"/', $html, $m);

        $nav = array_column($section['articles'], 0);
        expect($m[1])->toEqualCanonicalizing($nav, "Anchor di /docs/$slug tidak sinkron dengan config/docs.php");

        foreach ($section['articles'] as [$id, $title]) {
            expect(html_entity_decode($m[2][array_search($id, $m[1])]))->toBe($title);
        }
    }
});

it('returns 404 for unknown docs pages', function () {
    $this->get('/docs/tidak-ada')->assertNotFound();
});

it('builds a full-text search index covering all articles', function () {
    $index = $this->getJson('/docs/search.json')->assertOk()->json();

    $expected = collect(config('docs.sections'))->sum(fn ($s) => count($s['articles']));
    expect($index)->toHaveCount($expected);
    foreach ($index as $row) {
        expect($row)->toHaveKeys(['title', 'section', 'url', 'text'])->and($row['text'])->not->toBe('');
    }

    $hit = collect($index)->first(fn ($r) => str_contains(mb_strtolower($r['text']), 'x-idempotency-key'));
    expect($hit['url'])->toContain('/docs/');
});

it('keeps documented facts in sync with the real configuration', function () {
    $billing = $this->get('/docs/billing-plan')->getContent();
    foreach (config('notifora.plans') as $p) {
        expect($billing)->toContain(number_format($p['price'], 0, ',', '.'));
    }
    expect($billing)->toContain((string) config('notifora.billing.grace_days'));

    $auth = $this->get('/docs/autentikasi')->getContent();
    foreach (config('notifora.plans') as $p) {
        expect($auth)->toContain(number_format($p['rpm'], 0, ',', '.'));
    }
});

it('does not leak template syntax or raw Blade into rendered docs', function () {
    foreach (array_keys(config('docs.sections')) as $slug) {
        $html = $this->get("/docs/$slug")->getContent();
        expect($html)->not->toContain('@verbatim')->and($html)->not->toContain('@endverbatim')->and($html)->not->toContain('<x-docs');
    }
    // contoh sintaks template tampil literal
    $this->get('/docs/workflow-template')->assertSee(e('{{ subscriber.first_name|default:"Pelanggan" }}'), false);
});

it('links docs from the landing page and shows the footer credit', function () {
    $this->get('/')->assertOk()->assertSee(url('/docs'), false)
        ->assertSee('© '.date('Y').' notifora. All rights reserved. Created by notifora');
    $this->get('/docs')->assertSee('© '.date('Y').' notifora. All rights reserved. Created by notifora');
});
