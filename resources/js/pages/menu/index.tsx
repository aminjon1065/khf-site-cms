import { Head, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    IndentDecrease,
    IndentIncrease,
    ListTree,
    Plus,
    Save,
    X,
} from 'lucide-react';
import { useId, useState } from 'react';
import MenuController from '@/actions/App/Http/Controllers/Cms/MenuController';
import { useCan } from '@/lib/auth';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import { Checkbox, Input, InputError } from '@/ui/Field';
import { ConfirmDialog } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };
type Location = 'main' | 'footer';

interface MenuLeaf {
    key: string;
    id: number | null;
    label: LocaleMap;
    url: string;
    enabled: boolean;
}

interface MenuRow extends MenuLeaf {
    children: MenuLeaf[];
}

interface Props {
    menus: Record<
        Location,
        {
            id: number;
            label: LocaleMap;
            url: string | null;
            enabled: boolean;
            children?: {
                id: number;
                label: LocaleMap;
                url: string | null;
                enabled: boolean;
            }[];
        }[]
    >;
}

const LOCATIONS: { key: Location; label: string; hint: string }[] = [
    { key: 'main', label: 'Главное меню', hint: 'Верхняя навигация сайта' },
    {
        key: 'footer',
        label: 'Меню подвала',
        hint: 'Раздел «Разделы» в подвале',
    },
];

const EMPTY_LABEL: LocaleMap = { ru: '', tg: '', en: '' };

let rowSeq = 0;
const nextKey = (): string => `menu-row-${++rowSeq}`;

function toLeaf(item: {
    id: number | null;
    label: LocaleMap;
    url: string | null;
    enabled: boolean;
}): MenuLeaf {
    return {
        key: item.id !== null ? `id-${item.id}` : nextKey(),
        id: item.id,
        label: {
            ru: item.label.ru ?? '',
            tg: item.label.tg ?? '',
            en: item.label.en ?? '',
        },
        url: item.url ?? '',
        enabled: item.enabled,
    };
}

function toRow(item: {
    id: number | null;
    label: LocaleMap;
    url: string | null;
    enabled: boolean;
    children?: {
        id: number;
        label: LocaleMap;
        url: string | null;
        enabled: boolean;
    }[];
}): MenuRow {
    return {
        ...toLeaf(item),
        children: (item.children ?? []).map(toLeaf),
    };
}

function emptyLeaf(): MenuLeaf {
    return {
        key: nextKey(),
        id: null,
        label: { ...EMPTY_LABEL },
        url: '',
        enabled: true,
    };
}

function emptyRow(): MenuRow {
    return { ...emptyLeaf(), children: [] };
}

function payloadOf(rows: MenuRow[]): Record<string, unknown>[] {
    return rows
        .map((row) => ({
            id: row.id,
            label: row.label,
            url: row.url,
            enabled: row.enabled,
            children: row.children
                .filter((child) => child.label.ru.trim() !== '')
                .map((child) => ({
                    id: child.id,
                    label: child.label,
                    url: child.url,
                    enabled: child.enabled,
                })),
        }))
        .filter(
            (row) =>
                row.label.ru.trim() !== '' ||
                (row.children as { label: LocaleMap }[]).length > 0,
        );
}

export default function MenuIndex({ menus }: Props) {
    const can = useCan();
    const editable = can('settings.edit');
    const errorId = useId();
    const [pendingDelete, setPendingDelete] = useState<{
        loc: Location;
        parent: number;
        child?: number;
    } | null>(null);

    const form = useForm({
        items: {
            main: menus.main.map(toRow),
            footer: menus.footer.map(toRow),
        } as Record<Location, MenuRow[]>,
    });
    const { data, setData, processing, errors, hasErrors } = form;

    const setItems = (loc: Location, next: MenuRow[]) =>
        setData('items', { ...data.items, [loc]: next });

    const updateRoot = (loc: Location, i: number, patch: Partial<MenuRow>) =>
        setItems(
            loc,
            data.items[loc].map((row, idx) =>
                idx === i ? { ...row, ...patch } : row,
            ),
        );

    const updateChild = (
        loc: Location,
        parent: number,
        child: number,
        patch: Partial<MenuLeaf>,
    ) =>
        updateRoot(loc, parent, {
            children: data.items[loc][parent].children.map((row, idx) =>
                idx === child ? { ...row, ...patch } : row,
            ),
        });

    const add = (loc: Location) =>
        setItems(loc, [...data.items[loc], emptyRow()]);

    const addChild = (loc: Location, parent: number) =>
        updateRoot(loc, parent, {
            children: [...data.items[loc][parent].children, emptyLeaf()],
        });

    const removeRoot = (loc: Location, i: number) =>
        setItems(
            loc,
            data.items[loc].filter((_, idx) => idx !== i),
        );

    const removeChild = (loc: Location, parent: number, child: number) =>
        updateRoot(loc, parent, {
            children: data.items[loc][parent].children.filter(
                (_, idx) => idx !== child,
            ),
        });

    const moveRoot = (loc: Location, i: number, dir: -1 | 1) => {
        const j = i + dir;

        if (j < 0 || j >= data.items[loc].length) {
            return;
        }

        const next = [...data.items[loc]];
        [next[i], next[j]] = [next[j], next[i]];
        setItems(loc, next);
    };

    const moveChild = (
        loc: Location,
        parent: number,
        child: number,
        dir: -1 | 1,
    ) => {
        const siblings = data.items[loc][parent].children;
        const j = child + dir;

        if (j < 0 || j >= siblings.length) {
            return;
        }

        const next = [...siblings];
        [next[child], next[j]] = [next[j], next[child]];
        updateRoot(loc, parent, { children: next });
    };

    const indent = (loc: Location, i: number) => {
        if (i === 0) {
            return;
        }

        const items = data.items[loc];
        const current = items[i];
        const previous = items[i - 1];
        const asLeaf: MenuLeaf = {
            key: current.key,
            id: current.id,
            label: current.label,
            url: current.url,
            enabled: current.enabled,
        };
        const next = [
            ...items.slice(0, i - 1),
            {
                ...previous,
                children: [...previous.children, asLeaf, ...current.children],
            },
            ...items.slice(i + 1),
        ];
        setItems(loc, next);
    };

    const outdent = (loc: Location, parent: number, child: number) => {
        const items = data.items[loc];
        const root = items[parent];
        const moving = root.children[child];
        const next = [
            ...items.slice(0, parent),
            {
                ...root,
                children: root.children.filter((_, idx) => idx !== child),
            },
            { ...moving, children: [] },
            ...items.slice(parent + 1),
        ];
        setItems(loc, next);
    };

    const requestRemove = (loc: Location, parent: number, child?: number) => {
        if (child === undefined) {
            if (data.items[loc][parent].children.length > 0) {
                setPendingDelete({ loc, parent });

                return;
            }

            removeRoot(loc, parent);

            return;
        }

        removeChild(loc, parent, child);
    };

    const save = () => {
        form.transform((current) => ({
            items: {
                main: payloadOf(current.items.main),
                footer: payloadOf(current.items.footer),
            },
        }));

        form.put(MenuController.update.url(), { preserveScroll: true });
    };

    const fieldError = (path: string): string | undefined =>
        (errors as Record<string, string | undefined>)[path];

    return (
        <>
            <Head title="Меню сайта" />
            <PageHeader
                title="Меню сайта"
                subtitle="Пункты навигации публичного сайта · заголовок, ссылка, порядок, вложенность и видимость"
                actions={
                    editable && (
                        <Button
                            variant="primary"
                            icon={<Save size={16} strokeWidth={1.75} />}
                            loading={processing}
                            onClick={save}
                        >
                            Сохранить
                        </Button>
                    )
                }
            />

            {hasErrors && (
                <Blueprint
                    role="alert"
                    aria-labelledby={errorId}
                    style={{
                        marginBottom: 16,
                        padding: 16,
                        borderColor: 'var(--danger)',
                    }}
                >
                    <h3
                        id={errorId}
                        className="ui-card-title"
                        style={{ margin: '0 0 8px' }}
                    >
                        Проверьте пункты меню
                    </h3>
                    <ul
                        style={{
                            margin: 0,
                            paddingLeft: 18,
                            fontSize: 13,
                            color: 'var(--danger)',
                        }}
                    >
                        {Object.entries(errors).map(([key, message]) => (
                            <li key={key}>{message}</li>
                        ))}
                    </ul>
                </Blueprint>
            )}

            <div style={{ display: 'grid', gap: 16 }}>
                {LOCATIONS.map(({ key, label, hint }) => (
                    <Blueprint key={key} data-location={key} className="p-5">
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 4,
                                gap: 12,
                            }}
                        >
                            <h3 className="ui-card-title" style={{ margin: 0 }}>
                                {label}
                            </h3>
                            {editable && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    icon={<Plus size={14} strokeWidth={2} />}
                                    onClick={() => add(key)}
                                >
                                    Пункт
                                </Button>
                            )}
                        </div>
                        <p
                            style={{
                                margin: '0 0 12px',
                                fontSize: 12,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            {hint}. Один уровень вложенности: подпункт
                            показывается в выпадающем меню. Пункт без ссылки
                            остаётся заголовком группы, если у него есть
                            подпункты.
                        </p>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                            }}
                        >
                            {data.items[key].length === 0 && (
                                <p
                                    style={{
                                        margin: 0,
                                        fontSize: 13,
                                        color: 'var(--color-neutral-500)',
                                    }}
                                >
                                    Пока нет пунктов. Добавьте первый, чтобы он
                                    появился на сайте после сохранения.
                                </p>
                            )}

                            {data.items[key].map((row, i) => (
                                <div
                                    key={row.key}
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 8,
                                    }}
                                >
                                    <MenuItemRow
                                        row={row}
                                        editable={editable}
                                        depth={0}
                                        canMoveUp={i > 0}
                                        canMoveDown={
                                            i < data.items[key].length - 1
                                        }
                                        canIndent={i > 0}
                                        canOutdent={false}
                                        urlError={fieldError(
                                            `items.${key}.${i}.url`,
                                        )}
                                        onMove={(dir) => moveRoot(key, i, dir)}
                                        onIndent={() => indent(key, i)}
                                        onOutdent={() => undefined}
                                        onChange={(patch) =>
                                            updateRoot(key, i, patch)
                                        }
                                        onAddChild={
                                            editable
                                                ? () => addChild(key, i)
                                                : undefined
                                        }
                                        onRemove={() => requestRemove(key, i)}
                                    />

                                    {row.children.map((child, j) => (
                                        <MenuItemRow
                                            key={child.key}
                                            row={child}
                                            editable={editable}
                                            depth={1}
                                            canMoveUp={j > 0}
                                            canMoveDown={
                                                j < row.children.length - 1
                                            }
                                            canIndent={false}
                                            canOutdent
                                            urlError={fieldError(
                                                `items.${key}.${i}.children.${j}.url`,
                                            )}
                                            onMove={(dir) =>
                                                moveChild(key, i, j, dir)
                                            }
                                            onIndent={() => undefined}
                                            onOutdent={() => outdent(key, i, j)}
                                            onChange={(patch) =>
                                                updateChild(key, i, j, patch)
                                            }
                                            onRemove={() =>
                                                requestRemove(key, i, j)
                                            }
                                        />
                                    ))}
                                </div>
                            ))}
                        </div>
                    </Blueprint>
                ))}
            </div>

            {editable && (
                <div className="news-form-actions">
                    <span
                        style={{
                            fontSize: 12.5,
                            color: 'var(--color-neutral-600)',
                        }}
                    >
                        Изменения применяются к навигации публичного сайта после
                        сохранения.
                    </span>
                    <div style={{ flex: 1 }} />
                    <Button
                        variant="primary"
                        icon={<Save size={15} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={save}
                    >
                        Сохранить меню
                    </Button>
                </div>
            )}

            <ConfirmDialog
                open={pendingDelete !== null}
                onClose={() => setPendingDelete(null)}
                title="Удалить пункт вместе с подпунктами?"
                body="Подпункты тоже исчезнут из меню сайта. Это можно отменить, не сохраняя страницу."
                confirmLabel="Удалить группу"
                onConfirm={() => {
                    if (pendingDelete) {
                        removeRoot(pendingDelete.loc, pendingDelete.parent);
                    }

                    setPendingDelete(null);
                }}
            />
        </>
    );
}

function MenuItemRow({
    row,
    editable,
    depth,
    canMoveUp,
    canMoveDown,
    canIndent,
    canOutdent,
    urlError,
    onMove,
    onIndent,
    onOutdent,
    onChange,
    onAddChild,
    onRemove,
}: {
    row: MenuLeaf;
    editable: boolean;
    depth: 0 | 1;
    canMoveUp: boolean;
    canMoveDown: boolean;
    canIndent: boolean;
    canOutdent: boolean;
    urlError?: string;
    onMove: (dir: -1 | 1) => void;
    onIndent: () => void;
    onOutdent: () => void;
    onChange: (patch: Partial<MenuLeaf>) => void;
    onAddChild?: () => void;
    onRemove: () => void;
}) {
    return (
        <div
            className={`cms-menu-row${depth === 1 ? 'is-child' : ''}${row.enabled ? '' : 'is-off'}`}
        >
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(2, 44px)',
                    gap: 4,
                }}
            >
                <IconButton
                    label="Выше"
                    disabled={!editable || !canMoveUp}
                    onClick={() => onMove(-1)}
                >
                    <ArrowUp size={15} strokeWidth={1.75} />
                </IconButton>
                <IconButton
                    label="Ниже"
                    disabled={!editable || !canMoveDown}
                    onClick={() => onMove(1)}
                >
                    <ArrowDown size={15} strokeWidth={1.75} />
                </IconButton>
                <IconButton
                    label="Сделать подпунктом"
                    disabled={!editable || !canIndent}
                    onClick={onIndent}
                >
                    <IndentIncrease size={15} strokeWidth={1.75} />
                </IconButton>
                <IconButton
                    label="Вынести на верхний уровень"
                    disabled={!editable || !canOutdent}
                    onClick={onOutdent}
                >
                    <IndentDecrease size={15} strokeWidth={1.75} />
                </IconButton>
            </div>

            <div style={{ display: 'grid', gap: 6, minWidth: 0 }}>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
                        gap: 6,
                    }}
                >
                    <Input
                        value={row.label.tg}
                        onChange={(e) =>
                            onChange({
                                label: { ...row.label, tg: e.target.value },
                            })
                        }
                        placeholder="ТҶ"
                        aria-label={
                            depth === 1
                                ? 'Подпункт на таджикском'
                                : 'Заголовок на таджикском'
                        }
                        disabled={!editable}
                        style={{ fontSize: 13 }}
                    />
                    <Input
                        value={row.label.ru}
                        onChange={(e) =>
                            onChange({
                                label: { ...row.label, ru: e.target.value },
                            })
                        }
                        placeholder="РУ"
                        aria-label={
                            depth === 1
                                ? 'Подпункт на русском'
                                : 'Заголовок на русском'
                        }
                        disabled={!editable}
                        style={{ fontSize: 13 }}
                    />
                    <Input
                        value={row.label.en}
                        onChange={(e) =>
                            onChange({
                                label: { ...row.label, en: e.target.value },
                            })
                        }
                        placeholder="EN"
                        aria-label={
                            depth === 1
                                ? 'Подпункт на английском'
                                : 'Заголовок на английском'
                        }
                        disabled={!editable}
                        style={{ fontSize: 13 }}
                    />
                </div>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0, 1fr) auto',
                        gap: 8,
                        alignItems: 'center',
                    }}
                >
                    <Input
                        value={row.url}
                        onChange={(e) => onChange({ url: e.target.value })}
                        placeholder={
                            depth === 0 ? '/news или пусто для группы' : '/news'
                        }
                        disabled={!editable}
                        style={{ fontSize: 13 }}
                        className="ui-mono"
                        aria-invalid={urlError ? true : undefined}
                        aria-describedby={
                            urlError ? `${row.key}-url-error` : undefined
                        }
                    />
                    <Checkbox
                        label="Вкл."
                        checked={row.enabled}
                        disabled={!editable}
                        onChange={(e) =>
                            onChange({ enabled: e.target.checked })
                        }
                    />
                </div>
                {urlError && (
                    <span id={`${row.key}-url-error`}>
                        <InputError message={urlError} />
                    </span>
                )}
                {onAddChild && (
                    <div>
                        <Button
                            variant="ghost"
                            size="sm"
                            icon={<ListTree size={14} strokeWidth={1.75} />}
                            onClick={onAddChild}
                        >
                            Подпункт
                        </Button>
                    </div>
                )}
            </div>

            <IconButton
                label="Удалить"
                variant="ghost"
                disabled={!editable}
                onClick={onRemove}
            >
                <X size={14} strokeWidth={1.5} />
            </IconButton>
        </div>
    );
}
