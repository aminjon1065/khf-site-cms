import { usePage } from '@inertiajs/react';
import type { AuthUser, SharedProps } from '@/types/cms';

export function useShared(): SharedProps {
    return usePage<SharedProps>().props;
}

export function useAuth(): AuthUser | null {
    return usePage<SharedProps>().props.auth.user;
}

/**
 * Permission checker. The administrator passes everything; otherwise the
 * ability must be present in the user's flattened permission list.
 */
export function useCan(): (ability: string) => boolean {
    const user = useAuth();

    return (ability: string) => {
        if (!user) {
            return false;
        }

        return user.is_admin || user.permissions.includes(ability);
    };
}
