import { Plus, X } from 'lucide-react';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import { Input, Select, Textarea } from '@/ui/Field';
import type { Option, TimelineItem } from './types';

const TONE_OPTIONS: Option[] = [
    { value: 'success', label: 'Выполнено' },
    { value: 'info', label: 'В плане' },
    { value: 'warning', label: 'Внимание' },
    { value: 'neutral', label: 'Обычный' },
];

export function TimelineCard({
    timeline,
    addTimeline,
    updateTimeline,
    removeTimeline,
}: {
    timeline: TimelineItem[];
    addTimeline: () => void;
    updateTimeline: (i: number, key: keyof TimelineItem, value: string) => void;
    removeTimeline: (i: number) => void;
}) {
    return (
        <Blueprint style={{ padding: 20 }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    marginBottom: 4,
                }}
            >
                <h3 className="ui-card-title" style={{ margin: 0 }}>
                    Ход реализации
                </h3>
                <Button
                    variant="ghost"
                    size="sm"
                    icon={<Plus size={14} strokeWidth={2} />}
                    onClick={addTimeline}
                >
                    Этап
                </Button>
            </div>
            <p
                style={{
                    margin: '0 0 12px',
                    fontSize: 12.5,
                    color: 'var(--color-neutral-600)',
                }}
            >
                Общая хронология проекта (единая для всех языков).
            </p>

            {timeline.length === 0 ? (
                <p
                    style={{
                        margin: 0,
                        fontSize: 12.5,
                        color: 'var(--color-neutral-400)',
                    }}
                >
                    Этапы не добавлены.
                </p>
            ) : (
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 10,
                    }}
                >
                    {timeline.map((item, i) => (
                        <div
                            key={i}
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '150px 1fr 130px 34px',
                                gap: 6,
                                alignItems: 'start',
                            }}
                        >
                            <Input
                                value={item.date}
                                onChange={(e) =>
                                    updateTimeline(i, 'date', e.target.value)
                                }
                                placeholder="Июнь 2026"
                            />
                            <Textarea
                                value={item.text}
                                onChange={(e) =>
                                    updateTimeline(i, 'text', e.target.value)
                                }
                                placeholder="Что сделано / запланировано"
                                style={{ minHeight: 38 }}
                            />
                            <Select
                                value={item.tone}
                                options={TONE_OPTIONS}
                                onChange={(e) =>
                                    updateTimeline(i, 'tone', e.target.value)
                                }
                            />
                            <IconButton
                                label="Удалить этап"
                                variant="ghost"
                                onClick={() => removeTimeline(i)}
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
