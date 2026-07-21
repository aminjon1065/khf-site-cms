import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, FileText, Save, Send } from 'lucide-react';
import { useState } from 'react';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { StatusBadge } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, LinkButton } from '@/ui/Button';
import { Checkbox, DatePicker, Field, Input, Select } from '@/ui/Field';
import { LanguageTabs } from '@/ui/Nav';
import { Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };
type PublishMode = 'now' | 'review';
type FileLocale = 'tg' | 'ru' | 'en';

interface Option {
    value: string;
    label: string;
}

interface FileInfo {
    name: string;
    url: string;
}

interface DocumentData {
    id: number;
    name: LocaleMap;
    doc_type: string;
    number: string | null;
    doc_date: string | null;
    section: string | null;
    status: ContentStatus;
    files: Record<FileLocale, FileInfo | null>;
    published_at: string | null;
}

interface Props {
    document: DocumentData | null;
    reference: {
        types: Option[];
        sections: string[];
        authors: Option[];
    };
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };
const FILE_LOCALES: { key: FileLocale; label: string }[] = [
    { key: 'tg', label: 'Таджикский (ТҶ)' },
    { key: 'ru', label: 'Русский (РУ)' },
    { key: 'en', label: 'Английский (EN)' },
];

export default function DocumentForm({ document, reference }: Props) {
    const can = useCan();
    const isEdit = !!document;
    const [lang, setLang] = useState<ContentLocale>('ru');

    const form = useForm({
        name: { ...EMPTY, ...document?.name } as LocaleMap,
        doc_type: (document?.doc_type ?? '') as string,
        number: document?.number ?? '',
        doc_date: document?.doc_date ?? '',
        section: document?.section ?? '',
        file_tg: null as File | null,
        file_ru: null as File | null,
        file_en: null as File | null,
        file_tg_remove: false,
        file_ru_remove: false,
        file_en_remove: false,
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const compAll = {
        tg: data.name.tg.trim() !== '' ? 100 : 0,
        ru: data.name.ru.trim() !== '' ? 100 : 0,
        en: data.name.en.trim() !== '' ? 100 : 0,
    };

    const submit = (action: 'draft' | 'submit', mode?: PublishMode) => {
        form.transform((d) => ({
            ...d,
            action,
            publish_mode: mode ?? d.publish_mode,
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        form.post(isEdit ? `/documents/${document!.id}` : '/documents', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head
                title={isEdit ? 'Редактирование документа' : 'Новый документ'}
            />

            <PageHeader
                eyebrow={
                    <Link
                        href="/documents"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Документы
                    </Link>
                }
                title={isEdit ? 'Редактирование документа' : 'Новый документ'}
                subtitle="Укажите название и реквизиты, прикрепите файлы на нужных языках."
                actions={
                    isEdit && document ? (
                        <StatusBadge status={document.status} />
                    ) : null
                }
            />

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1.7fr 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                {/* ------------------------------------------------ main */}
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16,
                    }}
                >
                    <Blueprint style={{ padding: 20 }}>
                        <div
                            style={{
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                                marginBottom: 14,
                                flexWrap: 'wrap',
                                gap: 10,
                            }}
                        >
                            <h3 className="ui-card-title" style={{ margin: 0 }}>
                                Название
                            </h3>
                            <LanguageTabs
                                active={lang}
                                onChange={setLang}
                                completeness={compAll}
                            />
                        </div>

                        <Field
                            label="Название документа"
                            required={lang === 'ru'}
                            error={
                                lang === 'ru'
                                    ? fieldError('name.ru')
                                    : undefined
                            }
                        >
                            <Input
                                value={data.name[lang]}
                                onChange={(e) =>
                                    setData('name', {
                                        ...data.name,
                                        [lang]: e.target.value,
                                    })
                                }
                                hasError={
                                    lang === 'ru' && !!fieldError('name.ru')
                                }
                                placeholder={
                                    lang === 'ru'
                                        ? 'Например: Закон РТ «О…»'
                                        : 'Перевод названия'
                                }
                                maxLength={255}
                            />
                        </Field>
                    </Blueprint>

                    {/* ------------------------------------ files uploader */}
                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 4 }}
                        >
                            Файлы по языкам
                        </h3>
                        <p
                            style={{
                                margin: '0 0 14px',
                                fontSize: 12.5,
                                color: 'var(--color-neutral-600)',
                            }}
                        >
                            PDF, DOC(X), XLS(X), PPT(X) · до 20 МБ на файл.
                        </p>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 16,
                            }}
                        >
                            {FILE_LOCALES.map(({ key, label }) => {
                                const existing = document?.files?.[key] ?? null;
                                const removeKey = `file_${key}_remove` as const;
                                const fileKey = `file_${key}` as const;

                                return (
                                    <div key={key}>
                                        <Field
                                            label={label}
                                            error={fieldError(`file_${key}`)}
                                        >
                                            {existing && !data[removeKey] && (
                                                <div
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 8,
                                                        marginBottom: 6,
                                                        fontSize: 13,
                                                    }}
                                                >
                                                    <FileText
                                                        size={15}
                                                        strokeWidth={1.5}
                                                        style={{
                                                            color: 'var(--color-accent-600)',
                                                        }}
                                                    />
                                                    <a
                                                        href={existing.url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        style={{
                                                            color: 'var(--color-accent-700)',
                                                        }}
                                                    >
                                                        {existing.name}
                                                    </a>
                                                </div>
                                            )}
                                            <input
                                                type="file"
                                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                                onChange={(e) =>
                                                    setData(
                                                        fileKey,
                                                        e.target.files?.[0] ??
                                                            null,
                                                    )
                                                }
                                                style={{ fontSize: 13 }}
                                            />
                                        </Field>
                                        {existing && (
                                            <Checkbox
                                                label="Удалить текущий файл"
                                                checked={data[removeKey]}
                                                onChange={(e) =>
                                                    setData(
                                                        removeKey,
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </Blueprint>
                </div>

                {/* --------------------------------------------- sidebar */}
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16,
                    }}
                >
                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            Реквизиты
                        </h3>

                        <Field
                            label="Тип документа"
                            required
                            error={fieldError('doc_type')}
                        >
                            <Select
                                value={data.doc_type}
                                onChange={(e) =>
                                    setData('doc_type', e.target.value)
                                }
                                hasError={!!fieldError('doc_type')}
                                placeholder="Выберите тип"
                                options={reference.types}
                            />
                        </Field>

                        <Field label="Номер" error={fieldError('number')}>
                            <Input
                                value={data.number}
                                onChange={(e) =>
                                    setData('number', e.target.value)
                                }
                                placeholder="Например: № 1432"
                                maxLength={100}
                            />
                        </Field>

                        <Field
                            label="Дата документа"
                            error={fieldError('doc_date')}
                        >
                            <DatePicker
                                value={data.doc_date}
                                onChange={(e) =>
                                    setData('doc_date', e.target.value)
                                }
                            />
                        </Field>

                        <Field
                            label="Раздел"
                            hint="Группировка в каталоге (напр. «Законодательство»)."
                            error={fieldError('section')}
                        >
                            <Input
                                value={data.section}
                                onChange={(e) =>
                                    setData('section', e.target.value)
                                }
                                list="doc-sections"
                                maxLength={255}
                            />
                            <datalist id="doc-sections">
                                {reference.sections.map((s) => (
                                    <option key={s} value={s} />
                                ))}
                            </datalist>
                        </Field>
                    </Blueprint>
                </div>
            </div>

            {/* --------------------------------------------- sticky actions */}
            <div className="news-form-actions">
                <LinkButton href="/documents" variant="ghost">
                    Отмена
                </LinkButton>
                <div style={{ flex: 1 }} />
                <Button
                    variant="secondary"
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={() => submit('draft')}
                >
                    Сохранить черновик
                </Button>
                {can('documents.publish') ? (
                    <Dropdown
                        align="right"
                        trigger={({ toggle }) => (
                            <Button
                                variant="primary"
                                iconRight={
                                    <ChevronDown size={15} strokeWidth={2} />
                                }
                                onClick={toggle}
                            >
                                Опубликовать
                            </Button>
                        )}
                        items={[
                            {
                                label: 'Опубликовать сейчас',
                                onSelect: () => submit('submit', 'now'),
                            },
                            { separator: true },
                            {
                                label: 'Отправить на согласование',
                                onSelect: () => submit('submit', 'review'),
                            },
                        ]}
                    />
                ) : (
                    <Button
                        variant="primary"
                        icon={<Send size={15} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={() => submit('submit', 'review')}
                    >
                        На согласование
                    </Button>
                )}
            </div>
        </>
    );
}
