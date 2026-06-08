import { usePage } from '@inertiajs/react';
import { useMenu, type MenuItemNode } from '@/paginas/menu-context';

function FooterLink({ item }: { item: MenuItemNode }) {
    return (
        <li>
            <a
                href={item.resolved_url}
                target={item.target}
                rel={item.target === '_blank' ? 'noopener noreferrer' : undefined}
                className="text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                {item.label}
            </a>
        </li>
    );
}

export default function Footer() {
    const { site } = usePage().props as { site: { name: string } };
    const menu = useMenu('footer');
    const items = menu?.items ?? [];
    const year = new Date().getFullYear();

    return (
        <footer className="border-t border-border bg-background">
            <div className="mx-auto flex w-full max-w-7xl flex-col items-center gap-2 px-4 py-4 sm:min-h-16 sm:flex-row sm:justify-between sm:gap-4 sm:py-0">
                {items.length > 0 ? (
                    <nav aria-label="Menú del pie" className="flex-1">
                        <ul className="flex flex-wrap items-center justify-center gap-x-6 gap-y-1 sm:justify-start">
                            {items.map((item) => (
                                <FooterLink key={item.id} item={item} />
                            ))}
                        </ul>
                    </nav>
                ) : (
                    <div className="flex-1" />
                )}
                <p className="text-center text-xs text-muted-foreground sm:text-right">
                    © {year} {site.name}. Todos los derechos reservados.
                </p>
            </div>
        </footer>
    );
}
