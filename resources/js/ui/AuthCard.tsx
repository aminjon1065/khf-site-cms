import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Blueprint } from '@/ui/Blueprint';

/**
 * Карточка страниц входа и восстановления доступа: заголовок, пояснение,
 * сообщение системы («ссылка отправлена», «пароль изменён») и форма.
 */
export function AuthCard({
    title,
    lead,
    status,
    children,
}: {
    title: string;
    lead?: ReactNode;
    status?: string;
    children: ReactNode;
}) {
    return (
        <Blueprint corners={false} style={{ padding: 32 }}>
            <Head title={title} />
            <h2
                style={{
                    fontSize: 20,
                    fontWeight: 650,
                    fontFamily: 'var(--font-heading)',
                    letterSpacing: '-0.03em',
                }}
            >
                {title}
            </h2>
            {lead && (
                <p
                    style={{
                        fontSize: 13.5,
                        color: 'var(--color-neutral-600)',
                        marginTop: 6,
                        marginBottom: 22,
                        lineHeight: 1.45,
                    }}
                >
                    {lead}
                </p>
            )}
            {status && (
                <p
                    role="status"
                    style={{
                        fontSize: 13.5,
                        lineHeight: 1.45,
                        margin: '0 0 18px',
                        padding: '10px 12px',
                        border: '1px solid var(--ok)',
                        borderRadius: 'var(--radius-md)',
                        background:
                            'color-mix(in srgb, var(--ok) 8%, transparent)',
                    }}
                >
                    {status}
                </p>
            )}
            {children}
        </Blueprint>
    );
}
