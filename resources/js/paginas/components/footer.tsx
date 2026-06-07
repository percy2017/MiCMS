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
    const menu = useMenu('footer');
    const items = menu?.items ?? [];
    const year = new Date().getFullYear();

    return (
        <footer className="border-t border-border bg-background">
            <div className="mx-auto max-w-7xl px-4 py-10">
                {items.length > 0 ? (
                    <nav aria-label="Menú del pie" className="mb-6">
                        <ul className="flex flex-wrap items-center gap-x-6 gap-y-2">
                            {items.map((item) => (
                                <FooterLink key={item.id} item={item} />
                            ))}
                        </ul>
                    </nav>
                ) : null}
                <p className="text-xs text-muted-foreground">
                    © {year} Mi Sitio. Todos los derechos reservados.
                </p>
            </div>
        </footer>
    );
}
