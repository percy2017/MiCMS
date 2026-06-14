<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class LinkPreviewService
{
    private const CACHE_TTL = 60 * 60 * 24 * 7;

    private const CACHE_TTL_ERROR = 60 * 60;

    private const SCRIPT_TIMEOUT = 180;

    private const SKIP_DOMAINS = [
        'chat.whatsapp.com',
        'web.whatsapp.com',
        'wa.me',
        't.me',
        'telegram.me',
    ];

    private const MAX_URLS_PER_MESSAGE = 5;

    /**
     * Extrae todas las URLs (con o sin esquema) de un texto.
     *
     * Detecta:
     *   - https://example.com
     *   - http://example.com
     *   - www.example.com
     *   - example.com
     *   - sub.example.com/path?x=1
     *
     * Ignora emails, paths sin TLD y palabras sueltas.
     *
     * @return array<int, string>
     */
    public function extractUrls(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $unique = [];

        // 1. URLs con esquema explícito (https?://...)
        preg_match_all('#https?://[^\s<>"\'\\)\]]+#i', $text, $m1);
        foreach ($m1[0] as $u) {
            $clean = rtrim($u, '.,;:!?)');
            $key = md5($clean);
            $unique[$key] = $clean;
            if (count($unique) >= self::MAX_URLS_PER_MESSAGE) {
                return array_values($unique);
            }
        }

        // 2. URLs scheme-less: www.foo.com o foo.tld (con path/query opcional)
        // - empieza con "www." o es preceded by whitespace/start/(["'])
        // - debe tener un TLD de 2+ letras
        // - puede tener path /query/fragment
        preg_match_all(
            '#(?<![\w@/.])((?:www\.)?[a-zA-Z0-9][a-zA-Z0-9\-]{0,62}(?:\.[a-zA-Z0-9\-]{1,62}){1,}(?:/[^\s<>"\x27\\)\]]*)?)#i',
            $text,
            $m2,
        );

        $tldPattern = '/\.(com|net|org|io|co|bo|es|mx|ar|cl|pe|ve|uy|py|ec|pe|cr|gt|hn|ni|pa|cu|do|pr|us|uk|de|fr|it|pt|br|info|biz|dev|app|ai|me|tv|cc|tk|ml|ga|cf|gq|xyz|top|site|online|store|tech|news|wiki|gov|edu|mil|int)(?:\b|\/|$)/i';

        foreach ($m2[1] as $u) {
            $lower = strtolower($u);

            // Ignorar emails
            if (str_contains($lower, '@')) {
                continue;
            }

            // Debe tener un TLD conocido
            if (! preg_match($tldPattern, $lower)) {
                continue;
            }

            // Ignorar paths sueltos sin punto (no son URLs)
            if (! str_contains($u, '.')) {
                continue;
            }

            $clean = rtrim($u, '.,;:!?)');
            $key = md5($clean);
            if (! isset($unique[$key])) {
                $unique[$key] = $clean;
            }
            if (count($unique) >= self::MAX_URLS_PER_MESSAGE) {
                break;
            }
        }

        // Normalizar scheme-less a https://
        return array_values(array_map(
            fn (string $u): string => preg_match('#^https?://#i', $u) ? $u : 'https://'.$u,
            $unique,
        ));
    }

    /**
     * Fetchea metadatos OG para una lista de URLs. Las URLs ya cacheadas
     * no se vuelven a procesar.
     *
     * @param  array<int, string>  $urls
     * @return array<int, array<string, mixed>>
     */
    public function fetchMany(array $urls): array
    {
        $items = [];
        foreach ($urls as $url) {
            $items[] = $this->fetchOne($url);
        }

        return $items;
    }

    /**
     * Fetchea metadatos OG para una URL. Usa cache de Laravel (TTL 7 días).
     *
     * @return array<string, mixed>
     */
    public function fetchOne(string $url): array
    {
        $url = trim($url);

        if (! $this->isValidUrl($url)) {
            return $this->emptyItem($url, 'invalid_url');
        }

        if ($this->isSkippedDomain($url)) {
            return $this->fallbackPreview($url, 'domain_skipped');
        }

        $cacheKey = 'link_preview:'.md5($url);

        try {
            $item = Cache::get($cacheKey);

            if (is_array($item)) {
                return $item;
            }

            $item = $this->resolveHostSafely($url)
                ? $this->runScript($url)
                : $this->emptyItem($url, 'ssrf_blocked');

            if ($this->isErrorItem($item)) {
                $item = $this->fallbackPreview($url, $item['error'] ?? null);
            }

            $ttl = $this->isErrorItem($item) ? self::CACHE_TTL_ERROR : self::CACHE_TTL;

            Cache::put($cacheKey, $item, $ttl);

            return $item;
        } catch (\Throwable $e) {
            Log::warning('LinkPreviewService::fetchOne failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackPreview($url, 'preview_unavailable');
        }
    }

    private function isErrorItem(array $item): bool
    {
        if (! empty($item['error'])) {
            return true;
        }

        return empty($item['title']) && empty($item['description']) && empty($item['image']);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyItem(string $url, ?string $error = null): array
    {
        return [
            'url' => $url,
            'final_url' => $url,
            'title' => null,
            'description' => null,
            'image' => null,
            'image_width' => null,
            'image_height' => null,
            'site_name' => null,
            'favicon' => null,
            'error' => $error,
        ];
    }

    private function isValidUrl(string $url): bool
    {
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return false;
        }

        return true;
    }

    /**
     * Resuelve el host y verifica que no apunte a una IP privada.
     * Previene SSRF (Server-Side Request Forgery).
     */
    private function resolveHostSafely(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->isPrivateIp($host);
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (! is_array($records) || $records === []) {
            return false;
        }

        foreach ($records as $r) {
            $ip = $r['ip'] ?? $r['ipv6'] ?? null;
            if (is_string($ip) && $this->isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPrivateIp(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    private function isSkippedDomain(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach (self::SKIP_DOMAINS as $skip) {
            if ($host === $skip || str_ends_with($host, '.'.$skip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function runScript(string $url): array
    {
        $script = base_path('resources/js/scripts/fetch-link-preview.mjs');

        if (! file_exists($script)) {
            return $this->emptyItem($url, 'script_missing');
        }

        $env = ['PLAYWRIGHT_BROWSERS_PATH' => env('PLAYWRIGHT_BROWSERS_PATH', '/usr/local/share/playwright-browsers')];

        $process = new Process(
            ['node', $script, $url],
            base_path(),
            $env,
            null,
            self::SCRIPT_TIMEOUT
        );

        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('LinkPreviewService script failed', [
                'url' => $url,
                'stderr' => $process->getErrorOutput(),
            ]);

            return $this->emptyItem($url, 'script_failed');
        }

        $stdout = trim($process->getOutput());
        $decoded = json_decode($stdout, true);

        if (! is_array($decoded)) {
            return $this->emptyItem($url, 'invalid_json');
        }

        return $decoded + [
            'url' => $url,
            'final_url' => $decoded['final_url'] ?? $url,
            'title' => $decoded['title'] ?? null,
            'description' => $decoded['description'] ?? null,
            'image' => $decoded['image'] ?? null,
            'image_width' => $decoded['image_width'] ?? null,
            'image_height' => $decoded['image_height'] ?? null,
            'site_name' => $decoded['site_name'] ?? null,
            'favicon' => $decoded['favicon'] ?? null,
            'error' => $decoded['error'] ?? null,
        ];
    }

    /**
     * Construye un preview mínimo a partir de la URL cuando el script falla.
     * Usa el dominio como título y favicon de Google.
     */
    private function fallbackPreview(string $url, ?string $error = null): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        return [
            'url' => $url,
            'final_url' => $url,
            'title' => $host ?: $url,
            'description' => null,
            'image' => null,
            'image_width' => null,
            'image_height' => null,
            'site_name' => $host ?: null,
            'favicon' => $host ? "https://www.google.com/s2/favicons?domain={$host}&sz=64" : null,
            'error' => $error,
        ];
    }
}
