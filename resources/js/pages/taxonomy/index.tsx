import { Head, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Save, X } from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import type { ContentLocale } from '@/lib/domain';
import { Tag as TagPill } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import { Input } from '@/ui/Field';
import { LanguageTabs } from '@/ui/Nav';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };

interface CategoryRow {
    id: number | null;
    name: LocaleMap;
    slug: string;
    usage?: number;
}
interface TagRow {
    id: number | null;
    name: LocaleMap;
    slug: string;
}

interface Props {
    categories: {
        id: number;
        name: LocaleMap;
        slug: string;
        sort: number;
        usage: number;
    }[];
    tags: { id: number; name: LocaleMap; slug: string }[];
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

function toLocaleMap(v?: Partial<LocaleMap> | null): LocaleMap {
    return { ...EMPTY, ...(v ?? {}) };
}

export default function TaxonomyIndex({ categories, tags }: Props) {
    const can = useCan();
    const editable = can('taxonomy.edit');
    const [lang, setLang] = useState<ContentLocale>('ru');

    const form = useForm({
        categories: categories.map((c) => ({
            id: c.id,
            name: toLocaleMap(c.name),
            slug: c.slug,
            usage: c.usage,
        })) as CategoryRow[],
        tags: tags.map((t) => ({
            id: t.id,
            name: toLocaleMap(t.name),
            slug: t.slug,
        })) as TagRow[],
    });
    const { data, setData, processing, errors } = form;

    const errorMap = errors as Record<string, string | undefined>;
    const hasErrors = Object.keys(errorMap).length > 0;
    const catError = (i: number): string | undefined =>
        errorMap[`categories.${i}.name.ru`];
    const tagError = (i: number): string | undefined =>
        errorMap[`tags.${i}.name.ru`];

    const rowsAll = [...data.categories, ...data.tags];
    const pct = (l: ContentLocale): number =>
        rowsAll.length === 0
            ? 0
            : Math.round(
                  (rowsAll.filter((r) => r.name[l].trim() !== '').length /
                      rowsAll.length) *
                      100,
              );
    const completeness = { ru: pct('ru'), tg: pct('tg'), en: pct('en') };

    // --- categories ---
    const setCats = (next: CategoryRow[]) => setData('categories', next);
    const addCat = () =>
        setCats([
            ...data.categories,
            { id: null, name: { ...EMPTY }, slug: '' },
        ]);
    const updateCat = (i: number, patch: Partial<CategoryRow>) =>
        setCats(data.categories.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));
    const removeCat = (i: number) =>
        setCats(data.categories.filter((_, idx) => idx !== i));
    const moveCat = (i: number, dir: -1 | 1) => {
        const j = i + dir;

        if (j < 0 || j >= data.categories.length) {
            return;
        }

        const next = [...data.categories];
        [next[i], next[j]] = [next[j], next[i]];
        setCats(next);
    };

    // --- tags ---
    const setTags = (next: TagRow[]) => setData('tags', next);
    const addTag = () =>
        setTags([...data.tags, { id: null, name: { ...EMPTY }, slug: '' }]);
    const updateTag = (i: number, patch: Partial<TagRow>) =>
        setTags(data.tags.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));
    const removeTag = (i: number) =>
        setTags(data.tags.filter((_, idx) => idx !== i));

    const save = () =>
        form.put('/taxonomy', {
            preserveScroll: true,
            onError: (errs) => {
                // The required field is the Russian name — surface its tab.
                if (Object.keys(errs).some((k) => k.endsWith('.name.ru'))) {
                    setLang('ru');
                }
            },
        });

    return (
        <>
            <Head title="Категории и теги" />
            <PageHeader
                title="Категории и теги"
                subtitle="Рубрики новостей и словарь тегов · пустой адрес заполняется автоматически"
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

            <div style={{ marginBottom: 14 }}>
                <LanguageTabs
                    active={lang}
                    onChange={setLang}
                    completeness={completeness}
                />
            </div>

            {hasErrors && (
                <div
                    role="alert"
                    style={{
                        marginBottom: 14,
                        padding: '10px 14px',
                        borderRadius: 8,
                        fontSize: 13,
                        color: 'var(--color-danger-700, #b42318)',
                        background: 'var(--color-danger-50, #fef3f2)',
                        border: '1px solid var(--color-danger-200, #fecdca)',
                    }}
                >
                    Изменения не сохранены: у некоторых записей не заполнено
                    русское название. Заполните русское название на вкладке «РУ»
                    или удалите пустые строки.
                </div>
            )}

            {/* Категории новостей */}
            <Blueprint style={{ padding: 20, marginBottom: 16 }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        marginBottom: 12,
                    }}
                >
                    <h3 className="ui-card-title" style={{ margin: 0 }}>
                        Категории новостей
                    </h3>
                    {editable && (
                        <Button
                            variant="ghost"
                            size="sm"
                            icon={<Plus size={14} strokeWidth={2} />}
                            onClick={addCat}
                        >
                            Категория
                        </Button>
                    )}
                </div>

                <div
                    style={{ display: 'flex', flexDirection: 'column', gap: 8 }}
                >
                    {data.categories.map((row, i) => (
                        <div
                            key={row.id ?? `new-${i}`}
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    '26px 1fr 1fr 96px 28px',
                                gap: 6,
                                alignItems: 'center',
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
                                    onClick={() => moveCat(i, -1)}
                                    style={{ minHeight: 18, padding: 1 }}
                                >
                                    <ArrowUp size={13} strokeWidth={1.75} />
                                </button>
                                <button
                                    type="button"
                                    className="ui-btn ui-btn-icon ui-btn-ghost"
                                    aria-label="Ниже"
                                    disabled={
                                        i === data.categories.length - 1 ||
                                        !editable
                                    }
                                    onClick={() => moveCat(i, 1)}
                                    style={{ minHeight: 18, padding: 1 }}
                                >
                                    <ArrowDown size={13} strokeWidth={1.75} />
                                </button>
                            </div>
                            <Input
                                value={row.name[lang]}
                                onChange={(e) =>
                                    updateCat(i, {
                                        name: {
                                            ...row.name,
                                            [lang]: e.target.value,
                                        },
                                    })
                                }
                                placeholder="Название"
                                disabled={!editable}
                                hasError={lang === 'ru' && !!catError(i)}
                                style={{ fontSize: 13 }}
                            />
                            <Input
                                value={row.slug}
                                onChange={(e) =>
                                    updateCat(i, { slug: e.target.value })
                                }
                                placeholder="auto"
                                disabled={!editable}
                                className="ui-mono"
                                style={{ fontSize: 13 }}
                            />
                            <span
                                style={{
                                    fontSize: 11.5,
                                    color: 'var(--color-neutral-500)',
                                    textAlign: 'center',
                                }}
                                title="Новостей в категории"
                            >
                                {row.usage ?? 0} нов.
                            </span>
                            <IconButton
                                label="Удалить"
                                variant="ghost"
                                disabled={!editable}
                                onClick={() => removeCat(i)}
                            >
                                <X size={14} strokeWidth={1.5} />
                            </IconButton>
                        </div>
                    ))}
                    {data.categories.length === 0 && (
                        <p
                            style={{
                                margin: 0,
                                fontSize: 12.5,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            Категорий пока нет.
                        </p>
                    )}
                </div>
            </Blueprint>

            {/* Теги */}
            <Blueprint style={{ padding: 20 }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        marginBottom: 12,
                    }}
                >
                    <h3 className="ui-card-title" style={{ margin: 0 }}>
                        Теги
                    </h3>
                    {editable && (
                        <Button
                            variant="ghost"
                            size="sm"
                            icon={<Plus size={14} strokeWidth={2} />}
                            onClick={addTag}
                        >
                            Тег
                        </Button>
                    )}
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(2, 1fr)',
                        gap: 8,
                    }}
                >
                    {data.tags.map((row, i) => (
                        <div
                            key={row.id ?? `new-${i}`}
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr 28px',
                                gap: 6,
                                alignItems: 'center',
                            }}
                        >
                            <Input
                                value={row.name[lang]}
                                onChange={(e) =>
                                    updateTag(i, {
                                        name: {
                                            ...row.name,
                                            [lang]: e.target.value,
                                        },
                                    })
                                }
                                placeholder="Тег"
                                disabled={!editable}
                                hasError={lang === 'ru' && !!tagError(i)}
                                style={{ fontSize: 13 }}
                            />
                            <Input
                                value={row.slug}
                                onChange={(e) =>
                                    updateTag(i, { slug: e.target.value })
                                }
                                placeholder="auto"
                                disabled={!editable}
                                className="ui-mono"
                                style={{ fontSize: 13 }}
                            />
                            <IconButton
                                label="Удалить"
                                variant="ghost"
                                disabled={!editable}
                                onClick={() => removeTag(i)}
                            >
                                <X size={14} strokeWidth={1.5} />
                            </IconButton>
                        </div>
                    ))}
                    {data.tags.length === 0 && (
                        <p
                            style={{
                                margin: 0,
                                fontSize: 12.5,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            Тегов пока нет.
                        </p>
                    )}
                </div>
            </Blueprint>

            {editable && (
                <div className="news-form-actions">
                    <TagPill tone="neutral">
                        {data.categories.length} категорий · {data.tags.length}{' '}
                        тегов
                    </TagPill>
                    <div style={{ flex: 1 }} />
                    <Button
                        variant="primary"
                        icon={<Save size={15} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={save}
                    >
                        Сохранить
                    </Button>
                </div>
            )}
        </>
    );
}
