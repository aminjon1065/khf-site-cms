import type { Severity } from '@/lib/domain';
import { SeverityBadge } from '@/ui/Badge';
import { DatePicker, Field, Input, RadioCard, Select } from '@/ui/Field';
import type { Reference } from './types';

const LEVEL_DESC: Record<string, string> = {
    info: 'Справочное сообщение',
    attention: 'Возможно ухудшение',
    warning: 'Вероятная угроза',
    danger: 'Реальная угроза',
    critical: 'Угроза жизни',
};

export function Step1({
    data,
    setData,
    errors,
    reference,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    errors: Record<string, string>;
    reference: Reference;
}) {
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
                <Field
                    label="Тип события"
                    required
                    error={errors.hazard_type}
                    hint="Определяет иконку и связанные инструкции"
                >
                    <Select
                        placeholder="Выберите тип"
                        value={data.hazard_type}
                        options={reference.hazards}
                        onChange={(e) => setData('hazard_type', e.target.value)}
                        hasError={!!errors.hazard_type}
                    />
                </Field>
                <Field label="Источник данных">
                    <Select
                        placeholder="Не указан"
                        value={data.source}
                        onChange={(e) => setData('source', e.target.value)}
                    >
                        {reference.sources.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </Select>
                </Field>
            </div>

            <Field label="Уровень опасности" required error={errors.severity}>
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(5, 1fr)',
                        gap: 8,
                    }}
                    className="cms-levels-grid"
                >
                    {reference.severities.map((s) => (
                        <RadioCard
                            key={s.value}
                            active={data.severity === s.value}
                            onSelect={() => setData('severity', s.value)}
                        >
                            <SeverityBadge severity={s.value as Severity} />
                            <span
                                style={{
                                    fontSize: 11.5,
                                    color: 'var(--color-neutral-600)',
                                    marginTop: 4,
                                }}
                            >
                                {LEVEL_DESC[s.value]}
                            </span>
                        </RadioCard>
                    ))}
                </div>
            </Field>

            <Field
                label="Внутреннее название"
                required
                error={errors.internal_title}
                hint="Видно только сотрудникам. Публичный заголовок задаётся на этапе «Содержание»."
            >
                <Input
                    value={data.internal_title}
                    onChange={(e) => setData('internal_title', e.target.value)}
                    placeholder="Селевая опасность — Хатлонская область, июль 2026"
                    hasError={!!errors.internal_title}
                />
            </Field>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                }}
            >
                <Field label="Начало действия" error={errors.starts_at}>
                    <DatePicker
                        withTime
                        value={data.starts_at}
                        onChange={(e) => setData('starts_at', e.target.value)}
                    />
                </Field>
                <Field label="Автоматическое завершение" error={errors.ends_at}>
                    <DatePicker
                        withTime
                        value={data.ends_at}
                        onChange={(e) => setData('ends_at', e.target.value)}
                        hasError={!!errors.ends_at}
                    />
                </Field>
            </div>
        </div>
    );
}
