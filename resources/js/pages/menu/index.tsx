import { Head, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Save, X } from 'lucide-react';
import { useCan } from '@/lib/auth';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import { Checkbox, Input } from '@/ui/Field';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };
type Location = 'main' | 'footer';

interface MenuRow {
    id: number | null;
    label: LocaleMap;
    url: string;
    enabled: boolean;
}

interface Props {
    menus: Record<Location, { id: number; label: LocaleMap; url: string | null; enabled: boolean }[]>;
}

const LOCATIONS: { key: Location; label: string; hint: string }[] = [
    { key: 'main', label: 'Главное меню', hint: 'Верхняя навигация сайта' },
    { key: 'footer', label: 'Меню подвала', hint: 'Раздел «Разделы» в подвале' },
];

function toRow(m: {
    id: number;
    label: LocaleMap;
    url: string | null;
    enabled: boolean;
}): MenuRow {
    return {
        id: m.id,
        label: {
            ru: m.label.ru ?? '',
            tg: m.label.tg ?? '',
            en: m.label.en ?? '',
        },
        url: m.url ?? '',
        enabled: m.enabled,
    };
}

export default function MenuIndex({ menus }: Props) {
    const can = useCan();
    const editable = can('settings.edit');

    const form = useForm({
        items: {
            main: menus.main.map(toRow),
            footer: menus.footer.map(toRow),
        } as Record<Location, MenuRow[]>,
    });
    const { data, setData, processing } = form;

    const setItems = (loc: Location, next: MenuRow[]) =>
        setData('items', { ...data.items, [loc]: next });

    const update = (loc: Location, i: number, patch: Partial<MenuRow>) =>
        setItems(
            loc,
            data.items[loc].map((r, idx) => (idx === i ? { ...r, ...patch } : r)),
        );

    const add = (loc: Location) =>
        setItems(loc, [
            ...data.items[loc],
            { id: null, label: { ru: '', tg: '', en: '' }, url: '', enabled: true },
        ]);

    const remove = (loc: Location, i: number) =>
        setItems(loc, data.items[loc].filter((_, idx) => idx !== i));

    const move = (loc: Location, i: number, dir: -1 | 1) => {
        const j = i + dir;

        if (j < 0 || j >= data.items[loc].length) {
            return;
        }

        const next = [...data.items[loc]];
        [next[i], next[j]] = [next[j], next[i]];
        setItems(loc, next);
    };

    const save = () => form.put('/menu', { preserveScroll: true });

    return (
        <>
            <Head title="Меню сайта" />
            <PageHeader
                title="Меню сайта"
                subtitle="Пункты навигации публичного сайта · заголовок, ссылка, порядок и видимость"
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

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                {LOCATIONS.map(({ key, label, hint }) => (
                    <Blueprint key={key} style={{ padding: 20 }}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 4,
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
                            {hint}
                        </p>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                            }}
                        >
                            {data.items[key].map((row, i) => (
                                <div
                                    key={row.id ?? `new-${i}`}
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '26px 1fr 1fr 90px 28px',
                                        gap: 6,
                                        alignItems: 'center',
                                        opacity: row.enabled ? 1 : 0.55,
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                        }}
                                    >
                                        <button
                                            type="button"
                                            className="ui-btn ui-btn-icon ui-btn-ghost"
                                            aria-label="Выше"
                                            disabled={i === 0 || !editable}
                                            onClick={() => move(key, i, -1)}
                                            style={{ minHeight: 18, padding: 1 }}
                                        >
                                            <ArrowUp size={13} strokeWidth={1.75} />
                                        </button>
                                        <button
                                            type="button"
                                            className="ui-btn ui-btn-icon ui-btn-ghost"
                                            aria-label="Ниже"
                                            disabled={
                                                i ===
                                                    data.items[key].length - 1 ||
                                                !editable
                                            }
                                            onClick={() => move(key, i, 1)}
                                            style={{ minHeight: 18, padding: 1 }}
                                        >
                                            <ArrowDown
                                                size={13}
                                                strokeWidth={1.75}
                                            />
                                        </button>
                                    </div>
                                    <Input
                                        value={row.label.ru}
                                        onChange={(e) =>
                                            update(key, i, {
                                                label: {
                                                    ...row.label,
                                                    ru: e.target.value,
                                                },
                                            })
                                        }
                                        placeholder="Заголовок"
                                        disabled={!editable}
                                        style={{ fontSize: 13 }}
                                    />
                                    <Input
                                        value={row.url}
                                        onChange={(e) =>
                                            update(key, i, {
                                                url: e.target.value,
                                            })
                                        }
                                        placeholder="/news"
                                        disabled={!editable}
                                        style={{ fontSize: 13 }}
                                        className="ui-mono"
                                    />
                                    <Checkbox
                                        label="Вкл."
                                        checked={row.enabled}
                                        disabled={!editable}
                                        onChange={(e) =>
                                            update(key, i, {
                                                enabled: e.target.checked,
                                            })
                                        }
                                    />
                                    <IconButton
                                        label="Удалить"
                                        variant="ghost"
                                        disabled={!editable}
                                        onClick={() => remove(key, i)}
                                    >
                                        <X size={14} strokeWidth={1.5} />
                                    </IconButton>
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
        </>
    );
}
