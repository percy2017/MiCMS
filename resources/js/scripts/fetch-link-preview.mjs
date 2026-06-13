#!/usr/bin/env node
/**
 * fetch-link-preview.mjs
 *
 * Lee una URL desde argv[2] (o stdin), navega con Playwright headless
 * y devuelve un JSON con los Open Graph / Twitter Card / meta tags.
 *
 * Para plataformas que NO exponen OG de forma inmediata (YouTube, TikTok),
 * construye el preview determinísticamente a partir del patrón de URL.
 *
 * Salida (stdout): JSON con { url, final_url, title, description, image,
 *                                  image_width, image_height, site_name,
 *                                  favicon, error }
 *
 * Uso:
 *   node fetch-link-preview.mjs "https://example.com"
 *   echo "https://example.com" | node fetch-link-preview.mjs
 */

import { chromium } from 'playwright';

const input = process.argv[2] || (await new Promise((resolve) => {
    let buf = '';
    process.stdin.setEncoding('utf8');
    process.stdin.on('data', (c) => { buf += c; });
    process.stdin.on('end', () => resolve(buf.trim()));
}));

const url = (input || '').trim();

function bail(error, overrides = {}) {
    const out = {
        url,
        final_url: url,
        title: null,
        description: null,
        image: null,
        image_width: null,
        image_height: null,
        site_name: null,
        favicon: null,
        error,
        ...overrides,
    };
    process.stdout.write(JSON.stringify(out));
    process.exit(0);
}

if (!/^https?:\/\//i.test(url)) {
    bail('invalid_protocol');
}

const u = (() => {
    try { return new URL(url); } catch { return null; }
})();
const host = u?.hostname.toLowerCase() ?? '';

/**
 * YouTube no expone OG tags ni siquiera después de networkidle.
 * Construimos el preview determinísticamente desde el videoId.
 * Devuelve null si no es una URL de YouTube con videoId.
 */
function buildYouTubePreview(targetUrl) {
    let videoId = null;

    if (host.includes('youtube.com')) {
        if (targetUrl.pathname === '/watch') {
            videoId = targetUrl.searchParams.get('v');
        } else {
            const shortsMatch = targetUrl.pathname.match(/^\/shorts\/([\w-]+)/);
            const embedMatch = targetUrl.pathname.match(/^\/embed\/([\w-]+)/);
            if (shortsMatch) videoId = shortsMatch[1];
            else if (embedMatch) videoId = embedMatch[1];
        }
    } else if (host === 'youtu.be') {
        videoId = targetUrl.pathname.replace(/^\/+/, '').split('/')[0] || null;
    }

    if (!videoId || !/^[\w-]{6,20}$/.test(videoId)) {
        return null;
    }

    return {
        url: targetUrl.toString(),
        final_url: targetUrl.toString(),
        title: 'YouTube',
        description: 'Video de YouTube',
        image: `https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`,
        image_width: 480,
        image_height: 360,
        site_name: 'YouTube',
        favicon: 'https://www.youtube.com/s/desktop/0ecb6dad/img/favicon_32x32.png',
        error: null,
    };
}

/**
 * TikTok es un SPA pesado que solo expone OG tags después de que JS
 * se hidrata. Para URLs canónicas (`/@user/video/{id}` o `/t/{shortId}`)
 * construimos un preview con título y link al video, dejando que el
 * frontend aplique open-graph al hacer click.
 * Para `vt.tiktok.com/{shortId}` (links cortos) seguimos necesitando
 * Chromium para resolver la URL canónica.
 *
 * Devuelve `null` si la URL no es TikTok reconocible, o
 * `needsResolve: true` si es un short link que necesita seguir.
 */
function buildTikTokPreview(targetUrl) {
    if (!host.endsWith('tiktok.com')) {
        return null;
    }

    const tiktokFavicon = 'https://www.tiktok.com/favicon.ico';

    // Short link (vt.tiktok.com): dejar que Playwright resuelva el redirect
    if (host === 'vt.tiktok.com') {
        return { needsResolve: true, favicon: tiktokFavicon };
    }

    // /@user/video/{id}
    const videoMatch = targetUrl.pathname.match(/^\/@([^/]+)\/video\/(\d+)/);
    if (videoMatch) {
        return {
            url: targetUrl.toString(),
            final_url: targetUrl.toString(),
            title: `TikTok · @${videoMatch[1]}`,
            description: `Video de ${videoMatch[1]} en TikTok`,
            image: null,
            site_name: 'TikTok',
            favicon: tiktokFavicon,
            error: null,
        };
    }

    // /t/{shortId} (legacy share link)
    const shortMatch = targetUrl.pathname.match(/^\/t\/([\w-]+)/);
    if (shortMatch) {
        return {
            url: targetUrl.toString(),
            final_url: targetUrl.toString(),
            title: 'TikTok',
            description: 'Video de TikTok',
            image: null,
            site_name: 'TikTok',
            favicon: tiktokFavicon,
            error: null,
        };
    }

    return null;
}

let browser;
try {
    // Fast path: YouTube. No abrimos Chromium para no esperar 10s.
    if (u && (host.includes('youtube.com') || host === 'youtu.be')) {
        const yt = buildYouTubePreview(u);
        if (yt) {
            process.stdout.write(JSON.stringify(yt));
            process.exit(0);
        }
    }

    // Fast path: TikTok canónico. No abrimos Chromium.
    let tiktokCanonical = null;
    if (u && host.endsWith('tiktok.com')) {
        const tt = buildTikTokPreview(u);
        if (tt && !tt.needsResolve) {
            process.stdout.write(JSON.stringify(tt));
            process.exit(0);
        }
        tiktokCanonical = tt;
    }

    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        javaScriptEnabled: true,
        acceptDownloads: false, // Evitar que bit.ly etc triggereen downloads
    });
    const page = await context.newPage();

    // Si el server intenta forzar download, abortar y usar fallback
    let forcedDownload = false;
    page.on('download', async (download) => {
        forcedDownload = true;
        try { await download.cancel(); } catch {}
    });

    // Detectar responses que NO son HTML (descargas binarias)
    let nonHtmlResponse = false;
    page.on('response', async (response) => {
        const ct = response.headers()['content-type'] || '';
        if (response.status() < 400 && !ct.includes('text/html') && !ct.includes('application/xhtml') && !ct.includes('application/json')) {
            nonHtmlResponse = true;
        }
    });

    try {
        // 'commit' en vez de 'load' para short links que sirven archivos directos.
        // commit espera solo la primera respuesta del servidor.
        const response = await page.goto(url, { waitUntil: 'commit', timeout: 10000 });

        // Si el response no es HTML, abortar inmediatamente
        if (response) {
            const ct = response.headers()['content-type'] || '';
            if (!ct.includes('text/html') && !ct.includes('application/xhtml')) {
                await browser.close();
                bail('non_html_response: ' + (ct || 'unknown'));
            }
        }

        if (forcedDownload || nonHtmlResponse) {
            await browser.close();
            bail('download_blocked');
        }

        // Esperar un poco más para que el JS inyecte OG tags
        await page.waitForLoadState('domcontentloaded', { timeout: 5000 }).catch(() => {});
    } catch (e) {
        await browser.close();
        if (forcedDownload || nonHtmlResponse) {
            bail('download_blocked');
        }
        bail('navigation_failed: ' + (e?.message || 'unknown'));
    }

    // Para short links de TikTok: si resolvieron a canónico, devolver
    // preview determinístico (sin confiar en OG de la página hidratada).
    if (tiktokCanonical?.needsResolve) {
        await browser.close();
        const finalUrl = page.url();
        try {
            const finalHost = new URL(finalUrl).hostname.toLowerCase();
            if (finalHost.endsWith('tiktok.com')) {
                const resolved = buildTikTokPreview(new URL(finalUrl));
                if (resolved && !resolved.needsResolve) {
                    process.stdout.write(JSON.stringify(resolved));
                    process.exit(0);
                }
            }
        } catch {}
        // No se pudo resolver a un patrón conocido: caer al flujo normal.
    }

    const data = await page.evaluate(() => {
        const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.getAttribute('content') || null;
        const og = (p) => document.querySelector(`meta[property="og:${p}"]`)?.getAttribute('content') || null;
        const tw = (n) => document.querySelector(`meta[name="twitter:${n}"]`)?.getAttribute('content') || null;

        const faviconLink = document.querySelector('link[rel="icon"]')?.getAttribute('href')
            || document.querySelector('link[rel="shortcut icon"]')?.getAttribute('href')
            || document.querySelector('link[rel="apple-touch-icon"]')?.getAttribute('href')
            || null;

        const ogTitle = og('title') || tw('title');
        const ogDescription = og('description') || tw('description') || meta('description');
        const ogImage = og('image') || tw('image');
        const rawTitle = ogTitle || document.title || null;
        const cleanedTitle = rawTitle && rawTitle.trim() === location.hostname
            ? null
            : rawTitle;

        return {
            title: cleanedTitle ? String(cleanedTitle).slice(0, 300) : null,
            description: ogDescription ? String(ogDescription).slice(0, 600) : null,
            image: ogImage || null,
            site_name: og('site_name') || null,
            favicon: faviconLink || null,
            final_url: location.href,
        };
    });

    let width = null;
    let height = null;
    if (data.image) {
        try {
            const dims = await page.evaluate(async (imgUrl) => {
                return await new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => resolve({ w: img.naturalWidth, h: img.naturalHeight });
                    img.onerror = () => resolve(null);
                    img.src = imgUrl;
                });
            }, data.image);
            if (dims) {
                width = dims.w;
                height = dims.h;
            }
        } catch {
            // ignore
        }
    }

    await browser.close();

    // Si no se extrajo nada útil (SPA sin OG), usar favicon de TikTok
    // como último recurso para que el card no quede vacío.
    let finalFavicon = data.favicon;
    if (!finalFavicon && host.endsWith('tiktok.com')) {
        finalFavicon = 'https://www.tiktok.com/favicon.ico';
    }
    let finalSiteName = data.site_name;
    if (!finalSiteName && host.endsWith('tiktok.com')) {
        finalSiteName = 'TikTok';
    }

    process.stdout.write(JSON.stringify({
        url,
        final_url: data.final_url,
        title: data.title,
        description: data.description,
        image: data.image,
        image_width: width,
        image_height: height,
        site_name: finalSiteName,
        favicon: finalFavicon,
        error: null,
    }));
} catch (e) {
    if (browser) {
        try { await browser.close(); } catch {}
    }
    bail('fetch_failed: ' + (e?.message || 'unknown'));
}
