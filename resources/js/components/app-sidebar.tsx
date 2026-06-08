import { Link } from '@inertiajs/react';
import { Activity, CalendarClock, Image as ImageIcon, LayoutGrid, LayoutTemplate } from 'lucide-react';
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
import { index as paginasIndex } from '@/routes/admin/paginas';
import { reverb as reverbRoute } from '@/routes/admin';
import { index as scheduleIndex } from '@/routes/admin/schedule';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Admin',
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
    {
        title: 'Socket',
        href: reverbRoute(),
        icon: Activity,
    },
    {
        title: 'Tareas',
        href: scheduleIndex(),
        icon: CalendarClock,
    },
];

export function AppSidebar() {
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
