<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $pages = Page::query()
            ->where('status', Page::STATUS_PUBLISHED)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->get();

        $base = rtrim(config('app.url'), '/');

        $content = view('sitemap.xml', [
            'pages' => $pages,
            'base' => $base,
        ])->render();

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
