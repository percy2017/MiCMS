import { LinkPreviewCard } from '@/components/link-preview-card';
import type { LinkPreviews, LinkPreviewItem } from '@/types/link-preview';

type Props = {
    content: string;
    linkPreviews?: LinkPreviews | null;
    mediaPreview?: LinkPreviewItem | null;
};

const URL_REGEX = /https?:\/\/[^\s<>"')]+/gi;

function autoLinkify(text: string): React.ReactNode[] {
    const parts: React.ReactNode[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;
    URL_REGEX.lastIndex = 0;
    let key = 0;

    while ((match = URL_REGEX.exec(text)) !== null) {
        if (match.index > lastIndex) {
            parts.push(text.slice(lastIndex, match.index));
        }
        const raw = match[0];
        const clean = raw.replace(/[.,;:!?)]+$/, '');
        const trailing = raw.slice(clean.length);
        parts.push(
            <a
                key={`l-${key++}`}
                href={clean}
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
        const clean = m[0].replace(/[.,;:!?)]+$/, '');
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
