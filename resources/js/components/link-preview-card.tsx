import { ExternalLink, Globe } from 'lucide-react';
import { useState } from 'react';
import type { LinkPreviewItem } from '@/types/link-preview';

type Props = {
    item: LinkPreviewItem;
};

function hostname(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

export function LinkPreviewCard({ item }: Props) {
    const href = item.final_url || item.url;
    const [imgFailed, setImgFailed] = useState(false);
    const [faviconFailed, setFaviconFailed] = useState(false);
    const showImage = Boolean(item.image) && !imgFailed;
    const showFavicon = Boolean(item.favicon) && !faviconFailed;
    const host = hostname(href);
    const displayTitle = item.title?.trim() || host;

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-1.5 block max-w-sm overflow-hidden rounded-md border border-border bg-background text-foreground transition hover:bg-muted/40"
        >
            {showImage && (
                <div className="relative bg-muted">
                    <img
                        src={item.image ?? ''}
                        alt=""
                        loading="lazy"
                        referrerPolicy="no-referrer"
                        onError={() => setImgFailed(true)}
                        className="block max-h-44 w-full object-cover"
                    />
                </div>
            )}
            <div className="space-y-0.5 p-2.5">
                <div className="flex items-center gap-1.5 text-[10px] uppercase tracking-wide text-muted-foreground">
                    {showFavicon ? (
                        <img
                            src={item.favicon ?? ''}
                            alt=""
                            loading="lazy"
                            referrerPolicy="no-referrer"
                            onError={() => setFaviconFailed(true)}
                            className="size-3 shrink-0"
                        />
                    ) : (
                        <Globe className="size-3 shrink-0" />
                    )}
                    <span className="truncate">{item.site_name?.trim() || host}</span>
                    <ExternalLink className="ml-auto size-3 shrink-0 opacity-60" />
                </div>
                <p className="line-clamp-2 text-xs font-semibold leading-tight">{displayTitle}</p>
                {item.description && (
                    <p className="line-clamp-2 text-[11px] leading-snug text-muted-foreground">
                        {item.description}
                    </p>
                )}
            </div>
        </a>
    );
}
