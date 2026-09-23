import { Head, router } from '@inertiajs/react';
import { CheckCircle2, ClipboardCheck, ExternalLink, Eye } from 'lucide-react';
import { useState } from 'react';
import ApprovalController from '@/actions/App/Http/Controllers/Cms/ApprovalController';
import { DiffSegments } from '@/cms/DiffSegments';
import type { ContentStatus, Severity } from '@/lib/domain';
import { useT } from '@/lib/i18n';
import { diffLines } from '@/lib/text-diff';
import { LanguageBadges, SeverityBadge, StatusBadge, Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, LinkButton } from '@/ui/Button';
import { EmptyState, WorkflowTimeline } from '@/ui/Feedback';
import type { TimelineStep } from '@/ui/Feedback';
import { ConfirmDialog } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';

interface QueueItem {
    /** Set for changes proposed to a published material. */
    change_id: number | null;
    type: string;
    id: number;
    title: string;
    kind: string;
    subkind: string;
    severity: Severity | null;
    author: string;
    submitted: string;
    urgent: boolean;
}

interface DiffRow {
    field: string;
    label: string;
    before: string;
    after: string;
}

interface Detail {
    /** Set for changes proposed to a published material. */
    change_id: number | null;
    diff: DiffRow[];
    public_url: string | null;
    preview_url: string | null;
    type: string;
    id: number;
    title: string;
    kind: string;
    subkind: string;
    severity: Severity | null;
    status: ContentStatus;
    body: string;
    meta: { label: string; value: string }[];
    languages: Record<string, number> | null;
    timeline: { title: string; meta: string; state: string }[];
    comment: string | null;
    route: string;
    can_approve: boolean;
}

export default function Approvals({
    queue,
    detail,
}: {
    queue: QueueItem[];
    detail: Detail | null;
}) {
    const { t } = useT();
    const [returnOpen, setReturnOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const select = (item: QueueItem) => {
        router.get(
            ApprovalController.index.url(),
            item.change_id !== null
                ? { change: item.change_id }
                : { type: item.type, id: item.id },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const approve = () => {
        if (!detail) {
            return;
        }

        setProcessing(true);

        if (detail.change_id !== null) {
            router.post(
                ApprovalController.applyChange.url(detail.change_id),
                {},
                { onFinish: () => setProcessing(false) },
            );

            return;
        }

        router.post(
            ApprovalController.approve.url(),
            { type: detail.type, id: detail.id },
            { onFinish: () => setProcessing(false) },
        );
    };

    const submitReturn = (comment: string) => {
        if (!detail) {
            return;
        }

        setProcessing(true);
        const done = {
            onFinish: () => {
                setProcessing(false);
                setReturnOpen(false);
            },
        };

        if (detail.change_id !== null) {
            router.post(
                ApprovalController.rejectChange.url(detail.change_id),
                { comment },
                done,
            );

            return;
        }

        router.post(
            ApprovalController.returnToAuthor.url(),
            { type: detail.type, id: detail.id, comment },
            done,
        );
    };

    const isChange = detail?.change_id != null;

    return (
        <>
            <Head title={t('nav.approvals')} />
            <PageHeader
                title="Центр согласования"
                subtitle={`${queue.length} ${plural(queue.length, 'материал ожидает', 'материала ожидают', 'материалов ожидают')} вашего решения · срочные — первыми`}
            />

            {queue.length === 0 ? (
                <Blueprint style={{ minHeight: 280 }}>
                    <EmptyState
                        icon={
                            <CheckCircle2
                                size={30}
                                strokeWidth={1.25}
                                style={{ color: 'var(--ok)' }}
                            />
                        }
                        title="Очередь пуста"
                        hint="Все материалы согласованы. Новые появятся здесь автоматически."
                    />
                </Blueprint>
            ) : (
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '340px minmax(0, 1fr)',
                        gap: 20,
                    }}
                    className="cms-two-col"
                >
                    {/* Queue */}
                    <Blueprint style={{ padding: 0, alignSelf: 'flex-start' }}>
                        {queue.map((item) => {
                            const active =
                                item.change_id !== null
                                    ? detail?.change_id === item.change_id
                                    : detail?.change_id === null &&
                                      detail?.type === item.type &&
                                      detail?.id === item.id;

                            return (
                                <button
                                    key={
                                        item.change_id !== null
                                            ? `change-${item.change_id}`
                                            : `${item.type}-${item.id}`
                                    }
                                    type="button"
                                    onClick={() => select(item)}
                                    style={{
                                        display: 'block',
                                        width: '100%',
                                        textAlign: 'left',
                                        padding: '12px 14px',
                                        border: 0,
                                        borderBottom:
                                            '1px solid var(--color-divider)',
                                        borderLeft: `2px solid ${active ? 'var(--brand-700)' : 'transparent'}`,
                                        background: active
                                            ? 'var(--brand-100)'
                                            : 'transparent',
                                        cursor: 'pointer',
                                    }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 6,
                                            marginBottom: 4,
                                        }}
                                    >
                                        {item.urgent && (
                                            <Tag tone="danger">Срочно</Tag>
                                        )}
                                        <span
                                            style={{
                                                fontSize: 11,
                                                color: 'var(--color-neutral-500)',
                                            }}
                                        >
                                            {item.kind}
                                            {item.subkind
                                                ? ` · ${item.subkind}`
                                                : ''}
                                        </span>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 13.5,
                                            fontWeight: 500,
                                            marginBottom: 2,
                                        }}
                                    >
                                        {item.title}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: 'var(--color-neutral-500)',
                                        }}
                                    >
                                        {item.author} · {item.submitted}
                                    </div>
                                </button>
                            );
                        })}
                    </Blueprint>

                    {/* Detail */}
                    {detail ? (
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 20,
                            }}
                        >
                            <Blueprint style={{ padding: 18 }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                        marginBottom: 10,
                                    }}
                                >
                                    {detail.severity && (
                                        <SeverityBadge
                                            severity={detail.severity}
                                        />
                                    )}
                                    <span
                                        style={{
                                            fontSize: 12,
                                            color: 'var(--color-neutral-600)',
                                        }}
                                    >
                                        {detail.kind}
                                        {detail.subkind
                                            ? ` · ${detail.subkind}`
                                            : ''}
                                    </span>
                                    <StatusBadge status={detail.status} />
                                </div>
                                <h2
                                    style={{
                                        fontFamily: 'var(--font-heading)',
                                        fontWeight: 600,
                                        fontSize: 22,
                                        marginBottom: 8,
                                    }}
                                >
                                    {detail.title}
                                </h2>
                                {detail.body && (
                                    <p
                                        style={{
                                            fontSize: 14,
                                            lineHeight: 1.6,
                                            marginBottom: 12,
                                        }}
                                    >
                                        {detail.body}
                                    </p>
                                )}

                                {isChange && (
                                    <div style={{ marginBottom: 14 }}>
                                        {detail.diff.length === 0 ? (
                                            <p
                                                style={{
                                                    fontSize: 13,
                                                    color: 'var(--color-neutral-600)',
                                                }}
                                            >
                                                Изменения касаются только
                                                оформления текста.
                                            </p>
                                        ) : (
                                            detail.diff.map((row) => (
                                                <section
                                                    key={row.label}
                                                    style={{ marginBottom: 12 }}
                                                >
                                                    <h4
                                                        className="ui-card-title"
                                                        style={{
                                                            margin: '0 0 6px',
                                                        }}
                                                    >
                                                        {row.label}
                                                    </h4>
                                                    <DiffSegments
                                                        segments={diffLines(
                                                            row.before,
                                                            row.after,
                                                        )}
                                                    />
                                                </section>
                                            ))
                                        )}
                                    </div>
                                )}

                                {detail.meta.length > 0 && (
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexWrap: 'wrap',
                                            gap: '4px 20px',
                                            fontSize: 12.5,
                                            color: 'var(--color-neutral-700)',
                                            marginBottom: 8,
                                        }}
                                    >
                                        {detail.meta.map((m, i) => (
                                            <span key={i}>
                                                <strong
                                                    style={{ fontWeight: 600 }}
                                                >
                                                    {m.label}:
                                                </strong>{' '}
                                                {m.value}
                                            </span>
                                        ))}
                                    </div>
                                )}
                                {detail.languages && (
                                    <div style={{ marginBottom: 14 }}>
                                        <LanguageBadges
                                            completeness={detail.languages}
                                        />
                                    </div>
                                )}

                                <div
                                    style={{
                                        display: 'flex',
                                        gap: 8,
                                        flexWrap: 'wrap',
                                    }}
                                >
                                    {detail.can_approve && (
                                        <>
                                            <Button
                                                variant="primary"
                                                loading={processing}
                                                icon={
                                                    <CheckCircle2
                                                        size={16}
                                                        strokeWidth={1.5}
                                                    />
                                                }
                                                onClick={approve}
                                            >
                                                {isChange
                                                    ? 'Применить изменения'
                                                    : 'Согласовать и опубликовать'}
                                            </Button>
                                            <Button
                                                variant="danger-outline"
                                                onClick={() =>
                                                    setReturnOpen(true)
                                                }
                                            >
                                                {isChange
                                                    ? 'Отклонить…'
                                                    : 'Вернуть на доработку…'}
                                            </Button>
                                        </>
                                    )}
                                    {detail.preview_url && (
                                        <a
                                            href={detail.preview_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="ui-btn ui-btn-ghost"
                                        >
                                            <Eye size={15} strokeWidth={1.75} />
                                            Предпросмотр целиком
                                        </a>
                                    )}
                                    {detail.public_url && (
                                        <a
                                            href={detail.public_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="ui-btn ui-btn-ghost"
                                        >
                                            <ExternalLink
                                                size={15}
                                                strokeWidth={1.75}
                                            />
                                            Сейчас на сайте
                                        </a>
                                    )}
                                    <LinkButton
                                        href={detail.route}
                                        variant="ghost"
                                    >
                                        Открыть в редакторе
                                    </LinkButton>
                                </div>
                            </Blueprint>

                            {(detail.timeline.length > 0 || detail.comment) && (
                                <div>
                                    <h3
                                        style={{
                                            fontFamily: 'var(--font-heading)',
                                            fontWeight: 600,
                                            fontSize: 15,
                                            marginBottom: 10,
                                        }}
                                    >
                                        {isChange
                                            ? 'Обратите внимание'
                                            : 'Редакционный процесс'}
                                    </h3>
                                    <Blueprint style={{ padding: 16 }}>
                                        <WorkflowTimeline
                                            steps={detail.timeline.map(
                                                (s): TimelineStep => ({
                                                    title: s.title,
                                                    meta: s.meta,
                                                    state: s.state as TimelineStep['state'],
                                                }),
                                            )}
                                        />
                                        {detail.comment && (
                                            <div
                                                style={{
                                                    marginTop: 10,
                                                    paddingTop: 10,
                                                    borderTop:
                                                        '1px solid var(--color-divider)',
                                                    fontSize: 13,
                                                    color: 'var(--color-neutral-700)',
                                                }}
                                            >
                                                {detail.comment}
                                            </div>
                                        )}
                                    </Blueprint>
                                </div>
                            )}
                        </div>
                    ) : (
                        <Blueprint>
                            <EmptyState
                                icon={
                                    <ClipboardCheck
                                        size={28}
                                        strokeWidth={1.25}
                                    />
                                }
                                title="Выберите материал"
                                hint="Выберите запись в очереди слева, чтобы увидеть содержание и историю."
                            />
                        </Blueprint>
                    )}
                </div>
            )}

            <ConfirmDialog
                open={returnOpen}
                onClose={() => setReturnOpen(false)}
                loading={processing}
                title={
                    isChange ? 'Отклонить изменения?' : 'Вернуть на доработку?'
                }
                body={
                    detail
                        ? isChange
                            ? `Изменения «${detail.title}» не попадут на сайт. Напишите автору, почему.`
                            : `Материал «${detail.title}» вернётся автору со статусом «Возвращено». Укажите, что нужно исправить.`
                        : ''
                }
                confirmLabel={isChange ? 'Отклонить' : 'Вернуть автору'}
                requireComment
                onConfirm={submitReturn}
            />
        </>
    );
}

function plural(count: number, one: string, few: string, many: string): string {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return one;
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return few;
    }

    return many;
}
