<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate {--output=sitemap.xml : Output file path}';

    protected $description = 'Generate a sitemap.xml file from published pages.';

    public function handle(): int
    {
        $output = (string) $this->option('output');
        $path = str_starts_with($output, '/') ? $output : public_path($output);

        $pages = Page::query()
            ->where('status', Page::STATUS_PUBLISHED)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->get();

        $base = rtrim(config('app.url'), '/');

        $urls = $pages->map(function (Page $page) use ($base): array {
            $loc = $page->is_home ? $base : "{$base}/{$page->slug}";

            return [
                'loc' => $loc,
                'lastmod' => $page->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => $page->is_home ? '1.0' : '0.8',
            ];
        })->all();

        $xml = $this->buildXml($urls);

        file_put_contents($path, $xml);

        $this->info("Sitemap written to {$path} ({$pages->count()} urls).");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, string>>  $urls
     */
    protected function buildXml(array $urls): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $u) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>';
            if (! empty($u['lastmod'])) {
                $lines[] = '    <lastmod>'.$u['lastmod'].'</lastmod>';
            }
            $lines[] = '    <changefreq>'.$u['changefreq'].'</changefreq>';
            $lines[] = '    <priority>'.$u['priority'].'</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }
}
