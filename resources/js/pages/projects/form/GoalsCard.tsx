import { Plus, X } from 'lucide-react';
import type { ContentLocale } from '@/lib/domain';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import { Textarea } from '@/ui/Field';

export function GoalsCard({
    lang,
    goals,
    addGoal,
    updateGoal,
    removeGoal,
}: {
    lang: ContentLocale;
    goals: string[];
    addGoal: () => void;
    updateGoal: (i: number, value: string) => void;
    removeGoal: (i: number) => void;
}) {
    return (
        <Blueprint style={{ padding: 20 }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    marginBottom: 10,
                }}
            >
                <h3 className="ui-card-title" style={{ margin: 0 }}>
                    Цели и задачи ({lang.toUpperCase()})
                </h3>
                <Button
                    variant="ghost"
                    size="sm"
                    icon={<Plus size={14} strokeWidth={2} />}
                    onClick={addGoal}
                >
                    Цель
                </Button>
            </div>

            {goals.length === 0 ? (
                <p
                    style={{
                        margin: 0,
                        fontSize: 12.5,
                        color: 'var(--color-neutral-400)',
                    }}
                >
                    Цели не добавлены.
                </p>
            ) : (
                <div
                    style={{ display: 'flex', flexDirection: 'column', gap: 6 }}
                >
                    {goals.map((goal, i) => (
                        <div
                            key={i}
                            style={{
                                display: 'flex',
                                gap: 6,
                                alignItems: 'flex-start',
                            }}
                        >
                            <span
                                className="ui-mono"
                                style={{
                                    width: 22,
                                    paddingTop: 8,
                                    fontSize: 12.5,
                                    color: 'var(--color-neutral-500)',
                                }}
                            >
                                {String(i + 1).padStart(2, '0')}
                            </span>
                            <Textarea
                                value={goal}
                                onChange={(e) => updateGoal(i, e.target.value)}
                                style={{ minHeight: 40, flex: 1 }}
                                maxLength={1000}
                            />
                            <IconButton
                                label="Удалить цель"
                                variant="ghost"
                                onClick={() => removeGoal(i)}
                            >
                                <X size={15} strokeWidth={1.5} />
                            </IconButton>
                        </div>
                    ))}
                </div>
            )}
        </Blueprint>
    );
}
