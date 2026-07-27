import { Blueprint } from '@/ui/Blueprint';
import { Field, Input } from '@/ui/Field';
import type { Direction } from './types';

export function DirectionCard({
    direction,
    setDirection,
}: {
    direction: Direction;
    setDirection: (key: keyof Direction, value: string) => void;
}) {
    return (
        <Blueprint style={{ padding: 20 }}>
            <h3
                className="ui-card-title"
                style={{ marginTop: 0, marginBottom: 14 }}
            >
                Дирекция проекта
            </h3>
            <Field label="Адрес">
                <Input
                    value={direction.address}
                    onChange={(e) => setDirection('address', e.target.value)}
                />
            </Field>
            <Field label="Телефон">
                <Input
                    value={direction.phone}
                    onChange={(e) => setDirection('phone', e.target.value)}
                />
            </Field>
            <Field label="E-mail">
                <Input
                    value={direction.email}
                    onChange={(e) => setDirection('email', e.target.value)}
                />
            </Field>
        </Blueprint>
    );
}
