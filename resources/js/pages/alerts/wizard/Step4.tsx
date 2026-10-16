import { X } from 'lucide-react';
import { Tag } from '@/ui/Badge';
import { Field, Select } from '@/ui/Field';
import type { Reference } from './types';

export function Step4({
    data,
    setData,
    reference,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    reference: Reference;
}) {
    const toggleInstruction = (id: number) => {
        setData(
            'related_instructions',
            data.related_instructions.includes(id)
                ? data.related_instructions.filter((x: number) => x !== id)
                : [...data.related_instructions, id],
        );
    };

    return (
        <div
            style={{
                maxWidth: 760,
                display: 'flex',
                flexDirection: 'column',
                gap: 18,
            }}
        >
            <Field label="Связанные инструкции населению">
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {reference.instructions.map((i) => {
                        const active = data.related_instructions.includes(i.id);

                        return (
                            <button
                                key={i.id}
                                type="button"
                                aria-pressed={active}
                                onClick={() => toggleInstruction(i.id)}
                                style={{
                                    border: 0,
                                    background: 'transparent',
                                    cursor: 'pointer',
                                    padding: 0,
                                }}
                            >
                                <Tag tone={active ? 'accent' : 'outline'}>
                                    {i.name}
                                    {active && (
                                        <X
                                            size={12}
                                            strokeWidth={2}
                                            style={{ marginLeft: 4 }}
                                        />
                                    )}
                                </Tag>
                            </button>
                        );
                    })}
                </div>
            </Field>
            <div style={{ maxWidth: 372 }}>
                <Field label="Категория риска">
                    <Select
                        placeholder="Не указана"
                        value={data.risk_category}
                        options={reference.riskCategories}
                        onChange={(e) =>
                            setData('risk_category', e.target.value)
                        }
                    />
                </Field>
            </div>
        </div>
    );
}
