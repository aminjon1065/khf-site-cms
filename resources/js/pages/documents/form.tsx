import { useForm } from '@inertiajs/react';
import { FileStack, FileText, Sliders, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EditorCanvasBlock } from '@/cms/EditorCanvasBlock';
import { EditorialFormShell } from '@/cms/EditorialFormShell';
import type { PendingChangeInfo } from '@/cms/EditorialFormShell';
import { EditorInspector, InspectorSection } from '@/cms/EditorInspector';
import { useInspectorOpen } from '@/hooks/use-inspector-open';
import { useUploadLimits } from '@/hooks/use-upload-limits';
import { useCan } from '@/lib/auth';
import { localeShort, MEDIA_LOCKED_NOTE } from '@/lib/domain';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { languageChecks } from '@/lib/publication-languages';
import { acceptOf, uploadProblem } from '@/lib/uploads';
import { index, store, unpublish, update } from '@/routes/documents';
import { Button } from '@/ui/Button';
import { Checkbox, DatePicker, Field, Input, Select } from '@/ui/Field';
import { ReadinessWidget } from '@/ui/ReadinessWidget';

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
    updated_at: string;
    preview_url: string;
}

interface Props {
    document: DocumentData | null;
    reference: {
        types: Option[];
        sections: string[];
    };
    /** A proposal waiting for approval on this live material. */
    pending_change?: PendingChangeInfo | null;
    /** Saves of this user become proposals (live material, no publish right). */
    changes_need_approval?: boolean;
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };
const FILE_LOCALES: { key: FileLocale; label: string }[] = [
    { key: 'tg', label: 'Таджикский (ТҶ)' },
    { key: 'ru', label: 'Русский (РУ)' },
    { key: 'en', label: 'Английский (EN)' },
];

/** Grows a textarea with its text, as the editor's title does. */
function fitHeight(element: HTMLTextAreaElement | null): void {
    if (element) {
        element.style.height = 'auto';
        element.style.height = `${element.scrollHeight}px`;
    }
}

export default function DocumentForm({
    document,
    reference,
    pending_change = null,
    changes_need_approval = false,
}: Props) {
    const can = useCan();
    const isEdit = !!document;
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [sidebarOpen, setSidebarOpen] = useInspectorOpen();
    const fileLimit = useUploadLimits().file;
    const titleRef = useRef<HTMLTextAreaElement>(null);

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
    const { data, setData, processing, errors, isDirty } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    useEffect(() => {
        fitHeight(titleRef.current);
    }, [lang]);

    const compAll = {
        tg: data.name.tg.trim() !== '' ? 100 : 0,
        ru: data.name.ru.trim() !== '' ? 100 : 0,
        en: data.name.en.trim() !== '' ? 100 : 0,
    };

    // A document appears on the site by its name alone: its content is the file.
    const versions = {
        tg: { title: data.name.tg },
        ru: { title: data.name.ru },
        en: { title: data.name.en },
    };

    // A language keeps a file when one is picked, or the saved one stays.
    const hasFile = (key: FileLocale): boolean =>
        data[`file_${key}` as const] !== null ||
        (!!document?.files?.[key] && !data[`file_${key}_remove` as const]);

    // Readiness: what the catalogue shows about the document.
    const readinessItems = [
        {
            id: 'name',
            label: `Название (${localeShort[lang]})`,
            done: data.name[lang].trim() !== '',
            weight: 30,
        },
        {
            id: 'type',
            label: 'Тип документа',
            done: data.doc_type !== '',
            weight: 20,
        },
        {
            id: 'requisites',
            label: 'Номер и дата',
            done: data.number.trim() !== '' && data.doc_date !== '',
            weight: 20,
        },
        {
            id: 'file',
            label: 'Файл хотя бы на одном языке',
            done: FILE_LOCALES.some(({ key }) => hasFile(key)),
            weight: 20,
        },
        {
            id: 'bilingual',
            label: 'Название на таджикском и русском',
            done: data.name.tg.trim() !== '' && data.name.ru.trim() !== '',
            weight: 10,
        },
    ];
    const readinessScore = readinessItems.reduce(
        (sum, item) => sum + (item.done ? item.weight : 0),
        0,
    );

    const submit = (
        action: 'draft' | 'submit',
        mode?: PublishMode,
        stay = false,
    ) => {
        form.transform((d) => ({
            ...d,
            action,
            publish_mode: mode ?? d.publish_mode,
            stay,
            ...(isEdit
                ? {
                      _method: 'put',
                      _editorial_version: document!.updated_at,
                  }
                : {}),
        }));

        form.post(isEdit ? update.url(document!.id) : store.url(), {
            forceFormData: true,
            preserveScroll: true,
            // Keep what the editor typed when validation fails (the page
            // would otherwise remount from server data and drop the errors);
            // a successful save leaving the editor starts clean.
            preserveState: stay ? true : 'errors',
        });
    };

    return (
        <EditorialFormShell
            unpublishUrl={isEdit ? unpublish.url(document!.id) : undefined}
            pendingChange={pending_change}
            changesNeedApproval={changes_need_approval}
            canApprove={can('documents.approve')}
            variant="gutenberg"
            title={isEdit ? 'Редактирование документа' : 'Новый документ'}
            subtitle="Укажите название и реквизиты, прикрепите файлы на нужных языках."
            backLabel="Документы"
            backHref={index.url()}
            status={document?.status}
            language={{
                active: lang,
                onChange: setLang,
                completeness: compAll,
            }}
            errors={errors}
            isDirty={isDirty}
            processing={processing}
            canPublish={can('documents.publish')}
            onSaveDraft={() => submit('draft')}
            onSaveShortcut={() => submit('draft', undefined, true)}
            onSubmitReview={() => submit('submit', 'review')}
            onPublishNow={() => submit('submit', 'now')}
            extraActions={
                <Button
                    variant={sidebarOpen ? 'primary' : 'secondary'}
                    icon={<Sliders size={15} />}
                    onClick={() => setSidebarOpen(!sidebarOpen)}
                    title={
                        sidebarOpen
                            ? 'Скрыть панель настроек'
                            : 'Показать панель настроек'
                    }
                >
                    <span className="wp-topbar-label">Настройки</span>
                </Button>
            }
            autosave={{
                contentType: 'documents',
                contentId: document?.id ?? null,
                baseVersion: document?.updated_at ?? null,
                data,
                onRecover: (recovered) =>
                    form.setData({ ...data, ...recovered }),
            }}
            preview={{
                locales: versions,
                titleWord: 'названия',
                signedUrl: document?.preview_url,
                checklist: [
                    ...languageChecks(compAll, versions, 'названия'),
                    {
                        label: 'Реквизиты документа указаны',
                        ok:
                            data.doc_type !== '' &&
                            data.doc_date !== '' &&
                            data.number !== '',
                    },
                ],
            }}
        >
            <div
                className={`wp-editor-layout ${sidebarOpen ? 'has-sidebar' : 'no-sidebar'}`}
            >
                {/* ------------------------------------------- Document Canvas */}
                <main className="wp-editor-canvas-container" role="main">
                    <div className="wp-editor-canvas">
                        <div className="wp-title-wrapper">
                            <textarea
                                ref={titleRef}
                                id={`document-name-${lang}`}
                                value={data.name[lang]}
                                onChange={(e) => {
                                    setData('name', {
                                        ...data.name,
                                        [lang]: e.target.value,
                                    });
                                    fitHeight(e.target);
                                }}
                                placeholder={
                                    lang === 'ru'
                                        ? 'Название документа…'
                                        : lang === 'tg'
                                          ? 'Название на таджикском…'
                                          : 'Название на английском…'
                                }
                                className="wp-title-input"
                                rows={1}
                                maxLength={255}
                                aria-label="Название документа"
                            />
                            {(fieldError('name') ??
                                fieldError(`name.${lang}`)) && (
                                <div className="wp-field-error">
                                    {fieldError('name') ??
                                        fieldError(`name.${lang}`)}
                                </div>
                            )}
                        </div>

                        <EditorCanvasBlock
                            title="Файлы по языкам"
                            hint={`${fileLimit.label} · до ${fileLimit.max_mb} МБ на файл. На сайте у каждой языковой версии — свой файл.`}
                        >
                            <div className="wp-canvas-block-rows">
                                {changes_need_approval && (
                                    <p className="wp-locked-note">
                                        {MEDIA_LOCKED_NOTE}
                                    </p>
                                )}
                                {FILE_LOCALES.map(({ key, label }) => (
                                    <LanguageFile
                                        key={key}
                                        locked={changes_need_approval}
                                        id={`document-file-${key}`}
                                        label={label}
                                        saved={document?.files?.[key] ?? null}
                                        picked={data[`file_${key}` as const]}
                                        removed={
                                            data[`file_${key}_remove` as const]
                                        }
                                        onPick={(file) =>
                                            setData((prev) => ({
                                                ...prev,
                                                [`file_${key}` as const]: file,
                                                [`file_${key}_remove` as const]: false,
                                            }))
                                        }
                                        onRemovedChange={(removed) =>
                                            setData(
                                                `file_${key}_remove` as const,
                                                removed,
                                            )
                                        }
                                        error={fieldError(`file_${key}`)}
                                    />
                                ))}
                            </div>
                        </EditorCanvasBlock>
                    </div>
                </main>

                {/* --------------------------------- Inspector Sidebar */}
                {sidebarOpen && (
                    <EditorInspector
                        label="Настройки документа"
                        title={
                            <>
                                <FileStack size={15} />
                                <span>Документ</span>
                            </>
                        }
                        onClose={() => setSidebarOpen(false)}
                    >
                        <div className="wp-inspector-section">
                            <ReadinessWidget
                                title="Готовность документа"
                                score={readinessScore}
                                items={readinessItems}
                            />
                        </div>

                        <InspectorSection title="Реквизиты">
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
                                htmlFor="document-section"
                                hint="Группировка в каталоге (напр. «Законодательство»)."
                                error={fieldError('section')}
                            >
                                <Input
                                    id="document-section"
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
                        </InspectorSection>
                    </EditorInspector>
                )}
            </div>
        </EditorialFormShell>
    );
}

/**
 * The file of one language version: the saved one (a link), a freshly picked
 * one waiting for «Сохранить», a button to pick or replace it, and removal
 * of the saved file. Locked (the edit goes to approval), only the saved file
 * is shown.
 */
function LanguageFile({
    id,
    label,
    saved,
    picked,
    removed,
    onPick,
    onRemovedChange,
    locked,
    error,
}: {
    id: string;
    label: string;
    saved: FileInfo | null;
    picked: File | null;
    removed: boolean;
    onPick: (file: File) => void;
    onRemovedChange: (removed: boolean) => void;
    locked: boolean;
    error?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const limit = useUploadLimits().file;
    // A file turned away before the upload: the server's error comes later.
    const [problem, setProblem] = useState<string | null>(null);
    const action = saved || picked ? 'Заменить файл' : 'Выбрать файл';

    return (
        <div className="wp-language-file">
            <span className="wp-language-file-label">{label}</span>

            <div className="wp-language-file-current">
                {picked ? (
                    <span>
                        <FileText size={15} strokeWidth={1.5} />
                        {picked.name}
                        <small>— загрузится при сохранении</small>
                    </span>
                ) : saved && !removed ? (
                    <a href={saved.url} target="_blank" rel="noreferrer">
                        <FileText size={15} strokeWidth={1.5} />
                        {saved.name}
                    </a>
                ) : (
                    <span className="wp-language-file-empty">Файла нет</span>
                )}
            </div>

            {!locked && (
                <>
                    <input
                        ref={inputRef}
                        id={id}
                        type="file"
                        accept={acceptOf(limit)}
                        hidden
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            e.target.value = '';

                            if (!file) {
                                return;
                            }

                            const reason = uploadProblem(file, limit, 'file');
                            setProblem(reason);

                            if (reason === null) {
                                onPick(file);
                            }
                        }}
                    />

                    <div className="wp-btn-row">
                        <Button
                            variant="secondary"
                            size="sm"
                            icon={<Upload size={14} />}
                            onClick={() => inputRef.current?.click()}
                            aria-label={`${action}: ${label}`}
                        >
                            {action}
                        </Button>
                    </div>

                    {saved && (
                        <Checkbox
                            label="Удалить сохранённый файл"
                            checked={removed}
                            onChange={(e) => onRemovedChange(e.target.checked)}
                        />
                    )}
                </>
            )}

            {(problem ?? error) && (
                <div className="wp-field-error">{problem ?? error}</div>
            )}
        </div>
    );
}
