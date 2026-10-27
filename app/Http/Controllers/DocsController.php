<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

class DocsController extends Controller
{
    public function index()
    {
        return view('docs.index', ['sections' => config('docs.sections')]);
    }

    public function show(string $slug)
    {
        $sections = config('docs.sections');
        abort_unless(isset($sections[$slug]) && View::exists("docs.pages.$slug"), 404);

        $keys = array_keys($sections);
        $i = array_search($slug, $keys, true);

        return view('docs.show', [
            'slug' => $slug,
            'section' => $sections[$slug],
            'prev' => $i > 0 ? [$keys[$i - 1], $sections[$keys[$i - 1]]] : null,
            'next' => $i < count($keys) - 1 ? [$keys[$i + 1], $sections[$keys[$i + 1]]] : null,
        ]);
    }

    /** Indeks pencarian (judul + isi artikel) untuk Ctrl+K. Di-cache; di lokal selalu dibangun ulang. */
    public function search()
    {
        $build = fn () => $this->buildIndex();
        $index = app()->environment('local', 'development', 'testing') ? $build() : Cache::remember('docs.search.v1', 3600, $build);

        return response()->json($index)->header('Cache-Control', 'public, max-age=300');
    }

    private function buildIndex(): array
    {
        $out = [];
        foreach (config('docs.sections') as $slug => $section) {
            if (! View::exists("docs.pages.$slug")) {
                continue;
            }
            $html = view("docs.pages.$slug")->render();

            // Pecah per <article id="..." data-title="...">
            preg_match_all('/<article id="([^"]+)" data-title="([^"]*)".*?>(.*?)<\/article>/s', $html, $m, PREG_SET_ORDER);
            foreach ($m as [$_, $id, $title, $body]) {
                $body = preg_replace('/<h2.*?<\/h2>/s', '', $body); // judul sudah ada di field title
                $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($body))));
                $out[] = [
                    'title' => html_entity_decode($title),
                    'section' => $section['title'],
                    'url' => url("/docs/$slug").'#'.$id,
                    'text' => mb_substr($text, 0, 1200),
                ];
            }
        }

        return $out;
    }
}
