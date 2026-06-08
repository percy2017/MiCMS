import { Link, usePage } from '@inertiajs/react';
import {
    Image as ImageIcon,
    LayoutGrid,
    LayoutTemplate,
    Package,
    Puzzle,
    Settings,
    ShoppingCart,
} from 'lucide-react';
import type { ComponentType } from 'react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { admin } from '@/routes';
import { index as mediaIndex } from '@/routes/admin/media';
import { index as menusIndex } from '@/routes/admin/menus';
import { index as paquetesIndex, edit as paquetesEdit } from '@/routes/admin/paquetes';
import { index as paginasIndex } from '@/routes/admin/paginas';
import { reverb as reverbRoute } from '@/routes/admin';
import { index as scheduleIndex } from '@/routes/admin/schedule';
import type { NavItem } from '@/types';

type IconComponent = ComponentType<{ className?: string }>;

type SharedPackage = {
    id: number;
    slug: string;
    label: string;
    icon?: string | null;
};

type SharedProps = {
    enabledPackages: SharedPackage[];
};

const PACKAGE_ICON_MAP: Record<string, IconComponent> = {
    ShoppingCart,
    Package,
};

function resolveIcon(name?: string | null): IconComponent {
    if (name && PACKAGE_ICON_MAP[name]) {
        return PACKAGE_ICON_MAP[name];
    }

    return Puzzle;
}

export function AppSidebar() {
    const { props } = usePage<SharedProps>();
    const enabledPackages = props.enabledPackages ?? [];

    const mainNavItems: NavItem[] = [
        {
            title: 'Panel',
            href: admin(),
            icon: LayoutGrid,
        },
        {
            title: 'Páginas',
            href: paginasIndex(),
            icon: LayoutTemplate,
            children: [
                {
                    title: 'Páginas',
                    href: paginasIndex(),
                },
                {
                    title: 'Menús',
                    href: menusIndex(),
                },
            ],
        },
        {
            title: 'Medios',
            href: mediaIndex(),
            icon: ImageIcon,
        },
        ...enabledPackages.map<NavItem>((pkg) => {
            if (pkg.slug === 'pos-woo') {
                return {
                    title: pkg.label,
                    icon: ShoppingCart,
                    children: [
                        { title: 'Terminal', href: '/admin/pos-woo' },
                        { title: 'Pedidos', href: '/admin/pos-woo/pedidos' },
                    ],
                };
            }

            return {
                title: pkg.label,
                href: paquetesEdit({ package: pkg.id }).url,
                icon: resolveIcon(pkg.icon),
            };
        }),
        {
            title: 'Configuración',
            href: '#',
            icon: Settings,
            children: [
                {
                    title: 'Paquetes',
                    href: paquetesIndex(),
                },
                {
                    title: 'Socket',
                    href: reverbRoute(),
                },
                {
                    title: 'Tareas',
                    href: scheduleIndex(),
                },
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={admin()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
