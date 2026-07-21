import { Head, router } from '@inertiajs/react';
import { Bell, BellOff, CheckCheck } from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/Cms/NotificationController';
import { toneColor } from '@/lib/domain';
import type { StatusTone } from '@/lib/domain';
import { useT } from '@/lib/i18n';
import type { AppNotification } from '@/types/cms';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Pagination } from '@/ui/DataTable';
import { EmptyState } from '@/ui/Feedback';
import { PageHeader } from '@/ui/PageHeader';

interface Props {
    items: AppNotification[];
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        prev: string | null;
        next: string | null;
    };
}

function notificationTone(tone: string): StatusTone {
    return ['neutral', 'warn', 'ok', 'accent', 'danger'].includes(tone)
        ? (tone as StatusTone)
        : 'accent';
}

export default function Notifications({ items, meta }: Props) {
    const { t } = useT();

    const markAllRead = () => {
        router.post(
            NotificationController.markAllRead.url(),
            {},
            { preserveScroll: true },
        );
    };

    const openNotification = (notification: AppNotification) => {
        router.post(
            NotificationController.markRead.url(notification.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (notification.url) {
                        router.visit(notification.url);
                    }
                },
            },
        );
    };

    return (
        <>
            <Head title={t('nav.notifications')} />
            <PageHeader
                title={t('nav.notifications')}
                subtitle={`${meta.total} уведомлений · непрочитанные выделены`}
                actions={
                    items.some((item) => item.read_at === null) ? (
                        <Button
                            variant="secondary"
                            icon={<CheckCheck size={16} strokeWidth={1.5} />}
                            onClick={markAllRead}
                        >
                            {t('action.mark_all_read')}
                        </Button>
                    ) : undefined
                }
            />

            {items.length === 0 ? (
                <Blueprint className="min-h-72">
                    <EmptyState
                        icon={<BellOff size={30} strokeWidth={1.25} />}
                        title="Нет уведомлений"
                        hint="Здесь появятся задачи редакционного процесса и события системы."
                    />
                </Blueprint>
            ) : (
                <Blueprint className="overflow-hidden p-0">
                    {items.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => openNotification(item)}
                            className="flex w-full gap-3 border-0 border-b border-(--color-divider) px-4 py-3 text-left last:border-b-0"
                            style={{
                                background: item.read_at
                                    ? 'transparent'
                                    : 'var(--color-accent-100)',
                            }}
                        >
                            <Bell
                                size={17}
                                strokeWidth={1.5}
                                className="mt-0.5 shrink-0"
                                style={{
                                    color: toneColor[
                                        notificationTone(item.tone)
                                    ],
                                }}
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-medium">
                                    {item.title || 'Уведомление'}
                                </span>
                                <span className="mt-1 block text-[13px] text-(--color-neutral-700)">
                                    {item.message}
                                </span>
                                <span className="mt-1 block text-xs text-(--color-neutral-500)">
                                    {item.created_diff}
                                </span>
                            </span>
                        </button>
                    ))}
                </Blueprint>
            )}

            <Pagination
                from={meta.from ?? 0}
                to={meta.to ?? 0}
                total={meta.total}
                onPrev={
                    meta.prev
                        ? () =>
                              router.visit(meta.prev!, {
                                  preserveScroll: true,
                                  preserveState: true,
                              })
                        : undefined
                }
                onNext={
                    meta.next
                        ? () =>
                              router.visit(meta.next!, {
                                  preserveScroll: true,
                                  preserveState: true,
                              })
                        : undefined
                }
            />
        </>
    );
}
