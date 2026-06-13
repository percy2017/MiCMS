import { LinkPreviewCard } from '@/components/link-preview-card';
import type { LinkPreviews, LinkPreviewItem } from '@/types/link-preview';

type Props = {
    content: string;
    linkPreviews?: LinkPreviews | null;
    mediaPreview?: LinkPreviewItem | null;
};

// Match: http(s)://...   OR   (www.)?domain.tld[/path]
const URL_REGEX = /(?:https?:\/\/[^\s<>")']+|(?:www\.)?[a-zA-Z0-9][a-zA-Z0-9\-]{0,62}(?:\.[a-zA-Z0-9\-]{1,62}){1,}(?:\/[^\s<>")']*)?)/gi;

const TLD_PATTERN = /\.(com|net|org|io|co|bo|es|mx|ar|cl|pe|ve|uy|py|ec|cr|gt|hn|ni|pa|cu|do|pr|us|uk|de|fr|it|pt|br|info|biz|dev|app|ai|me|tv|cc|tk|ml|ga|cf|gq|xyz|top|site|online|store|tech|news|wiki|gov|edu|mil|int)(?:\b|\/|$)/i;

function isValidUrl(token: string): boolean {
    if (token.includes('@')) return false;
    if (!token.includes('.')) return false;
    return TLD_PATTERN.test(token.toLowerCase());
}

function normalizeUrl(url: string): string {
    return /^https?:\/\//i.test(url) ? url : `https://${url}`;
}

function autoLinkify(text: string): React.ReactNode[] {
    const parts: React.ReactNode[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;
    URL_REGEX.lastIndex = 0;
    let key = 0;

    while ((match = URL_REGEX.exec(text)) !== null) {
        const raw = match[0];
        if (!isValidUrl(raw)) continue;

        if (match.index > lastIndex) {
            parts.push(text.slice(lastIndex, match.index));
        }
        const clean = raw.replace(/[.,;:!?)]+$/, '');
        const trailing = raw.slice(clean.length);
        const href = normalizeUrl(clean);
        parts.push(
            <a
                key={`l-${key++}`}
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                className="text-blue-600 underline underline-offset-2 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
            >
                {clean}
            </a>,
        );
        if (trailing) {
            parts.push(trailing);
        }
        lastIndex = match.index + raw.length;
    }

    if (lastIndex < text.length) {
        parts.push(text.slice(lastIndex));
    }

    return parts;
}

function matchItemToUrl(item: LinkPreviewItem, url: string): boolean {
    const itemUrl = (item.final_url || item.url || '').replace(/[.,;:!?)]+$/, '');
    if (itemUrl === url) {
        return true;
    }
    return itemUrl.startsWith(url) || url.startsWith(itemUrl);
}

export function MessageBody({ content, linkPreviews, mediaPreview }: Props) {
    const urlsInContent: string[] = [];
    URL_REGEX.lastIndex = 0;
    let m: RegExpExecArray | null;
    const seen = new Set<string>();
    while ((m = URL_REGEX.exec(content)) !== null) {
        const raw = m[0];
        if (!isValidUrl(raw)) continue;
        const clean = raw.replace(/[.,;:!?)]+$/, '');
        if (!seen.has(clean)) {
            seen.add(clean);
            urlsInContent.push(clean);
        }
    }

    const items: LinkPreviewItem[] = [];
    if (linkPreviews?.items) {
        items.push(...(linkPreviews.items.filter((it): it is LinkPreviewItem => {
            if (!it || it.error) {
                return false;
            }
            return urlsInContent.some((u) => matchItemToUrl(it, u));
        })));
    } else if (mediaPreview && !mediaPreview.error) {
        items.push(mediaPreview);
    }

    return (
        <div>
            <div className="whitespace-pre-wrap break-words">{autoLinkify(content)}</div>
            {items.length > 0 && (
                <div className="flex flex-col gap-1.5">
                    {items.map((item) => (
                        <LinkPreviewCard key={item.url} item={item} />
                    ))}
                </div>
            )}
        </div>
    );
}
