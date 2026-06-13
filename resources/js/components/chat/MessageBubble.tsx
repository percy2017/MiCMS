import { useState } from 'react';
import { Copy, MapPin, MoreHorizontal, Phone, Smile, User } from 'lucide-react';
import { MessageBody } from '@/components/message-body';
import type { ChatMessage, Reaction } from '@/types/chat';
import type { LinkPreviewItem } from '@/types/link-preview';
import { formatBytes, formatChatDate, isPlaceholder } from '@/lib/chat-utils';
import { cn } from '@/lib/utils';
import { ImageBubble } from './ImageBubble';
import { VideoBubble } from './VideoBubble';
import { AudioBubble } from './AudioBubble';
import { FileBubble } from './FileBubble';

const REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

type MessageBubbleProps = {
    m: ChatMessage;
    isMine: boolean;
    currentAdminJid: string;
    onReact: (m: ChatMessage, emoji: string) => void;
    onLightbox: (url: string) => void;
};

export function MessageBubble({ m, isMine, currentAdminJid, onReact, onLightbox }: MessageBubbleProps) {
    const t = m.type ?? 'text';
    const fallbackMediaUrl = (m.metadata?.media_url as string | undefined) ?? null;
    const hasMedia = Boolean(m.attachment_url) || Boolean(fallbackMediaUrl);
    const isImage = t === 'image' && hasMedia;
    const isVideo = t === 'video' && hasMedia;
    const isAudio = t === 'audio' && hasMedia;
    const isSticker = t === 'sticker' && hasMedia;
    const isFile = t === 'file' && hasMedia;
    const isDoc = !isImage && !isVideo && !isAudio && !isSticker && !isFile && hasMedia;
    const isLocation = t === 'location';
    const locLat = isLocation ? Number(m.metadata?.media_latitude ?? NaN) : NaN;
    const locLng = isLocation ? Number(m.metadata?.media_longitude ?? NaN) : NaN;
    const hasCoords = isLocation && Number.isFinite(locLat) && Number.isFinite(locLng);
    const locName = isLocation ? (m.metadata?.media_name as string | undefined) : undefined;
    const locAddress = isLocation ? (m.metadata?.media_address as string | undefined) : undefined;
    const isContact = t === 'contact';
    const contactName = isContact ? (m.metadata?.media_name as string | undefined) : undefined;
    const contactPhone = isContact ? (m.metadata?.media_phone as string | undefined) : undefined;

    const placeholder = isPlaceholder(m.content);
    const showCaption = m.content && !placeholder && !isLocation && !isContact;

    const [reactionsOpen, setReactionsOpen] = useState(false);

    const handleCopy = (): void => {
        if (m.content && !placeholder) {
            navigator.clipboard?.writeText(m.content).catch(() => {});
        }
    };

    return (
        <div className={cn('group flex w-full', isMine ? 'justify-end' : 'justify-start')}>
            <div className="flex max-w-[75%] flex-col text-sm shadow-sm">
                <div
                    className={cn(
                        'flex flex-col overflow-hidden border border-border bg-muted text-foreground',
                        hasMedia ? 'rounded-lg' : 'rounded-lg px-3 py-2',
                    )}
                >
                    {isSticker && <FileBubble m={m} isSticker />}

                    {isImage && <ImageBubble m={m} isMine={isMine} onLightbox={onLightbox} />}

                    {isVideo && <VideoBubble m={m} isMine={isMine} onLightbox={onLightbox} />}

                    {isAudio && <AudioBubble m={m} isMine={isMine} />}

                    {isFile && <FileBubble m={m} isMine={isMine} />}

                    {isDoc && <FileBubble m={m} isMine={isMine} />}

                    {isLocation && (
                        <div className="flex flex-col gap-1 px-3 py-2 text-xs">
                            <div className="flex items-center gap-1.5 font-medium">
                                <MapPin className="size-3.5 shrink-0" />
                                <span className="truncate">{locName || locAddress || 'Ubicación compartida'}</span>
                            </div>
                            {hasCoords && (
                                <a
                                    href={`https://www.google.com/maps?q=${locLat},${locLng}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary underline-offset-2 hover:underline"
                                >
                                    {locLat.toFixed(5)}, {locLng.toFixed(5)}
                                </a>
                            )}
                            {! hasCoords && locAddress && (
                                <span className="text-muted-foreground">{locAddress}</span>
                            )}
                        </div>
                    )}

                    {isContact && (
                        <div className="flex items-center gap-2 px-3 py-2 text-xs">
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <User className="size-4" />
                            </div>
                            <div className="flex min-w-0 flex-col">
                                <span className="truncate font-medium">{contactName || 'Contacto'}</span>
                                {contactPhone && (
                                    <a
                                        href={`tel:${contactPhone}`}
                                        className="flex items-center gap-1 text-muted-foreground hover:text-primary"
                                    >
                                        <Phone className="size-3 shrink-0" />
                                        <span className="truncate">+{contactPhone}</span>
                                    </a>
                                )}
                            </div>
                        </div>
                    )}

                    {showCaption && (
                        <div className={cn(hasMedia ? 'px-3 pb-1.5 pt-1' : '')}>
                            <MessageBody
                                content={m.content ?? ''}
                                linkPreviews={m.link_previews ?? null}
                                mediaPreview={(m.metadata?.media_preview as LinkPreviewItem | null | undefined) ?? null}
                            />
                        </div>
                    )}
                </div>

                {(m.reactions ?? []).length > 0 && (
                    <div className={cn('mt-1 flex flex-wrap gap-1', isMine ? 'justify-end' : 'justify-start')}>
                        {Object.entries(
                            (m.reactions ?? []).reduce<Record<string, Reaction[]>>((acc, r) => {
                                (acc[r.emoji] ??= []).push(r);
                                return acc;
                            }, {}),
                        ).map(([emoji, list]) => (
                            <button
                                key={emoji}
                                type="button"
                                onClick={() => onReact(m, emoji)}
                                className={cn(
                                    'flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition',
                                    list.some((r) => r.user_jid === currentAdminJid)
                                        ? 'border-primary bg-primary/10 text-foreground'
                                        : 'border-border bg-muted/50 hover:bg-muted',
                                )}
                                title={list.map((r) => r.user_jid).join(', ')}
                            >
                                <span>{emoji}</span>
                                <span className="text-[10px] font-medium">{list.length}</span>
                            </button>
                        ))}
                    </div>
                )}

                <div className="mt-0.5 flex items-center justify-between gap-2 px-1 text-[10px] text-muted-foreground">
                    <span className="truncate">
                        {formatChatDate(m.created_at)}
                        {isMine && m.read_at ? ' · Leído' : ''}
                    </span>
                    <div className="flex shrink-0 items-center gap-1 opacity-0 transition group-hover:opacity-100">
                        {showCaption && (
                            <button
                                type="button"
                                onClick={handleCopy}
                                title="Copiar texto"
                                className="rounded p-0.5 transition hover:bg-muted hover:text-foreground"
                            >
                                <Copy className="size-3" />
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setReactionsOpen((v) => !v)}
                            title="Reaccionar"
                            className="rounded p-0.5 transition hover:bg-muted hover:text-foreground"
                        >
                            <Smile className="size-3" />
                        </button>
                    </div>
                </div>

                {reactionsOpen && (
                    <div className={cn('mt-1 flex flex-wrap gap-1', isMine ? 'justify-end' : 'justify-start')}>
                        {REACTION_EMOJIS.map((emoji) => (
                            <button
                                key={emoji}
                                type="button"
                                onClick={() => {
                                    onReact(m, emoji);
                                    setReactionsOpen(false);
                                }}
                                className="rounded-full border bg-background px-1.5 py-0.5 text-sm transition hover:bg-muted"
                            >
                                {emoji}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
