/**
 * Visibility rule shared by the sidebar, command palette, top-bar
 * create menu and dashboard quick actions: an item without
 * `permission` is always visible; a string requires that exact
 * ability; a list requires any one of them — matching the server's
 * "abort_if(allowed list is empty)" guards.
 */
export function navItemAllowed(
    item: { permission?: string | string[] },
    can: (ability: string) => boolean,
): boolean {
    if (!item.permission) {
        return true;
    }

    if (Array.isArray(item.permission)) {
        return item.permission.some((ability) => can(ability));
    }

    return can(item.permission);
}
