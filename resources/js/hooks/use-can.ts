import { usePage } from '@inertiajs/react';

type AuthUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
} | null;

type PageProps = {
    auth: {
        user: AuthUser;
    };
};

export function useCan(permission: string): boolean {
    const { props } = usePage<PageProps>();
    const perms = props.auth.user?.permissions ?? [];
    return perms.includes(permission);
}

export function useHasRole(role: string): boolean {
    const { props } = usePage<PageProps>();
    const roles = props.auth.user?.roles ?? [];
    return roles.includes(role);
}

export function useIsAdmin(): boolean {
    return useHasRole('admin');
}
