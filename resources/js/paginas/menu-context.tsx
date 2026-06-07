import { createContext, useContext, type ReactNode } from 'react';

export type MenuItemNode = {
    id: number;
    menu_id: number;
    parent_id: number | null;
    label: string;
    url: string | null;
    resolved_url: string;
    type: 'custom' | 'page';
    page_id: number | null;
    order: number;
    target: '_self' | '_blank';
    is_external: boolean;
    children?: MenuItemNode[];
};

export type MenuNode = {
    id: number;
    name: string;
    location: string;
    items: MenuItemNode[];
};

export type MenusByLocation = Record<string, MenuNode>;

const MenuContext = createContext<MenusByLocation>({});

export function MenuProvider({
    menus,
    children,
}: {
    menus: MenusByLocation;
    children: ReactNode;
}) {
    return <MenuContext value={menus}>{children}</MenuContext>;
}

export function useMenus(): MenusByLocation {
    return useContext(MenuContext);
}

export function useMenu(location: string): MenuNode | null {
    const menus = useMenus();
    return menus[location] ?? null;
}
