import { CheckCircle2, TriangleAlert } from 'lucide-react';
import { Blueprint } from '@/ui/Blueprint';

export function ChecklistPanel({
    checklist,
    compAll,
}: {
    checklist: { label: string; ok: boolean }[];
    compAll?: Record<string, number>;
}) {
    return (
        <Blueprint
            style={{
                padding: 14,
                position: 'sticky',
                top: 70,
                alignSelf: 'flex-start',
            }}
        >
            <div className="ui-kicker" style={{ marginBottom: 10 }}>
                Проверка перед публикацией
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 7 }}>
                {checklist.map((c, i) => (
                    <div
                        key={i}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            fontSize: 13,
                        }}
                    >
                        {c.ok ? (
                            <CheckCircle2
                                size={15}
                                strokeWidth={1.5}
                                style={{ color: 'var(--ok)' }}
                            />
                        ) : (
                            <TriangleAlert
                                size={15}
                                strokeWidth={1.5}
                                style={{ color: 'var(--warn)' }}
                            />
                        )}
                        <span
                            style={{
                                color: c.ok
                                    ? 'var(--color-text)'
                                    : 'var(--warn)',
                            }}
                        >
                            {c.label}
                        </span>
                    </div>
                ))}
            </div>
            {compAll && (
                <div
                    style={{
                        marginTop: 12,
                        paddingTop: 10,
                        borderTop: '1px solid var(--color-divider)',
                        fontSize: 11.5,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    Заполненность: ТҶ {compAll.tg}% · РУ {compAll.ru}% · EN{' '}
                    {compAll.en}%
                </div>
            )}
        </Blueprint>
    );
}
