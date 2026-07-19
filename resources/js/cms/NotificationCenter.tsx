import { router } from '@inertiajs/react';
import { BellOff } from 'lucide-react';
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

    const markAll = () => {
        router.post(
            '/notifications/read-all',
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const openItem = (id: string, url: string | null) => {
        router.post(
            `/notifications/${id}/read`,
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
                notifications.items.length > 0 ? (
                    <Button variant="ghost" onClick={markAll}>
                        {t('action.mark_all_read')}
                    </Button>
                ) : undefined
            }
        >
            {notifications.items.length === 0 ? (
                <EmptyState
                    icon={<BellOff size={28} strokeWidth={1.5} />}
                    title="Нет уведомлений"
                    hint="Здесь появятся задачи и события системы."
                />
            ) : (
                <div style={{ margin: '-14px' }}>
                    {notifications.items.map((n) => {
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
