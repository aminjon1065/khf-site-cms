import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Eye, History, Save, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode, RefObject } from 'react';
import { EditorialPreview } from '@/cms/EditorialPreview';
import type { EditorialPreviewConfig } from '@/cms/EditorialPreview';
import { useEditorialAutosave } from '@/hooks/use-editorial-autosave';
import type { EditorialAutosaveConfig } from '@/hooks/use-editorial-autosave';
import { useSaveShortcut } from '@/hooks/use-save-shortcut';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { StatusBadge } from '@/ui/Badge';
import { Button, LinkButton } from '@/ui/Button';
import { LanguageTabs } from '@/ui/Nav';
import { Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface LanguageSwitcher {
    active: ContentLocale;
    onChange: (locale: ContentLocale) => void;
    completeness: Partial<Record<ContentLocale, number>>;
}

interface EditorialFormShellProps<T extends object> {
    title: string;
    subtitle: string;
    backLabel: string;
    backHref: string;
    status?: ContentStatus;
    language: LanguageSwitcher;
    errors: Record<string, string | undefined>;
    isDirty: boolean;
    processing: boolean;
    canPublish: boolean;
    onSaveDraft: () => void;
    onSaveShortcut?: () => void;
    onSubmitReview: () => void;
    onPublishNow: () => void;
    onSchedule?: () => void;
    autosave: EditorialAutosaveConfig<T>;
    preview: EditorialPreviewConfig;
    children: ReactNode;
}

export function EditorialFormShell<T extends object>({
    title,
    subtitle,
    backLabel,
    backHref,
    status,
    language,
    errors,
    isDirty,
    processing,
    canPublish,
    onSaveDraft,
    onSaveShortcut,
    onSubmitReview,
    onPublishNow,
    onSchedule,
    autosave,
    preview,
    children,
}: EditorialFormShellProps<T>) {
    const errorEntries = Object.entries(errors).filter(
        (entry): entry is [string, string] => Boolean(entry[1]),
    );
    const errorSummaryRef = useRef<HTMLDivElement>(null);
    const allowNextVisit = useRef(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const autosaveState = useEditorialAutosave(autosave, isDirty);

    useUnsavedChangesGuard(isDirty && !processing, allowNextVisit);

    useEffect(() => {
        if (errorEntries.length > 0) {
            errorSummaryRef.current?.focus();
        }
    }, [errorEntries.length]);

    const submit = (callback: () => void) => {
        allowNextVisit.current = true;
        callback();
    };
    const publish = (callback: () => void) => {
        if (preview.checklist.some((check) => check.blocking && !check.ok)) {
            setPreviewOpen(true);

            return;
        }

        submit(callback);
    };

    useSaveShortcut(() => submit(onSaveShortcut ?? onSaveDraft), !processing);

    return (
        <>
            <Head title={title} />

            <PageHeader
                eyebrow={
                    <Link href={backHref} className="editorial-form-back">
                        <ArrowLeft size={14} strokeWidth={1.75} />
                        {backLabel}
                    </Link>
                }
                title={title}
                subtitle={subtitle}
                actions={
                    <div className="editorial-form-save-state">
                        {status && <StatusBadge status={status} />}
                        <span aria-live="polite">
                            {autosaveLabel(
                                autosaveState.state,
                                autosaveState.savedAt,
                            )}
                        </span>
                    </div>
                }
            />

            <div className="editorial-form-language">
                <div>
                    <strong>Язык материала</strong>
                    <span>Поля ниже редактируются для выбранной локали.</span>
                </div>
                <LanguageTabs
                    active={language.active}
                    onChange={language.onChange}
                    completeness={language.completeness}
                />
            </div>

            {errorEntries.length > 0 && (
                <div
                    ref={errorSummaryRef}
                    className="editorial-form-errors"
                    role="alert"
                    aria-live="assertive"
                    tabIndex={-1}
                >
                    <strong>
                        Проверьте форму: ошибок — {errorEntries.length}
                    </strong>
                    <ul>
                        {errorEntries.map(([field, message]) => (
                            <li key={field}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

            {autosaveState.recovery && (
                <div className="editorial-recovery" role="status">
                    <div>
                        <strong>Найдена локальная копия</strong>
                        <span>
                            Сохранена{' '}
                            {formatSavedAt(autosaveState.recovery.savedAt)}.
                            Можно восстановить данные после закрытия вкладки или
                            истечения сессии.
                        </span>
                    </div>
                    <Button
                        variant="primary"
                        size="sm"
                        onClick={autosaveState.recoverLocal}
                    >
                        Восстановить
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={autosaveState.discardRecovery}
                    >
                        Удалить копию
                    </Button>
                </div>
            )}

            {autosaveState.conflict && (
                <div className="editorial-conflict" role="alert">
                    <div>
                        <strong>Обнаружены одновременные изменения</strong>
                        <span>
                            {autosaveState.conflict.savedBy
                                ? `${autosaveState.conflict.savedBy} сохранил другую версию`
                                : 'На сервере уже есть другая версия'}
                            {autosaveState.conflict.savedAt
                                ? ` в ${formatSavedAt(autosaveState.conflict.savedAt)}`
                                : ''}
                            . Выберите, какую продолжить.
                        </span>
                    </div>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={autosaveState.useRemoteConflict}
                    >
                        Загрузить серверную
                    </Button>
                    <Button
                        variant="primary"
                        size="sm"
                        onClick={() => void autosaveState.keepLocalConflict()}
                    >
                        Сохранить мою
                    </Button>
                </div>
            )}

            {children}

            <EditorialPreview
                open={previewOpen}
                onClose={() => setPreviewOpen(false)}
                preview={preview}
                initialLocale={language.active}
            />

            {autosaveState.historyOpen && (
                <section
                    className="editorial-history"
                    aria-label="История версий"
                >
                    <div className="editorial-history-heading">
                        <strong>История версий</strong>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => autosaveState.setHistoryOpen(false)}
                        >
                            Закрыть
                        </Button>
                    </div>
                    {autosaveState.historyLoading ? (
                        <p>Загрузка истории…</p>
                    ) : autosaveState.history.length === 0 ? (
                        <p>Сохранённых версий пока нет.</p>
                    ) : (
                        <ol>
                            {autosaveState.history.map((revision) => (
                                <li key={revision.id}>
                                    <div>
                                        <strong>
                                            {revisionSourceLabel(
                                                revision.source,
                                            )}
                                        </strong>
                                        <span>
                                            {formatSavedAt(revision.saved_at)}
                                            {revision.saved_by
                                                ? ` · ${revision.saved_by}`
                                                : ''}
                                        </span>
                                    </div>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() =>
                                            void autosaveState.restoreRevision(
                                                revision.id,
                                            )
                                        }
                                    >
                                        Восстановить
                                    </Button>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            )}

            <div className="editorial-form-actions">
                <LinkButton href={backHref} variant="ghost">
                    Отмена
                </LinkButton>
                <Button
                    variant="ghost"
                    icon={<Eye size={15} strokeWidth={1.75} />}
                    onClick={() => setPreviewOpen(true)}
                >
                    Предпросмотр
                </Button>
                {autosave.contentId !== null && (
                    <Button
                        variant="ghost"
                        icon={<History size={15} strokeWidth={1.75} />}
                        onClick={() => void autosaveState.loadHistory()}
                    >
                        История версий
                    </Button>
                )}
                <div className="editorial-form-actions-spacer" />
                <Button
                    variant="secondary"
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={() => submit(onSaveDraft)}
                >
                    Сохранить черновик
                </Button>
                {canPublish ? (
                    <Dropdown
                        align="right"
                        placement="top"
                        trigger={({ open, toggle }) => (
                            <div className="ui-splitbtn">
                                <Button
                                    variant="primary"
                                    loading={processing}
                                    onClick={() => publish(onPublishNow)}
                                >
                                    Опубликовать
                                </Button>
                                <Button
                                    variant="primary"
                                    className="ui-splitbtn-chevron"
                                    aria-label="Другие варианты публикации"
                                    aria-haspopup="menu"
                                    aria-expanded={open}
                                    disabled={processing}
                                    onClick={toggle}
                                >
                                    <ChevronDown
                                        size={16}
                                        strokeWidth={2}
                                        style={{
                                            transform: open
                                                ? 'rotate(180deg)'
                                                : undefined,
                                            transition: 'transform 0.15s ease',
                                        }}
                                    />
                                </Button>
                            </div>
                        )}
                        items={[
                            {
                                label: 'Опубликовать сейчас',
                                description: 'Сразу появится на сайте',
                                onSelect: () => publish(onPublishNow),
                            },
                            ...(onSchedule
                                ? [
                                      {
                                          label: 'Запланировать',
                                          description:
                                              'Выйдет в дату из блока «Публикация»',
                                          onSelect: () => publish(onSchedule),
                                      },
                                  ]
                                : []),
                            { separator: true },
                            {
                                label: 'Отправить на согласование',
                                description: 'Сначала проверит руководитель',
                                onSelect: () => submit(onSubmitReview),
                            },
                        ]}
                    />
                ) : (
                    <Button
                        variant="primary"
                        icon={<Send size={15} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={() => submit(onSubmitReview)}
                    >
                        Отправить на проверку
                    </Button>
                )}
            </div>
        </>
    );
}

function autosaveLabel(
    state: 'idle' | 'saving' | 'saved' | 'offline' | 'conflict',
    savedAt: string | null,
): string {
    if (state === 'saving') {
        return 'Сохраняем…';
    }

    if (state === 'offline') {
        return 'Офлайн-копия сохранена';
    }

    if (state === 'conflict') {
        return 'Нужно выбрать версию';
    }

    if (state === 'saved' && savedAt) {
        return `Сохранено в ${formatSavedAt(savedAt)}`;
    }

    return 'Автосохранение включено';
}

function formatSavedAt(value: string): string {
    return new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function revisionSourceLabel(source: string): string {
    if (source === 'autosave') {
        return 'Автосохранение';
    }

    if (source === 'before_restore') {
        return 'До восстановления';
    }

    return 'Ручное сохранение';
}

function useUnsavedChangesGuard(
    active: boolean,
    allowNextVisit: RefObject<boolean>,
): void {
    useEffect(() => {
        if (!active) {
            return;
        }

        const message =
            'Есть несохранённые изменения. Покинуть страницу и потерять их?';
        const beforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        const removeInertiaListener = router.on('before', () => {
            if (allowNextVisit.current) {
                allowNextVisit.current = false;

                return;
            }

            return window.confirm(message);
        });

        window.addEventListener('beforeunload', beforeUnload);

        return () => {
            window.removeEventListener('beforeunload', beforeUnload);
            removeInertiaListener();
        };
    }, [active, allowNextVisit]);
}
