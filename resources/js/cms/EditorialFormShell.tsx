import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    Copy,
    Eye,
    History,
    Save,
    Send,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode, RefObject } from 'react';
import { EditorialPreview } from '@/cms/EditorialPreview';
import type { EditorialPreviewConfig } from '@/cms/EditorialPreview';
import { RevisionDiff } from '@/cms/RevisionDiff';
import { useEditorialAutosave } from '@/hooks/use-editorial-autosave';
import type { EditorialAutosaveConfig } from '@/hooks/use-editorial-autosave';
import { useSaveShortcut } from '@/hooks/use-save-shortcut';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { StatusBadge } from '@/ui/Badge';
import { Button, LinkButton } from '@/ui/Button';
import { LanguageTabs } from '@/ui/Nav';
import { ConfirmDialog, Dropdown } from '@/ui/Overlay';
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
    extraActions?: ReactNode;
    children: ReactNode;
    variant?: 'default' | 'gutenberg';
    onCopyLocale?: (from: ContentLocale, to: ContentLocale) => void;
    /** Where «Снять с публикации» posts (the material's unpublish route). */
    unpublishUrl?: string;
    /** A proposal waiting for approval on this live material, if any. */
    pendingChange?: PendingChangeInfo | null;
    /** The server turns this user's saves into proposals (live material, no publish right). */
    changesNeedApproval?: boolean;
    /** The user may apply proposals (sees the link to the approval center). */
    canApprove?: boolean;
}

export interface PendingChangeInfo {
    id: number;
    author: string | null;
    created_at: string | null;
    is_mine: boolean;
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
    extraActions,
    variant = 'default',
    onCopyLocale,
    unpublishUrl,
    pendingChange = null,
    changesNeedApproval = false,
    canApprove = false,
    children,
}: EditorialFormShellProps<T>) {
    const errorEntries = Object.entries(errors).filter(
        (entry): entry is [string, string] => Boolean(entry[1]),
    );
    const errorSummaryRef = useRef<HTMLDivElement>(null);
    const allowNextVisit = useRef(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [diffRevisionId, setDiffRevisionId] = useState<number | null>(null);
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

    const [unpublishOpen, setUnpublishOpen] = useState(false);
    const [unpublishing, setUnpublishing] = useState(false);
    const publishActionProps: Omit<PublishActionsProps, 'compact'> = {
        status,
        canPublish,
        changesNeedApproval,
        processing,
        onSave: () => submit(onSaveDraft),
        onPublishNow: () => publish(onPublishNow),
        onSchedule: onSchedule ? () => publish(onSchedule) : undefined,
        onSubmitReview: () => submit(onSubmitReview),
        onUnpublish: unpublishUrl ? () => setUnpublishOpen(true) : undefined,
    };

    const languageTabsNode = (
        <LanguageTabs
            active={language.active}
            onChange={language.onChange}
            completeness={language.completeness}
        />
    );

    return (
        <>
            <Head title={title} />

            {variant === 'gutenberg' ? (
                <header className="wp-topbar" role="banner">
                    {/* The editor's big title field is content, not a
                        heading: screen readers still need the page's one. */}
                    <h1 className="sr-only">{title}</h1>
                    <div className="wp-topbar-left">
                        <Link
                            href={backHref}
                            className="wp-topbar-back"
                            title={backLabel}
                        >
                            <ArrowLeft size={16} strokeWidth={2} />
                            <span className="wp-topbar-back-label">
                                {backLabel}
                            </span>
                        </Link>
                        <span className="wp-topbar-divider" />
                        {status && <StatusBadge status={status} />}
                        <span className="wp-topbar-autosave" aria-live="polite">
                            {autosaveLabel(
                                autosaveState.state,
                                autosaveState.savedAt,
                            )}
                        </span>
                    </div>

                    <div className="wp-topbar-center">
                        {languageTabsNode}
                        {onCopyLocale && (
                            <button
                                type="button"
                                className="wp-copy-locale-btn"
                                title="Скопировать заголовок, краткое описание и текст из русской версии в текущую"
                                onClick={() =>
                                    onCopyLocale('ru', language.active)
                                }
                            >
                                <Copy size={13} strokeWidth={1.75} />
                                <span className="wp-topbar-label">
                                    Скопировать с русского
                                </span>
                            </button>
                        )}
                    </div>

                    <div className="wp-topbar-right">
                        {autosave.contentId !== null && (
                            <Button
                                variant="ghost"
                                size="sm"
                                icon={<History size={15} strokeWidth={1.75} />}
                                onClick={() => void autosaveState.loadHistory()}
                                title="История версий"
                            >
                                <span className="wp-topbar-label">История</span>
                            </Button>
                        )}
                        <LinkButton
                            href={backHref}
                            variant="ghost"
                            size="sm"
                            icon={<X size={15} strokeWidth={1.75} />}
                            title="Отмена — выйти без сохранения"
                        >
                            <span className="wp-topbar-label">Отмена</span>
                        </LinkButton>
                        <Button
                            variant="ghost"
                            size="sm"
                            icon={<Eye size={15} strokeWidth={1.75} />}
                            onClick={() => setPreviewOpen(true)}
                            title="Предпросмотр материала"
                        >
                            <span className="wp-topbar-label">
                                Предпросмотр
                            </span>
                        </Button>
                        <PublishActions compact {...publishActionProps} />

                        {extraActions}
                    </div>
                </header>
            ) : (
                <>
                    <PageHeader
                        eyebrow={
                            <Link
                                href={backHref}
                                className="editorial-form-back"
                            >
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
                                {extraActions}
                            </div>
                        }
                    />

                    <div className="editorial-form-language">
                        <div>
                            <strong>Язык материала</strong>
                            <span>
                                Поля ниже редактируются для выбранного языка.
                            </span>
                        </div>
                        {languageTabsNode}
                    </div>
                </>
            )}

            {(pendingChange || changesNeedApproval) && (
                <div className="editorial-pending" role="status">
                    {pendingChange ? (
                        <>
                            <strong>
                                {pendingChange.is_mine
                                    ? 'Ваши изменения ждут согласования'
                                    : `${pendingChange.author ?? 'Сотрудник'} предложил изменения — они ждут согласования`}
                            </strong>
                            <span>
                                {pendingChange.created_at
                                    ? `Отправлены ${pendingChange.created_at}. `
                                    : ''}
                                На сайте пока прежняя версия.
                                {pendingChange.is_mine
                                    ? ' Ниже — ваша версия: её можно поправить и отправить снова.'
                                    : ''}
                            </span>
                            {canApprove && !pendingChange.is_mine && (
                                <Link
                                    href={`/approvals?change=${pendingChange.id}`}
                                >
                                    Открыть в разделе «Согласование» →
                                </Link>
                            )}
                        </>
                    ) : (
                        <>
                            <strong>Материал опубликован</strong>
                            <span>
                                Ваши изменения появятся на сайте после
                                согласования. Фото и файлы опубликованного
                                материала меняет сотрудник с правом публикации.
                            </span>
                        </>
                    )}
                </div>
            )}

            {unpublishUrl && (
                <ConfirmDialog
                    open={unpublishOpen}
                    onClose={() => setUnpublishOpen(false)}
                    loading={unpublishing}
                    title="Снять с публикации?"
                    body="Материал уберут с сайта и вернут в черновики — его можно будет поправить и опубликовать снова по тому же адресу."
                    confirmLabel="Снять с публикации"
                    requireComment
                    onConfirm={(comment) => {
                        allowNextVisit.current = true;
                        setUnpublishing(true);
                        router.post(
                            unpublishUrl,
                            { comment },
                            {
                                preserveScroll: true,
                                onFinish: () => {
                                    setUnpublishing(false);
                                    setUnpublishOpen(false);
                                },
                            },
                        );
                    }}
                />
            )}

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
                        <strong>Найдена несохранённая копия</strong>
                        <span>
                            Сохранена{' '}
                            {formatSavedAt(autosaveState.recovery.savedAt)}. Её
                            можно восстановить, если вкладка закрылась или вас
                            вывело из системы.
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
                                : 'В системе уже сохранена другая версия'}
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
                        Взять сохранённую
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
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setDiffRevisionId(revision.id)
                                        }
                                    >
                                        Сравнить
                                    </Button>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            )}

            {diffRevisionId !== null && (
                <RevisionDiff
                    revisionId={diffRevisionId}
                    onClose={() => setDiffRevisionId(null)}
                />
            )}

            {variant !== 'gutenberg' && (
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
                    <PublishActions {...publishActionProps} />
                </div>
            )}
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
        return 'Копия сохранена на этом компьютере';
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

interface PublishActionsProps {
    /** The editor top bar: small buttons, secondary labels collapse to icons. */
    compact?: boolean;
    status?: ContentStatus;
    canPublish: boolean;
    changesNeedApproval: boolean;
    processing: boolean;
    onSave: () => void;
    onPublishNow: () => void;
    onSchedule?: () => void;
    onSubmitReview: () => void;
    onUnpublish?: () => void;
}

const LIVE_STATUSES: ContentStatus[] = ['published', 'updated', 'completed'];

/**
 * The publish box, WordPress-style: what the buttons do depends on where the
 * material is. A draft is saved or published; a live material is updated in
 * place (or, without the publish right, its changes go to approval); a
 * scheduled one can go out now or be taken off the schedule.
 */
function PublishActions({
    compact = false,
    status,
    canPublish,
    changesNeedApproval,
    processing,
    onSave,
    onPublishNow,
    onSchedule,
    onSubmitReview,
    onUnpublish,
}: PublishActionsProps) {
    const size = compact ? 'sm' : undefined;
    const label = (text: string) =>
        compact ? <span className="wp-topbar-label">{text}</span> : text;
    const isLive = status !== undefined && LIVE_STATUSES.includes(status);
    const inReview = status === 'review' || status === 'translation_check';

    if (changesNeedApproval) {
        return (
            <Button
                variant="primary"
                size={size}
                icon={<Send size={15} strokeWidth={1.75} />}
                loading={processing}
                onClick={onSave}
                title="Изменения появятся на сайте после согласования"
            >
                Отправить изменения на согласование
            </Button>
        );
    }

    if (isLive && canPublish) {
        return (
            <SplitAction
                size={size}
                processing={processing}
                label="Обновить"
                title="Сохранить изменения — они сразу появятся на сайте"
                onClick={onSave}
                items={
                    onUnpublish
                        ? [
                              {
                                  label: 'Снять с публикации…',
                                  description:
                                      'Убрать с сайта и вернуть в черновики',
                                  danger: true,
                                  onSelect: onUnpublish,
                              },
                          ]
                        : []
                }
            />
        );
    }

    if (status === 'scheduled' && canPublish) {
        return (
            <>
                <Button
                    variant="secondary"
                    size={size}
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={onSave}
                    title="Сохранить (выйдет в назначенное время)"
                >
                    {label('Сохранить')}
                </Button>
                <SplitAction
                    size={size}
                    processing={processing}
                    label="Опубликовать сейчас"
                    onClick={onPublishNow}
                    items={
                        onUnpublish
                            ? [
                                  {
                                      label: 'Отменить планирование…',
                                      description: 'Вернуть в черновики',
                                      danger: true,
                                      onSelect: onUnpublish,
                                  },
                              ]
                            : []
                    }
                />
            </>
        );
    }

    const saveButton = (
        <Button
            variant="secondary"
            size={size}
            icon={<Save size={15} strokeWidth={1.75} />}
            loading={processing}
            onClick={onSave}
            title={
                inReview ? 'Сохранить (Ctrl+S)' : 'Сохранить черновик (Ctrl+S)'
            }
        >
            {label(inReview ? 'Сохранить' : 'Сохранить черновик')}
        </Button>
    );

    if (inReview && !canPublish) {
        return saveButton;
    }

    if (!canPublish) {
        return (
            <>
                {saveButton}
                <Button
                    variant="primary"
                    size={size}
                    icon={<Send size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={onSubmitReview}
                >
                    Отправить на согласование
                </Button>
            </>
        );
    }

    return (
        <>
            {saveButton}
            <SplitAction
                size={size}
                processing={processing}
                label="Опубликовать"
                onClick={onPublishNow}
                items={[
                    {
                        label: 'Опубликовать сейчас',
                        description: 'Сразу появится на сайте',
                        onSelect: onPublishNow,
                    },
                    ...(onSchedule
                        ? [
                              {
                                  label: 'Запланировать',
                                  description:
                                      'Выйдет в дату из блока «Публикация»',
                                  onSelect: onSchedule,
                              },
                          ]
                        : []),
                    ...(inReview
                        ? []
                        : [
                              { separator: true as const },
                              {
                                  label: 'Отправить на согласование',
                                  description: 'Сначала проверит руководитель',
                                  onSelect: onSubmitReview,
                              },
                          ]),
                ]}
            />
        </>
    );
}

type SplitItem =
    | { separator: true }
    | {
          label: string;
          description?: string;
          danger?: boolean;
          onSelect: () => void;
      };

function SplitAction({
    size,
    processing,
    label,
    title,
    onClick,
    items,
}: {
    size?: 'sm';
    processing: boolean;
    label: string;
    title?: string;
    onClick: () => void;
    items: SplitItem[];
}) {
    if (items.length === 0) {
        return (
            <Button
                variant="primary"
                size={size}
                loading={processing}
                onClick={onClick}
                title={title}
            >
                {label}
            </Button>
        );
    }

    return (
        <Dropdown
            align="right"
            placement={size === 'sm' ? 'bottom' : 'top'}
            trigger={({ open, toggle }) => (
                <div className="ui-splitbtn">
                    <Button
                        variant="primary"
                        size={size}
                        loading={processing}
                        onClick={onClick}
                        title={title}
                    >
                        {label}
                    </Button>
                    <Button
                        variant="primary"
                        size={size}
                        className="ui-splitbtn-chevron"
                        aria-label="Другие действия"
                        aria-haspopup="menu"
                        aria-expanded={open}
                        disabled={processing}
                        onClick={toggle}
                    >
                        <ChevronDown
                            size={15}
                            strokeWidth={2}
                            style={{
                                transform: open ? 'rotate(180deg)' : undefined,
                                transition: 'transform 0.15s ease',
                            }}
                        />
                    </Button>
                </div>
            )}
            items={items}
        />
    );
}
