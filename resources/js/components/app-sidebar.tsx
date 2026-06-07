import { Link } from '@inertiajs/react';
import { FileText, Image as ImageIcon, LayoutGrid, Menu as MenuIcon } from 'lucide-react';
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
        icon: FileText,
    },
    {
        title: 'Medios',
        href: mediaIndex(),
        icon: ImageIcon,
    },
    {
        title: 'Menús',
        href: menusIndex(),
        icon: MenuIcon,
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
