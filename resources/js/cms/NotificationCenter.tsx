import { router } from '@inertiajs/react';
import { BellOff } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import NotificationController from '@/actions/App/Http/Controllers/Cms/NotificationController';
import { useShared } from '@/lib/auth';
import { toneColor } from '@/lib/domain';
import type { StatusTone } from '@/lib/domain';
import { useT } from '@/lib/i18n';
import { Button } from '@/ui/Button';
import { EmptyState } from '@/ui/Feedback';
import { Drawer } from '@/ui/Overlay';

export function NotificationCenter({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const { t } = useT();
    const { notifications } = useShared();
    const [loading, setLoading] = useState(false);

    const loadNotifications = useCallback(() => {
        router.reload({
            only: ['notifications', 'notification_unread'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }, []);

    useEffect(() => {
        if (open) {
            loadNotifications();
        }
    }, [loadNotifications, open]);

    const markAll = () => {
        router.post(
            NotificationController.markAllRead.url(),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: loadNotifications,
            },
        );
    };

    const openItem = (id: string, url: string | null) => {
        router.post(
            NotificationController.markRead.url(id),
            {},
            { preserveScroll: true, preserveState: true },
        );

        if (url) {
            router.visit(url);
            onClose();
        }
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            width={390}
            title={t('nav.notifications')}
            footer={
                (notifications?.items.length ?? 0) > 0 ? (
                    <Button variant="ghost" onClick={markAll}>
                        {t('action.mark_all_read')}
                    </Button>
                ) : undefined
            }
        >
            {loading && !notifications ? (
                <div
                    aria-busy="true"
                    aria-label="Загрузка уведомлений"
                    style={{ display: 'grid', gap: 10 }}
                >
                    {[0, 1, 2].map((item) => (
                        <div
                            key={item}
                            className="animate-pulse"
                            style={{
                                height: 58,
                                background: 'var(--color-neutral-100)',
                            }}
                        />
                    ))}
                </div>
            ) : (notifications?.items.length ?? 0) === 0 ? (
                <EmptyState
                    icon={<BellOff size={28} strokeWidth={1.5} />}
                    title="Нет уведомлений"
                    hint="Здесь появятся задачи и события системы."
                />
            ) : (
                <div
                    style={{
                        margin: '-14px',
                        opacity: loading ? 0.65 : 1,
                    }}
                    aria-busy={loading}
                >
                    {notifications?.items.map((n) => {
                        const tone = (
                            [
                                'neutral',
                                'warn',
                                'ok',
                                'accent',
                                'danger',
                            ].includes(n.tone)
                                ? n.tone
                                : 'accent'
                        ) as StatusTone;

                        return (
                            <button
                                key={n.id}
                                type="button"
                                onClick={() => openItem(n.id, n.url)}
                                style={{
                                    display: 'flex',
                                    gap: 10,
                                    width: '100%',
                                    textAlign: 'left',
                                    padding: '12px 14px',
                                    border: 0,
                                    borderBottom:
                                        '1px solid var(--color-divider)',
                                    background: n.read_at
                                        ? 'transparent'
                                        : 'var(--color-accent-100)',
                                    cursor: 'pointer',
                                }}
                            >
                                <span
                                    style={{
                                        width: 8,
                                        height: 8,
                                        borderRadius: '50%',
                                        background: toneColor[tone],
                                        marginTop: 6,
                                        flex: 'none',
                                    }}
                                />
                                <span style={{ flex: 1 }}>
                                    <span
                                        style={{
                                            display: 'block',
                                            fontSize: 13,
                                            fontWeight: n.read_at ? 400 : 500,
                                        }}
                                    >
                                        {n.message || n.title}
                                    </span>
                                    <span
                                        style={{
                                            display: 'block',
                                            fontSize: 11.5,
                                            color: 'var(--color-neutral-500)',
                                            marginTop: 3,
                                        }}
                                    >
                                        {n.created_diff}
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}
        </Drawer>
    );
}
