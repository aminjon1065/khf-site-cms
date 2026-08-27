import { Head, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, GripVertical, Save, Trash2 } from 'lucide-react';
import HomeBlockController from '@/actions/App/Http/Controllers/Cms/HomeBlockController';
import { useCan } from '@/lib/auth';
import { Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Checkbox, Field, Input } from '@/ui/Field';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };

interface BlockRow {
    id: number;
    type: string;
    type_label: string;
    title: LocaleMap;
    enabled: boolean;
    supports_limit: boolean;
    limit: number | null;
    supports_items: boolean;
    items: IndicatorItem[];
}

/** Показатель ведомства: число и подпись к нему по локалям. */
interface IndicatorItem {
    value: string;
    label: LocaleMap;
}

interface Props {
    blocks: BlockRow[];
}

export default function HomeBlocksIndex({ blocks }: Props) {
    const can = useCan();
    const editable = can('home.edit');

    const form = useForm({
        blocks: blocks.map((b) => ({
            id: b.id,
            type: b.type,
            type_label: b.type_label,
            title: {
                ru: b.title.ru ?? '',
                tg: b.title.tg ?? '',
                en: b.title.en ?? '',
            } as LocaleMap,
            enabled: b.enabled,
            supports_limit: b.supports_limit,
            limit: b.limit as number | null,
            supports_items: b.supports_items,
            items: (b.items ?? []).map((it) => ({
                value: it.value ?? '',
                label: {
                    ru: it.label?.ru ?? '',
                    tg: it.label?.tg ?? '',
                    en: it.label?.en ?? '',
                } as LocaleMap,
            })),
        })),
    });
    const { data, setData, processing } = form;

    const updateItem = (
        blockIndex: number,
        itemIndex: number,
        patch: Partial<IndicatorItem>,
    ) =>
        update(blockIndex, {
            items: data.blocks[blockIndex].items.map((item, k) =>
                k === itemIndex ? { ...item, ...patch } : item,
            ),
        });

    const update = (i: number, patch: Partial<(typeof data.blocks)[number]>) =>
        setData(
            'blocks',
            data.blocks.map((b, idx) => (idx === i ? { ...b, ...patch } : b)),
        );

    const move = (i: number, dir: -1 | 1) => {
        const j = i + dir;

        if (j < 0 || j >= data.blocks.length) {
            return;
        }

        const next = [...data.blocks];
        [next[i], next[j]] = [next[j], next[i]];
        setData('blocks', next);
    };

    const save = () =>
        form.put(HomeBlockController.update.url(), { preserveScroll: true });

    return (
        <>
            <Head title="Главная страница" />
            <PageHeader
                title="Главная страница"
                subtitle="Блоки главной страницы сайта · порядок, видимость и количество материалов"
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

            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {data.blocks.map((block, i) => (
                    <Blueprint
                        key={block.id}
                        style={{
                            padding: 16,
                            display: 'grid',
                            gridTemplateColumns: '34px 1fr 220px 110px 90px',
                            gap: 14,
                            alignItems: 'center',
                            opacity: block.enabled ? 1 : 0.6,
                        }}
                    >
                        {/* order controls */}
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: 2,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            <button
                                type="button"
                                className="ui-btn ui-btn-icon ui-btn-ghost"
                                aria-label="Выше"
                                disabled={i === 0 || !editable}
                                onClick={() => move(i, -1)}
                                style={{ minHeight: 22, padding: 2 }}
                            >
                                <ArrowUp size={14} strokeWidth={1.75} />
                            </button>
                            <GripVertical
                                size={13}
                                strokeWidth={1.5}
                                style={{ color: 'var(--color-neutral-300)' }}
                            />
                            <button
                                type="button"
                                className="ui-btn ui-btn-icon ui-btn-ghost"
                                aria-label="Ниже"
                                disabled={
                                    i === data.blocks.length - 1 || !editable
                                }
                                onClick={() => move(i, 1)}
                                style={{ minHeight: 22, padding: 2 }}
                            >
                                <ArrowDown size={14} strokeWidth={1.75} />
                            </button>
                        </div>

                        {/* type + titles */}
                        <div style={{ minWidth: 0 }}>
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    marginBottom: 6,
                                }}
                            >
                                <span
                                    style={{
                                        fontFamily: 'var(--font-heading)',
                                        fontWeight: 600,
                                        fontSize: 14,
                                    }}
                                >
                                    {block.type_label}
                                </span>
                                {!block.enabled && (
                                    <Tag tone="neutral">скрыт</Tag>
                                )}
                            </div>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns:
                                        'repeat(3, minmax(0, 1fr))',
                                    gap: 8,
                                }}
                            >
                                <Input
                                    value={block.title.tg}
                                    onChange={(e) =>
                                        update(i, {
                                            title: {
                                                ...block.title,
                                                tg: e.target.value,
                                            },
                                        })
                                    }
                                    placeholder="ТҶ"
                                    aria-label="Заголовок блока на таджикском"
                                    disabled={!editable}
                                    style={{ fontSize: 13 }}
                                />
                                <Input
                                    value={block.title.ru}
                                    onChange={(e) =>
                                        update(i, {
                                            title: {
                                                ...block.title,
                                                ru: e.target.value,
                                            },
                                        })
                                    }
                                    placeholder="РУ"
                                    aria-label="Заголовок блока на русском"
                                    disabled={!editable}
                                    style={{ fontSize: 13 }}
                                />
                                <Input
                                    value={block.title.en}
                                    onChange={(e) =>
                                        update(i, {
                                            title: {
                                                ...block.title,
                                                en: e.target.value,
                                            },
                                        })
                                    }
                                    placeholder="EN"
                                    aria-label="Заголовок блока на английском"
                                    disabled={!editable}
                                    style={{ fontSize: 13 }}
                                />
                            </div>
                        </div>

                        {/* limit */}
                        <div>
                            {block.supports_limit ? (
                                <Field label="Материалов" className="m-0">
                                    <Input
                                        type="number"
                                        min={1}
                                        max={20}
                                        value={
                                            block.limit === null
                                                ? ''
                                                : String(block.limit)
                                        }
                                        onChange={(e) =>
                                            update(i, {
                                                limit:
                                                    e.target.value === ''
                                                        ? null
                                                        : Number(
                                                              e.target.value,
                                                          ),
                                            })
                                        }
                                        disabled={!editable}
                                        style={{ maxWidth: 90 }}
                                    />
                                </Field>
                            ) : (
                                <span
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--color-neutral-400)',
                                    }}
                                >
                                    —
                                </span>
                            )}
                        </div>

                        {/* enabled */}
                        <Checkbox
                            label="Показывать"
                            checked={block.enabled}
                            disabled={!editable}
                            onChange={(e) =>
                                update(i, { enabled: e.target.checked })
                            }
                        />

                        {/* position */}
                        <span
                            className="ui-mono"
                            style={{
                                fontSize: 12,
                                color: 'var(--color-neutral-500)',
                                textAlign: 'right',
                            }}
                        >
                            №{i + 1}
                        </span>

                        {/* Показатели ведомства. Считать их система не может:
                            «спасательных операций» и «человек спасено» нет ни
                            в одной таблице — цифры приходят из отчётности,
                            поэтому их вводит редактор. */}
                        {block.supports_items && (
                            <div style={{ gridColumn: '1 / -1' }}>
                                <div className="flex flex-col gap-2">
                                    {block.items.map((item, k) => (
                                        <div
                                            key={k}
                                            className="flex items-start gap-2"
                                        >
                                            <Input
                                                value={item.value}
                                                aria-label="Значение"
                                                placeholder="86 500"
                                                disabled={!editable}
                                                style={{ maxWidth: 110 }}
                                                onChange={(e) =>
                                                    updateItem(i, k, {
                                                        value: e.target.value,
                                                    })
                                                }
                                            />
                                            {(['ru', 'tg', 'en'] as const).map(
                                                (loc) => (
                                                    <Input
                                                        key={loc}
                                                        value={
                                                            item.label[loc] ??
                                                            ''
                                                        }
                                                        aria-label={`Подпись · ${loc.toUpperCase()}`}
                                                        placeholder={`Подпись · ${loc.toUpperCase()}`}
                                                        disabled={!editable}
                                                        onChange={(e) =>
                                                            updateItem(i, k, {
                                                                label: {
                                                                    ...item.label,
                                                                    [loc]: e
                                                                        .target
                                                                        .value,
                                                                },
                                                            })
                                                        }
                                                    />
                                                ),
                                            )}
                                            <button
                                                type="button"
                                                className="btn btn-icon"
                                                aria-label="Удалить показатель"
                                                disabled={!editable}
                                                onClick={() =>
                                                    update(i, {
                                                        items: block.items.filter(
                                                            (_, x) => x !== k,
                                                        ),
                                                    })
                                                }
                                            >
                                                <Trash2
                                                    size={15}
                                                    strokeWidth={1.5}
                                                />
                                            </button>
                                        </div>
                                    ))}
                                    {editable && (
                                        <div>
                                            <button
                                                type="button"
                                                className="btn"
                                                onClick={() =>
                                                    update(i, {
                                                        items: [
                                                            ...block.items,
                                                            {
                                                                value: '',
                                                                label: {
                                                                    ru: '',
                                                                    tg: '',
                                                                    en: '',
                                                                } as LocaleMap,
                                                            },
                                                        ],
                                                    })
                                                }
                                            >
                                                Добавить показатель
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
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
                        Изменения применяются к публичной главной странице после
                        сохранения.
                    </span>
                    <div style={{ flex: 1 }} />
                    <Button
                        variant="primary"
                        icon={<Save size={15} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={save}
                    >
                        Сохранить порядок и настройки
                    </Button>
                </div>
            )}
        </>
    );
}
