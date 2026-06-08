import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useCallback, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { appName } from '@/lib/app-name';
import type { NavItem } from '@/types';

function isItemActive(item: NavItem, isCurrentUrl: (href: string) => boolean): boolean {
    if (item.href && isCurrentUrl(item.href)) {
        return true;
    }

    return (item.children ?? []).some((child) => isItemActive(child, isCurrentUrl));
}

function NavLeaf({ item, isCurrentUrl }: { item: NavItem; isCurrentUrl: (href: string) => boolean }) {
    if (!item.href) return null;

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={isCurrentUrl(item.href)}
                tooltip={{ children: item.title }}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function NavGroup({
    item,
    isCurrentUrl,
    open,
    onOpenChange,
}: {
    item: NavItem;
    isCurrentUrl: (href: string) => boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const active = isItemActive(item, isCurrentUrl);

    return (
        <Collapsible asChild open={open} onOpenChange={onOpenChange}>
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        isActive={active}
                        tooltip={{ children: item.title }}
                    >
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {item.children?.map((child) => (
                            <SidebarMenuSubItem key={child.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isCurrentUrl(child.href)}
                                >
                                    <Link href={child.href} prefetch>
                                        <span>{child.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    const initialOpen = (() => {
        const active = items.find((item) => isItemActive(item, isCurrentUrl));
        return active ? active.title : null;
    })();

    const [openTitle, setOpenTitle] = useState<string | null>(initialOpen);

    const handleOpenChange = useCallback((title: string, open: boolean) => {
        setOpenTitle((current) => {
            if (open) {
                return title;
            }
            return current === title ? null : current;
        });
    }, []);

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{appName}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.children && item.children.length > 0 ? (
                        <NavGroup
                            key={item.title}
                            item={item}
                            isCurrentUrl={isCurrentUrl}
                            open={openTitle === item.title}
                            onOpenChange={(open) => handleOpenChange(item.title, open)}
                        />
                    ) : (
                        <NavLeaf key={item.title} item={item} isCurrentUrl={isCurrentUrl} />
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
