#!/usr/bin/env node
/**
 * fetch-link-preview.mjs
 *
 * Lee una URL desde argv[2] (o stdin), navega con Playwright headless
 * y devuelve un JSON con los Open Graph / Twitter Card / meta tags.
 *
 * Para plataformas que NO exponen OG (YouTube), construye el preview
 * determinísticamente a partir del patrón de URL.
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

    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        javaScriptEnabled: true,
    });
    const page = await context.newPage();

    try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 10000 });
    } catch (e) {
        await browser.close();
        bail('navigation_failed: ' + (e?.message || 'unknown'));
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

    process.stdout.write(JSON.stringify({
        url,
        final_url: data.final_url,
        title: data.title,
        description: data.description,
        image: data.image,
        image_width: width,
        image_height: height,
        site_name: data.site_name,
        favicon: data.favicon,
        error: null,
    }));
} catch (e) {
    if (browser) {
        try { await browser.close(); } catch {}
    }
    bail('fetch_failed: ' + (e?.message || 'unknown'));
}
