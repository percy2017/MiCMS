import { useEffect } from 'react';

export function useEscapeKey(active: boolean, onEscape: () => void) {
    useEffect(() => {
        if (!active) return;
        function handler(e: KeyboardEvent) {
            if (e.key === 'Escape') {
                e.stopPropagation();
                onEscape();
            }
        }
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [active, onEscape]);
}
