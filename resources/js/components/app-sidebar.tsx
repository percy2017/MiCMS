import { Link, usePage } from '@inertiajs/react';
import {
    Image as ImageIcon,
    LayoutGrid,
    LayoutTemplate,
    MessageCircle,
    Package,
    Puzzle,
    ScrollText,
    Settings,
    ShoppingCart,
    UserCog,
} from 'lucide-react';
import type { ComponentType } from 'react';
import AppLogo from '@/components/app-logo';
import { useCan, useIsAdmin } from '@/hooks/use-can';
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
import { index as paquetesIndex } from '@/routes/admin/paquetes';
import { index as paginasIndex } from '@/routes/admin/paginas';
import { index as permisosIndex } from '@/routes/admin/permisos';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as scheduleIndex } from '@/routes/admin/schedule';
import { index as usuariosIndex } from '@/routes/admin/usuarios';
import { reverb as reverbRoute } from '@/routes/admin';
import type { NavItem } from '@/types';

type IconComponent = ComponentType<{ className?: string }>;

type MenuChild = {
    title: string;
    route?: string;
};

type SharedPackage = {
    slug: string;
    label: string;
    icon?: string | null;
    menu?: {
        icon?: string;
        children?: MenuChild[];
    };
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

    const can = {
        viewPages: useCan('view pages'),
        viewMedia: useCan('view media'),
        viewMenus: useCan('view menus'),
        viewPackages: useCan('view packages'),
        viewSchedule: useCan('view schedule'),
        viewUsers: useCan('view users'),
        viewRoles: useCan('view roles'),
        viewPermissions: useCan('view permissions'),
        viewPosWoo: useCan('view pos-woo'),
        viewChatbot: useCan('view chatbot'),
        viewLogs: useCan('view logs'),
    };

    const isAdmin = useIsAdmin();

    const mainNavItems: NavItem[] = [
        {
            title: 'Panel',
            href: admin(),
            icon: LayoutGrid,
        },
    ];

    const pagesChildren: NavItem[] = [];
    if (can.viewPages) {
        pagesChildren.push({ title: 'Páginas', href: paginasIndex() });
    }
    if (can.viewMenus) {
        pagesChildren.push({ title: 'Menús', href: menusIndex() });
    }
    if (pagesChildren.length > 0) {
        mainNavItems.push({
            title: 'Contenido',
            href: pagesChildren[0].href,
            icon: LayoutTemplate,
            children: pagesChildren,
        });
    }

    const userMgmtChildren: NavItem[] = [];
    if (can.viewUsers) {
        userMgmtChildren.push({ title: 'Usuarios', href: usuariosIndex() });
    }
    if (can.viewRoles) {
        userMgmtChildren.push({ title: 'Roles', href: rolesIndex() });
    }
    if (can.viewPermissions) {
        userMgmtChildren.push({ title: 'Permisos', href: permisosIndex() });
    }
    if (userMgmtChildren.length > 0) {
        mainNavItems.push({
            title: 'Usuarios',
            href: userMgmtChildren[0].href,
            icon: UserCog,
            children: userMgmtChildren,
        });
    }

    if (can.viewMedia) {
        mainNavItems.push({
            title: 'Medios',
            href: mediaIndex(),
            icon: ImageIcon,
        });
    }

    if (can.viewChatbot) {
        mainNavItems.push({
            title: 'Mensajeria',
            href: '/admin/canales',
            icon: MessageCircle,
            children: [
                { title: 'Chats', href: '/admin/chats' },
                { title: 'Canales', href: '/admin/canales' },
                { title: 'Respuestas rápidas', href: '/admin/canales/respuestas-rapidas' },
            ],
        });
    }

    if (can.viewPosWoo) {
        const posWooPkg = enabledPackages.find((p) => p.slug === 'pos-woo');
        const moduleChildren = (posWooPkg?.menu?.children ?? []).map((child) => ({
            title: child.title,
            href: child.href ?? '#',
        }));

        const posWooChildren: NavItem[] = moduleChildren.length > 0
            ? moduleChildren
            : [
                { title: 'Terminal', href: '/admin/pos-woo' },
                { title: 'Pedidos', href: '/admin/pos-woo/pedidos' },
                { title: 'Calendario', href: '/admin/pos-woo/calendario' },
            ];

        if (posWooPkg || true) {
            mainNavItems.push({
                title: posWooPkg?.label ?? 'PosWoo',
                href: posWooChildren[0].href,
                icon: resolveIcon(posWooPkg?.menu?.icon ?? posWooPkg?.icon),
                children: posWooChildren,
            });
        }
    }



    const configChildren: NavItem[] = [];
    if (can.viewPackages) {
        configChildren.push({ title: 'Paquetes', href: paquetesIndex() });
    }
    if (can.viewSchedule) {
        configChildren.push({ title: 'Tareas', href: scheduleIndex() });
    }
    if (can.viewLogs) {
        configChildren.push({ title: 'logs', href: '/admin/logs' });
    }
    configChildren.push({ title: 'Socket', href: reverbRoute() });
    
    if (configChildren.length > 0) {
        mainNavItems.push({
            title: 'Configuración',
            href: configChildren[0].href,
            icon: Settings,
            children: configChildren,
        });
    }

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
