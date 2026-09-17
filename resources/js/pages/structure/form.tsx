import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';
import StructureUnitController from '@/actions/App/Http/Controllers/Cms/StructureUnitController';
import type { ContentLocale } from '@/lib/domain';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Field, Input, Select, Textarea } from '@/ui/Field';
import { LanguageTabs } from '@/ui/Nav';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };

interface StructureUnitData {
    id: number;
    parent_id: number | null;
    num: string;
    name: LocaleMap;
    desc: LocaleMap;
    sort: number;
}

interface ParentOption {
    value: number;
    label: string;
}

interface Props {
    unit: StructureUnitData | null;
    /** Units this one may be placed under — never itself or its own subunits. */
    parents: ParentOption[];
    /** Set when a subunit is added from its parent's row in the list. */
    defaultParentId: number | null;
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

function toLocaleMap(value?: Partial<LocaleMap> | null): LocaleMap {
    return { ...EMPTY, ...(value ?? {}) };
}

export default function StructureForm({
    unit,
    parents,
    defaultParentId,
}: Props) {
    const isEdit = !!unit;
    const [lang, setLang] = useState<ContentLocale>('ru');

    const form = useForm({
        parent_id: (unit?.parent_id ?? defaultParentId ?? '') as number | '',
        num: unit?.num ?? '',
        name: toLocaleMap(unit?.name),
        desc: toLocaleMap(unit?.desc),
        sort: unit?.sort ?? 0,
    });
    const { data, setData, processing, errors } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const setLocale = (field: 'name' | 'desc', value: string) =>
        setData(field, { ...data[field], [lang]: value });

    const completeness: Partial<Record<ContentLocale, number>> = {
        ru: data.name.ru.trim() && data.desc.ru.trim() ? 100 : 0,
        tg: data.name.tg.trim() && data.desc.tg.trim() ? 100 : 0,
        en: data.name.en.trim() && data.desc.en.trim() ? 100 : 0,
    };

    const submit = () => {
        if (isEdit && unit) {
            form.put(StructureUnitController.update.url(unit.id), {
                preserveScroll: true,
            });
        } else {
            form.post(StructureUnitController.store.url(), {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head
                title={
                    isEdit
                        ? 'Редактирование подразделения'
                        : 'Новое подразделение'
                }
            />

            <PageHeader
                eyebrow={
                    <Link
                        href={StructureUnitController.index.url()}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Структура
                    </Link>
                }
                title={
                    isEdit
                        ? unit!.name.ru || 'Подразделение'
                        : 'Новое подразделение'
                }
                subtitle="Место в структуре, номер, название и описание для страницы «Структура»."
                actions={
                    <Button
                        variant="primary"
                        icon={<Save size={16} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={submit}
                    >
                        Сохранить
                    </Button>
                }
            />

            <div style={{ marginBottom: 14 }}>
                <LanguageTabs
                    active={lang}
                    onChange={setLang}
                    completeness={completeness}
                />
            </div>

            <Blueprint style={{ padding: 20, maxWidth: 560 }}>
                <h3 className="ui-card-title" style={{ marginTop: 0 }}>
                    Основные данные
                </h3>
                <Field
                    label="Вышестоящее подразделение"
                    htmlFor="structure-parent"
                    hint="Оставьте пустым для подразделения верхнего уровня."
                    error={fieldError('parent_id')}
                >
                    <Select
                        id="structure-parent"
                        value={String(data.parent_id)}
                        onChange={(e) =>
                            setData(
                                'parent_id',
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                        hasError={!!fieldError('parent_id')}
                        placeholder="— Верхний уровень —"
                        options={parents}
                    />
                </Field>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '100px 1fr',
                        gap: 12,
                    }}
                >
                    <Field label="Номер" error={fieldError('num')} required>
                        <Input
                            value={data.num}
                            onChange={(e) => setData('num', e.target.value)}
                            placeholder="01"
                            className="ui-mono"
                        />
                    </Field>
                    <Field label="Порядок">
                        <Input
                            type="number"
                            min={0}
                            value={String(data.sort)}
                            onChange={(e) =>
                                setData('sort', Number(e.target.value) || 0)
                            }
                        />
                    </Field>
                </div>
                <Field
                    label={`Название (${lang.toUpperCase()})`}
                    error={fieldError('name.ru')}
                    required={lang === 'ru'}
                >
                    <Input
                        value={data.name[lang]}
                        onChange={(e) => setLocale('name', e.target.value)}
                        placeholder="Служба спасения"
                    />
                </Field>
                <Field
                    label={`Описание (${lang.toUpperCase()})`}
                    error={fieldError('desc.ru')}
                    required={lang === 'ru'}
                >
                    <Textarea
                        value={data.desc[lang]}
                        onChange={(e) => setLocale('desc', e.target.value)}
                        rows={3}
                        placeholder="Аэромобильный отряд, кинологические расчёты, водолазная и горная службы"
                    />
                </Field>
            </Blueprint>

            <div className="news-form-actions">
                <div style={{ flex: 1 }} />
                <Button
                    variant="primary"
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={submit}
                >
                    Сохранить подразделение
                </Button>
            </div>
        </>
    );
}
