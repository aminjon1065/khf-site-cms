import { Blueprint } from '@/ui/Blueprint';
import { Field, Input, Select } from '@/ui/Field';
import type { Option } from './types';

export function ParametersCard({
    data,
    setData,
    fieldError,
    lifecycles,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    fieldError: (key: string) => string | undefined;
    lifecycles: Option[];
}) {
    return (
        <Blueprint style={{ padding: 20 }}>
            <h3
                className="ui-card-title"
                style={{ marginTop: 0, marginBottom: 14 }}
            >
                Параметры
            </h3>

            <Field
                label="Статус проекта"
                required
                error={fieldError('lifecycle_status')}
            >
                <Select
                    value={data.lifecycle_status}
                    options={lifecycles}
                    onChange={(e) =>
                        setData('lifecycle_status', e.target.value)
                    }
                />
            </Field>
            <Field label="Код проекта">
                <Input
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    placeholder="Проект 01"
                />
            </Field>
            <Field label="Сроки">
                <Input
                    value={data.years}
                    onChange={(e) => setData('years', e.target.value)}
                    placeholder="2026–2030"
                />
            </Field>
            <Field label="Заказчик">
                <Input
                    value={data.customer}
                    onChange={(e) => setData('customer', e.target.value)}
                />
            </Field>
            <Field label="Партнёры">
                <Input
                    value={data.partner}
                    onChange={(e) => setData('partner', e.target.value)}
                />
            </Field>
            <Field label="Бюджет">
                <Input
                    value={data.budget}
                    onChange={(e) => setData('budget', e.target.value)}
                    placeholder="18,4 млн долл. США"
                />
            </Field>
            <Field
                label="Адрес (slug)"
                hint="Пусто — из названия."
                error={fieldError('slug')}
            >
                <Input
                    value={data.slug}
                    onChange={(e) => setData('slug', e.target.value)}
                    hasError={!!fieldError('slug')}
                    placeholder="early-warning-system"
                />
            </Field>
        </Blueprint>
    );
}
