/**
 * Roles and rights as the «Роли и права» screen words them. What a right
 * allows is decided on the server (App\Support\PermissionMatrix); this only
 * puts it into words and keeps a picked set consistent while it is edited.
 */

export interface RightsModule {
    value: string;
    label: string;
    /** The actions that mean something for the section. */
    actions: string[];
}

export interface RightsAction {
    value: string;
    label: string;
}

/** [section => [action => granted]] over the meaningful cells. */
export type RightsMatrix = Record<string, Record<string, boolean>>;

/** Sections of the rights table, grouped the way the sidebar reads. */
export const MODULE_GROUPS: { label: string; modules: string[] }[] = [
    {
        label: 'Материалы сайта',
        modules: [
            'alerts',
            'news',
            'instructions',
            'documents',
            'projects',
            'announcements',
            'pages',
        ],
    },
    {
        label: 'Оформление и справочники',
        modules: [
            'home',
            'media',
            'taxonomy',
            'regions',
            'leadership',
            'structure',
        ],
    },
    { label: 'Работа с гражданами', modules: ['submissions'] },
    { label: 'Администрирование', modules: ['users', 'settings'] },
];

/**
 * What a role may do in a section, in a few words: «Всё», «Просмотр»,
 * «Создание, правка · публикация через согласование», «—».
 */
export function rightsSummary(
    module: RightsModule,
    cells: Record<string, boolean> | undefined,
    actions: RightsAction[],
): string {
    const granted = module.actions.filter((action) => cells?.[action]);

    if (granted.length === 0) {
        return '—';
    }

    if (granted.length === module.actions.length) {
        return 'Всё';
    }

    const words = granted
        .filter((action) => action !== 'view')
        .map(
            (action) =>
                actions
                    .find((known) => known.value === action)
                    ?.label.toLowerCase() ?? action,
        );

    if (words.length === 0) {
        return 'Просмотр';
    }

    const text = words.join(', ');
    const sentence = text.charAt(0).toUpperCase() + text.slice(1);
    const prepares = granted.includes('create') || granted.includes('edit');
    const viaApproval =
        prepares &&
        module.actions.includes('publish') &&
        !granted.includes('publish');

    return viaApproval
        ? `${sentence} · публикация через согласование`
        : sentence;
}

/**
 * Tick or untick one right. Seeing a section comes with any other right in
 * it, and without seeing it no other right there works — the server keeps
 * the same rule (PermissionMatrix::normalize).
 */
export function toggleRight(
    permissions: string[],
    module: string,
    action: string,
    on: boolean,
): string[] {
    const name = `${module}.${action}`;
    const view = `${module}.view`;
    let next = permissions.filter((permission) => permission !== name);

    if (on) {
        next.push(name);

        if (action !== 'view' && !next.includes(view)) {
            next.push(view);
        }
    } else if (action === 'view') {
        next = next.filter(
            (permission) => !permission.startsWith(`${module}.`),
        );
    }

    return next;
}

/** The rights a matrix grants, as `module.action` names. */
export function grantedRights(matrix: RightsMatrix): string[] {
    return Object.entries(matrix).flatMap(([module, cells]) =>
        Object.entries(cells)
            .filter(([, granted]) => granted)
            .map(([action]) => `${module}.${action}`),
    );
}
