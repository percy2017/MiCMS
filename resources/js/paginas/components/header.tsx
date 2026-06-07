import { usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { useMenu, type MenuItemNode } from '@/paginas/menu-context';

type NavLinkProps = {
    item: MenuItemNode;
    depth?: number;
};

function NavLink({ item, depth = 0 }: NavLinkProps) {
    const hasChildren = (item.children?.length ?? 0) > 0;

    if (hasChildren) {
        return (
            <li className="group relative">
                <button
                    type="button"
                    className="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
                >
                    {item.label}
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="12"
                        height="12"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>
                <ul
                    className={
                        'absolute left-0 top-full z-50 min-w-48 rounded-md border bg-popover p-1 text-popover-foreground shadow-md ' +
                        (depth > 0 ? 'left-full top-0' : '')
                    }
                >
                    {item.children!.map((child) => (
                        <NavLink key={child.id} item={child} depth={depth + 1} />
                    ))}
                </ul>
            </li>
        );
    }

    return (
        <li>
            <a
                href={item.resolved_url}
                target={item.target}
                rel={item.target === '_blank' ? 'noopener noreferrer' : undefined}
                className="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
            >
                {item.label}
                {item.is_external ? (
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="12"
                        height="12"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="ml-1"
                        aria-hidden="true"
                    >
                        <path d="M15 3h6v6" />
                        <path d="M10 14 21 3" />
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                    </svg>
                ) : null}
            </a>
        </li>
    );
}

export default function Header() {
    const { site } = usePage().props as { site: { name: string; logo_url: string | null; tagline: string } };
    const menu = useMenu('header');
    const items = menu?.items ?? [];
    const [open, setOpen] = useState(false);

    return (
        <header className="sticky top-0 z-40 w-full border-b border-border bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4">
                <a
                    href="/"
                    className="flex items-center gap-2 text-base font-semibold tracking-tight"
                >
                    {site.logo_url ? (
                        <img
                            src={site.logo_url}
                            alt={site.name}
                            className="h-7 w-auto rounded-md"
                        />
                    ) : (
                        <span className="inline-block size-7 rounded-md bg-primary" aria-hidden="true" />
                    )}
                    {site.name}
                </a>

                {items.length > 0 ? (
                    <>
                        <nav aria-label="Menú principal" className="hidden lg:block">
                            <ul className="flex items-center gap-1">
                                {items.map((item) => (
                                    <NavLink key={item.id} item={item} />
                                ))}
                            </ul>
                        </nav>

                        <Sheet open={open} onOpenChange={setOpen}>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="lg:hidden"
                                    aria-label="Abrir menú"
                                >
                                    {open ? (
                                        <X className="size-5" />
                                    ) : (
                                        <Menu className="size-5" />
                                    )}
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="right" className="w-72 sm:w-80">
                                <div className="mt-8 flex flex-col gap-1">
                                    {items.map((item) => (
                                        <MobileNavLink
                                            key={item.id}
                                            item={item}
                                            onClose={() => setOpen(false)}
                                        />
                                    ))}
                                </div>
                            </SheetContent>
                        </Sheet>
                    </>
                ) : (
                    <span className="hidden text-xs text-muted-foreground lg:block">
                        Sin menú principal
                    </span>
                )}
            </div>
        </header>
    );
}

function MobileNavLink({
    item,
    depth = 0,
    onClose,
}: {
    item: MenuItemNode;
    depth?: number;
    onClose: () => void;
}) {
    const hasChildren = (item.children?.length ?? 0) > 0;

    if (hasChildren) {
        return (
            <div className="flex flex-col">
                <span className="rounded-md px-3 py-2 text-sm font-medium text-foreground/80">
                    {item.label}
                </span>
                {item.children!.map((child) => (
                    <MobileNavLink
                        key={child.id}
                        item={child}
                        depth={depth + 1}
                        onClose={onClose}
                    />
                ))}
            </div>
        );
    }

    return (
        <a
            href={item.resolved_url}
            target={item.target}
            rel={item.target === '_blank' ? 'noopener noreferrer' : undefined}
            onClick={onClose}
            className="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
        >
            {item.label}
            {item.is_external ? (
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                >
                    <path d="M15 3h6v6" />
                    <path d="M10 14 21 3" />
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                </svg>
            ) : null}
        </a>
    );
}
