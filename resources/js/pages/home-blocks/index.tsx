import { Head, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, GripVertical, Save } from 'lucide-react';
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
        })),
    });
    const { data, setData, processing } = form;

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

    const save = () => form.put('/home-blocks', { preserveScroll: true });

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
                            <div style={{ display: 'flex', gap: 8 }}>
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
                                    placeholder="Заголовок (рус.)"
                                    disabled={!editable}
                                    style={{ fontSize: 13 }}
                                />
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
                                    disabled={!editable}
                                    style={{ fontSize: 13, maxWidth: 160 }}
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
