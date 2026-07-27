import { X } from 'lucide-react';
import { Tag } from '@/ui/Badge';
import { Field, Input, Select } from '@/ui/Field';
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
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                }}
            >
                <Field label="Изображение">
                    <div className="ui-dropzone">
                        Перетащите файл или выберите из медиабиблиотеки
                    </div>
                </Field>
                <Field label="Документы">
                    <div className="ui-dropzone">
                        Прикрепите прогноз или официальный документ (PDF)
                    </div>
                </Field>
            </div>
            <Field label="Связанные инструкции населению">
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {reference.instructions.map((i) => {
                        const active = data.related_instructions.includes(i.id);

                        return (
                            <button
                                key={i.id}
                                type="button"
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
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                }}
            >
                <Field label="Экстренные номера">
                    <Input
                        value={data.contacts.ru ?? ''}
                        onChange={(e) =>
                            setData('contacts', {
                                ...data.contacts,
                                ru: e.target.value,
                            })
                        }
                        placeholder="112 · +992 (37) 221-59-00"
                    />
                </Field>
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
