import { Head, usePage } from '@inertiajs/react';
import { Render } from '@puckeditor/core';
import type { Data } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import Footer from '@/paginas/components/footer';
import Header from '@/paginas/components/header';
import { MenuProvider, type MenusByLocation } from '@/paginas/menu-context';
import { puckConfig } from '@/paginas/puck-config';

type PageProps = {
    page: {
        id: number;
        title: string;
        slug: string;
        puck_data: Data;
        excerpt?: string;
        og_image?: string;
    };
    menus: MenusByLocation;
    site: {
        name: string;
        tagline: string;
        logo_url: string | null;
    };
    enabledPackages?: unknown[];
};

function buildExcerpt(puckData: Data): string {
    const content = (puckData?.content ?? []) as Array<{
        type?: string;
        props?: Record<string, unknown>;
    }>;

    for (const block of content) {
        if (!block || typeof block !== 'object') {
            continue;
        }
        const props = block.props ?? {};
        const text = props.content ?? props.children;
        if (typeof text === 'string') {
            return text
                .replace(/<[^>]+>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .slice(0, 160);
        }
    }

    return '';
}

export default function PaginaShow({ page, menus, site, enabledPackages }: PageProps) {
    const { props: pageProps } = usePage<{ site: PageProps['site'] }>();
    const resolvedSite = site ?? pageProps?.site;
    const excerpt = page.excerpt ?? buildExcerpt(page.puck_data);
    const canonical = typeof window !== 'undefined'
        ? `${window.location.origin}/${page.slug === 'home' ? '' : page.slug}`
        : '';
    const ogImage = page.og_image ?? resolvedSite?.logo_url ?? '';

    return (
        <MenuProvider menus={menus}>
            <Head title={`${page.title} | ${resolvedSite?.name ?? 'CMS'}`}>
                <meta name="description" content={excerpt || `${page.title} - ${resolvedSite?.tagline ?? ''}`} />
                <link rel="canonical" href={canonical} />

                <meta property="og:title" content={page.title} />
                <meta property="og:description" content={excerpt} />
                <meta property="og:type" content="website" />
                <meta property="og:url" content={canonical} />
                {ogImage ? <meta property="og:image" content={ogImage} /> : null}
                <meta property="og:site_name" content={resolvedSite?.name ?? 'CMS'} />

                <meta name="twitter:card" content={ogImage ? 'summary_large_image' : 'summary'} />
                <meta name="twitter:title" content={page.title} />
                <meta name="twitter:description" content={excerpt} />
                {ogImage ? <meta name="twitter:image" content={ogImage} /> : null}
            </Head>

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <Header />
                <main className="flex-1">
                    <div className="mx-auto w-full max-w-4xl px-4 py-8 sm:py-12">
                        <Render config={puckConfig} data={page.puck_data} />
                    </div>
                </main>
                <Footer />
            </div>
        </MenuProvider>
    );
}
