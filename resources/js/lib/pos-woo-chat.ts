import { router } from '@inertiajs/react';

export async function openPosWooChat(
    convId: number | null | undefined,
    phone: string | null | undefined,
    name: string,
): Promise<void> {
    if (convId) {
        router.visit(`/admin/chats?active=${convId}`);
        return;
    }
    if (!phone) {
        return;
    }
    const csrf = document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.getAttribute('content') ?? '';
    try {
        const r = await fetch('/admin/pos-woo/find-or-create-chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({ phone, name }),
        });
        const data = (await r.json().catch(() => ({}))) as { conversation_id?: number; error?: string };
        if (data?.conversation_id) {
            router.visit(`/admin/chats?active=${data.conversation_id}`);
        } else if (data?.error) {
            console.warn('find-or-create-chat:', data.error);
        }
    } catch {
        // Silenciar errores de red
    }
}
