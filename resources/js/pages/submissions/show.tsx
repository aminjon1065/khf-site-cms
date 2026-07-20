import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, MessageSquarePlus, Save } from 'lucide-react';
import { useCan } from '@/lib/auth';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Field, Select, Textarea } from '@/ui/Field';
import { PageHeader } from '@/ui/PageHeader';

interface Option {
    value: number;
    label: string;
}

interface Comment {
    id: number;
    body: string;
    author: string;
    created_at: string | null;
    created_diff: string;
}

interface SubmissionData {
    id: number;
    tracking_number: string | null;
    name: string;
    email: string;
    phone: string | null;
    topic: string | null;
    message: string;
    status: string;
    assigned_to: number | null;
    region: string | null;
    ip_address: string | null;
    created_at: string | null;
    comments: Comment[];
}

interface Props {
    submission: SubmissionData;
    reference: {
        statuses: { value: string; label: string }[];
        assignees: Option[];
    };
}

function fmt(date: string | null): string {
    return date
        ? new Date(date).toLocaleString('ru-RU', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
}

export default function SubmissionShow({ submission, reference }: Props) {
    const can = useCan();
    const editable = can('submissions.edit');

    const status = useForm({
        status: submission.status,
        assigned_to: (submission.assigned_to ?? '') as number | '',
    });

    const comment = useForm({ body: '' });

    const saveStatus = () =>
        status.put(`/submissions/${submission.id}`, { preserveScroll: true });

    const addComment = () =>
        comment.post(`/submissions/${submission.id}/comments`, {
            preserveScroll: true,
            onSuccess: () => comment.reset('body'),
        });

    const info: { label: string; value: string }[] = [
        { label: 'Заявитель', value: submission.name },
        { label: 'E-mail', value: submission.email },
        { label: 'Телефон', value: submission.phone ?? '—' },
        { label: 'Тема', value: submission.topic ?? '—' },
        { label: 'Регион', value: submission.region ?? '—' },
        { label: 'Получено', value: fmt(submission.created_at) },
        { label: 'IP-адрес', value: submission.ip_address ?? '—' },
    ];

    return (
        <>
            <Head title={submission.tracking_number ?? 'Обращение'} />

            <PageHeader
                eyebrow={
                    <Link
                        href="/submissions"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Обращения
                    </Link>
                }
                title={submission.tracking_number ?? `Обращение #${submission.id}`}
                subtitle={submission.name}
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
                {/* main */}
                <div
                    style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
                >
                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 12 }}
                        >
                            Текст обращения
                        </h3>
                        <p
                            style={{
                                margin: 0,
                                fontSize: 14.5,
                                lineHeight: 1.6,
                                whiteSpace: 'pre-wrap',
                            }}
                        >
                            {submission.message}
                        </p>
                    </Blueprint>

                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 12 }}
                        >
                            Внутренние комментарии
                        </h3>

                        {submission.comments.length === 0 ? (
                            <p
                                style={{
                                    margin: '0 0 12px',
                                    fontSize: 12.5,
                                    color: 'var(--color-neutral-400)',
                                }}
                            >
                                Комментариев пока нет.
                            </p>
                        ) : (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 12,
                                    marginBottom: 14,
                                }}
                            >
                                {submission.comments.map((c) => (
                                    <div
                                        key={c.id}
                                        style={{
                                            borderLeft:
                                                '2px solid var(--color-divider)',
                                            paddingLeft: 12,
                                        }}
                                    >
                                        <div
                                            style={{
                                                fontSize: 12,
                                                color: 'var(--color-neutral-500)',
                                                marginBottom: 2,
                                            }}
                                        >
                                            <strong
                                                style={{
                                                    color: 'var(--color-text)',
                                                }}
                                            >
                                                {c.author}
                                            </strong>{' '}
                                            · {c.created_diff}
                                        </div>
                                        <div
                                            style={{
                                                fontSize: 13.5,
                                                lineHeight: 1.5,
                                                whiteSpace: 'pre-wrap',
                                            }}
                                        >
                                            {c.body}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {editable && (
                            <div>
                                <Textarea
                                    value={comment.data.body}
                                    onChange={(e) =>
                                        comment.setData('body', e.target.value)
                                    }
                                    placeholder="Служебная заметка по обращению…"
                                    style={{ minHeight: 70 }}
                                    hasError={!!comment.errors.body}
                                />
                                {comment.errors.body && (
                                    <div
                                        style={{
                                            color: 'var(--danger)',
                                            fontSize: 12,
                                            marginTop: 4,
                                        }}
                                    >
                                        {comment.errors.body}
                                    </div>
                                )}
                                <Button
                                    className="mt-2"
                                    variant="secondary"
                                    icon={
                                        <MessageSquarePlus
                                            size={15}
                                            strokeWidth={1.75}
                                        />
                                    }
                                    loading={comment.processing}
                                    onClick={addComment}
                                >
                                    Добавить комментарий
                                </Button>
                            </div>
                        )}
                    </Blueprint>
                </div>

                {/* sidebar */}
                <div
                    style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
                >
                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            Обработка
                        </h3>

                        <Field label="Статус">
                            <Select
                                value={status.data.status}
                                options={reference.statuses}
                                onChange={(e) =>
                                    status.setData('status', e.target.value)
                                }
                                disabled={!editable}
                            />
                        </Field>

                        <Field label="Ответственный">
                            <Select
                                value={
                                    status.data.assigned_to === ''
                                        ? ''
                                        : String(status.data.assigned_to)
                                }
                                onChange={(e) =>
                                    status.setData(
                                        'assigned_to',
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                                placeholder="Не назначен"
                                options={reference.assignees.map((a) => ({
                                    value: a.value,
                                    label: a.label,
                                }))}
                                disabled={!editable}
                            />
                        </Field>

                        {editable && (
                            <Button
                                className="mt-1"
                                variant="primary"
                                block
                                icon={<Save size={15} strokeWidth={1.75} />}
                                loading={status.processing}
                                onClick={saveStatus}
                            >
                                Сохранить
                            </Button>
                        )}
                    </Blueprint>

                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 12 }}
                        >
                            Данные заявителя
                        </h3>
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                            }}
                        >
                            {info.map((it) => (
                                <div key={it.label}>
                                    <div
                                        style={{
                                            fontSize: 11,
                                            color: 'var(--color-neutral-500)',
                                        }}
                                    >
                                        {it.label}
                                    </div>
                                    <div style={{ fontSize: 13.5 }}>
                                        {it.value}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <a
                            className="ui-btn ui-btn-secondary ui-btn-block mt-3"
                            href={`mailto:${submission.email}`}
                        >
                            Ответить по e-mail
                        </a>
                    </Blueprint>
                </div>
            </div>
        </>
    );
}
