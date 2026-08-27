import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, ClipboardList, Save, XCircle } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/routes/usability';
import { Tag } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import {
    Checkbox,
    Field,
    Input,
    InputError,
    Select,
    Textarea,
} from '@/ui/Field';
import { PageHeader } from '@/ui/PageHeader';

interface TaskDefinition {
    key: string;
    label: string;
    target_seconds: number | null;
}

interface TaskResult {
    completed: boolean;
    assisted: boolean;
    duration_seconds: number;
    irreversible_error: boolean;
}

interface Target {
    label: string;
    actual: number | null;
    target: string;
    passed: boolean;
}

interface TaskMetric {
    key: string;
    label: string;
    attempts: number;
    unassisted_success_rate: number;
    p75_seconds: number | null;
    target_seconds: number | null;
}

interface Session {
    participant_code: string;
    role: string;
    experience_level: string;
    sus_score: number;
    completed_at: string;
}

interface Props {
    tasks: TaskDefinition[];
    summary: {
        participants: number;
        task_attempts: number;
        unassisted_success_rate: number;
        sus_average: number | null;
        irreversible_errors: number;
        ready: boolean;
    };
    targets: Record<string, Target>;
    task_metrics: TaskMetric[];
    sessions: Session[];
}

const SUS_STATEMENTS = [
    'Я хотел(а) бы часто пользоваться этой CMS.',
    'CMS показалась мне излишне сложной.',
    'CMS показалась мне простой в использовании.',
    'Мне понадобилась бы помощь технического специалиста.',
    'Функции CMS хорошо согласованы между собой.',
    'В CMS слишком много несогласованности.',
    'Большинство редакторов быстро освоят CMS.',
    'CMS показалась мне громоздкой.',
    'Я чувствовал(а) себя уверенно при работе с CMS.',
    'До начала работы нужно освоить слишком много нового.',
];

const ROLE_LABELS: Record<string, string> = {
    editor: 'Редактор',
    regional_editor: 'Региональный редактор',
    alert_operator: 'Оператор предупреждений',
    chief_editor: 'Главный редактор',
    translator: 'Переводчик',
};

const EXPERIENCE_LABELS: Record<string, string> = {
    none: 'Нет опыта с CMS',
    basic: 'Базовый опыт',
    experienced: 'Опытный пользователь',
};

function localDateTime(date = new Date()): string {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

    return local.toISOString().slice(0, 16);
}

function formatActual(key: string, actual: number | null): string {
    if (actual === null) {
        return 'Нет данных';
    }

    if (key === 'unassisted_success_rate') {
        return `${actual}%`;
    }

    if (['news_first', 'news_repeat', 'critical_alert'].includes(key)) {
        return `${actual} сек.`;
    }

    return String(actual);
}

export default function UsabilityIndex({
    tasks,
    summary,
    targets,
    task_metrics: taskMetrics,
    sessions,
}: Props) {
    const [initialTimes] = useState(() => {
        const completedAt = new Date();

        return {
            startedAt: localDateTime(
                new Date(completedAt.getTime() - 20 * 60_000),
            ),
            completedAt: localDateTime(completedAt),
        };
    });
    const initialTasks = Object.fromEntries(
        tasks.map((task) => [
            task.key,
            {
                completed: true,
                assisted: false,
                duration_seconds: task.target_seconds ?? 60,
                irreversible_error: false,
            },
        ]),
    ) as Record<string, TaskResult>;
    const form = useForm(store(), {
        participant_code: '',
        role: 'editor',
        experience_level: 'none',
        tasks: initialTasks,
        sus_responses: Array.from({ length: 10 }, () => 3),
        notes: '',
        started_at: initialTimes.startedAt,
        completed_at: initialTimes.completedAt,
    });
    const fieldError = (key: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[key];

    const setTask = (
        key: string,
        field: keyof TaskResult,
        value: boolean | number,
    ) => {
        form.setData('tasks', {
            ...form.data.tasks,
            [key]: { ...form.data.tasks[key], [field]: value },
        });
    };

    const setSusResponse = (index: number, value: number) => {
        const responses = [...form.data.sus_responses];
        responses[index] = value;
        form.setData('sus_responses', responses);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.submit({
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Проверка удобства" />
            <PageHeader
                eyebrow="UX-10"
                title="Проверка удобства CMS"
                subtitle="Анонимный протокол полевых сессий с будущими редакторами и автоматическая проверка критериев."
                actions={
                    <Tag tone={summary.ready ? 'ok' : 'warn'}>
                        {summary.ready
                            ? 'Критерии достигнуты'
                            : `${summary.participants}/5 участников`}
                    </Tag>
                }
            />

            <section aria-labelledby="usability-results-heading">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2
                            id="usability-results-heading"
                            className="m-0 text-xl font-semibold"
                        >
                            Итог исследования
                        </h2>
                        <p className="ui-hint mt-1">
                            Готовность подтверждается только после пяти реальных
                            сессий и прохождения всех целей.
                        </p>
                    </div>
                    <Tag tone={summary.ready ? 'ok' : 'neutral'}>
                        {summary.ready ? 'Gate пройден' : 'Gate не пройден'}
                    </Tag>
                </div>

                <div className="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Blueprint className="p-4">
                        <strong className="block font-mono text-2xl">
                            {summary.participants}
                        </strong>
                        <span className="ui-hint">реальных участников</span>
                    </Blueprint>
                    <Blueprint className="p-4">
                        <strong className="block font-mono text-2xl">
                            {summary.unassisted_success_rate}%
                        </strong>
                        <span className="ui-hint">задач без помощи</span>
                    </Blueprint>
                    <Blueprint className="p-4">
                        <strong className="block font-mono text-2xl">
                            {summary.sus_average ?? '—'}
                        </strong>
                        <span className="ui-hint">средний SUS</span>
                    </Blueprint>
                    <Blueprint className="p-4">
                        <strong className="block font-mono text-2xl">
                            {summary.irreversible_errors}
                        </strong>
                        <span className="ui-hint">необратимых ошибок</span>
                    </Blueprint>
                </div>

                <Blueprint className="mb-5 overflow-x-auto p-0">
                    <table className="w-full border-collapse text-left text-sm">
                        <caption className="sr-only">
                            Результат проверки целей usability-исследования
                        </caption>
                        <thead>
                            <tr className="border-b border-(--color-divider)">
                                <th scope="col" className="px-4 py-3">
                                    Цель
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Факт
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Порог
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Статус
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(targets).map(([key, target]) => (
                                <tr
                                    key={key}
                                    className="border-b border-(--color-divider) last:border-0"
                                >
                                    <th
                                        scope="row"
                                        className="px-4 py-3 font-medium"
                                    >
                                        {target.label}
                                    </th>
                                    <td className="px-4 py-3 font-mono">
                                        {formatActual(key, target.actual)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {target.target}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Tag
                                            tone={
                                                target.passed ? 'ok' : 'neutral'
                                            }
                                        >
                                            {target.passed ? (
                                                <>
                                                    <CheckCircle2
                                                        aria-hidden="true"
                                                        size={13}
                                                    />{' '}
                                                    Пройдено
                                                </>
                                            ) : (
                                                <>
                                                    <XCircle
                                                        aria-hidden="true"
                                                        size={13}
                                                    />{' '}
                                                    Не пройдено
                                                </>
                                            )}
                                        </Tag>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Blueprint>

                <Blueprint className="mb-5 overflow-x-auto p-0">
                    <table className="w-full border-collapse text-left text-sm">
                        <caption className="sr-only">
                            Метрики выполнения заданий
                        </caption>
                        <thead>
                            <tr className="border-b border-(--color-divider)">
                                <th scope="col" className="px-4 py-3">
                                    Задание
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Попытки
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Без помощи
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Время p75
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {taskMetrics.map((metric) => (
                                <tr
                                    key={metric.key}
                                    className="border-b border-(--color-divider) last:border-0"
                                >
                                    <th
                                        scope="row"
                                        className="px-4 py-3 font-medium"
                                    >
                                        {metric.label}
                                    </th>
                                    <td className="px-4 py-3">
                                        {metric.attempts}
                                    </td>
                                    <td className="px-4 py-3">
                                        {metric.unassisted_success_rate}%
                                    </td>
                                    <td className="px-4 py-3 font-mono">
                                        {metric.p75_seconds === null
                                            ? '—'
                                            : `${metric.p75_seconds} сек.`}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Blueprint>
            </section>

            <section aria-labelledby="usability-session-heading">
                <Blueprint className="p-5">
                    <div className="mb-5 flex items-start gap-3">
                        <ClipboardList
                            aria-hidden="true"
                            className="mt-0.5 text-(--brand-700)"
                            size={24}
                        />
                        <div>
                            <h2
                                id="usability-session-heading"
                                className="ui-card-title m-0"
                            >
                                Записать реальную сессию
                            </h2>
                            <p className="ui-hint mt-1">
                                Не вводите ФИО, e-mail или другие персональные
                                данные. Фасилитатор не подсказывает до отметки
                                «потребовалась помощь».
                            </p>
                        </div>
                    </div>

                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field
                                label="Код участника"
                                required
                                htmlFor="participant-code"
                                hint="Например, UXT-001"
                                error={fieldError('participant_code')}
                            >
                                <Input
                                    id="participant-code"
                                    className="min-h-11!"
                                    value={form.data.participant_code}
                                    onChange={(event) =>
                                        form.setData(
                                            'participant_code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    pattern="[A-Z0-9-]{2,24}"
                                    autoComplete="off"
                                    hasError={Boolean(
                                        fieldError('participant_code'),
                                    )}
                                />
                            </Field>
                            <Field
                                label="Будущая роль"
                                required
                                htmlFor="participant-role"
                                error={fieldError('role')}
                            >
                                <Select
                                    id="participant-role"
                                    className="min-h-11!"
                                    value={form.data.role}
                                    onChange={(event) =>
                                        form.setData('role', event.target.value)
                                    }
                                    options={Object.entries(ROLE_LABELS).map(
                                        ([value, label]) => ({ value, label }),
                                    )}
                                />
                            </Field>
                            <Field
                                label="Предыдущий опыт"
                                required
                                htmlFor="participant-experience"
                                error={fieldError('experience_level')}
                            >
                                <Select
                                    id="participant-experience"
                                    className="min-h-11!"
                                    value={form.data.experience_level}
                                    onChange={(event) =>
                                        form.setData(
                                            'experience_level',
                                            event.target.value,
                                        )
                                    }
                                    options={Object.entries(
                                        EXPERIENCE_LABELS,
                                    ).map(([value, label]) => ({
                                        value,
                                        label,
                                    }))}
                                />
                            </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                label="Начало сессии"
                                required
                                htmlFor="session-started-at"
                                error={fieldError('started_at')}
                            >
                                <Input
                                    id="session-started-at"
                                    className="min-h-11!"
                                    type="datetime-local"
                                    value={form.data.started_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'started_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Завершение сессии"
                                required
                                htmlFor="session-completed-at"
                                error={fieldError('completed_at')}
                            >
                                <Input
                                    id="session-completed-at"
                                    className="min-h-11!"
                                    type="datetime-local"
                                    value={form.data.completed_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'completed_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            {tasks.map((task, index) => {
                                const result = form.data.tasks[task.key];

                                return (
                                    <fieldset
                                        key={task.key}
                                        className="rounded-md border border-(--color-divider) p-4"
                                    >
                                        <legend className="px-1 font-medium">
                                            {index + 1}. {task.label}
                                        </legend>
                                        <Field
                                            label="Время, секунд"
                                            required
                                            htmlFor={`task-${task.key}-duration`}
                                            hint={
                                                task.target_seconds
                                                    ? `Цель: не более ${task.target_seconds} сек.`
                                                    : undefined
                                            }
                                            error={fieldError(
                                                `tasks.${task.key}.duration_seconds`,
                                            )}
                                        >
                                            <Input
                                                id={`task-${task.key}-duration`}
                                                className="min-h-11!"
                                                type="number"
                                                min={1}
                                                max={7200}
                                                value={result.duration_seconds}
                                                onChange={(event) =>
                                                    setTask(
                                                        task.key,
                                                        'duration_seconds',
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                        </Field>
                                        <div className="mt-3 flex flex-col gap-3">
                                            <Checkbox
                                                className="min-h-11"
                                                checked={result.completed}
                                                onChange={(event) =>
                                                    setTask(
                                                        task.key,
                                                        'completed',
                                                        event.target.checked,
                                                    )
                                                }
                                                label="Задание завершено"
                                            />
                                            <Checkbox
                                                className="min-h-11"
                                                checked={result.assisted}
                                                onChange={(event) =>
                                                    setTask(
                                                        task.key,
                                                        'assisted',
                                                        event.target.checked,
                                                    )
                                                }
                                                label="Потребовалась помощь"
                                            />
                                            <Checkbox
                                                className="min-h-11"
                                                checked={
                                                    result.irreversible_error
                                                }
                                                onChange={(event) =>
                                                    setTask(
                                                        task.key,
                                                        'irreversible_error',
                                                        event.target.checked,
                                                    )
                                                }
                                                label="Произошла необратимая ошибка"
                                            />
                                        </div>
                                    </fieldset>
                                );
                            })}
                        </div>
                        <InputError message={fieldError('tasks')} />

                        <fieldset className="rounded-md border border-(--color-divider) p-4">
                            <legend className="px-1 font-medium">
                                System Usability Scale
                            </legend>
                            <p className="ui-hint mb-4">
                                Оцените каждое утверждение от 1 «полностью не
                                согласен» до 5 «полностью согласен».
                            </p>
                            <div className="grid gap-4 xl:grid-cols-2">
                                {SUS_STATEMENTS.map((statement, index) => (
                                    <Field
                                        key={statement}
                                        label={`${index + 1}. ${statement}`}
                                        htmlFor={`sus-${index}`}
                                        error={fieldError(
                                            `sus_responses.${index}`,
                                        )}
                                    >
                                        <Select
                                            id={`sus-${index}`}
                                            className="min-h-11!"
                                            value={
                                                form.data.sus_responses[index]
                                            }
                                            onChange={(event) =>
                                                setSusResponse(
                                                    index,
                                                    Number(event.target.value),
                                                )
                                            }
                                            options={[1, 2, 3, 4, 5].map(
                                                (value) => ({
                                                    value,
                                                    label: String(value),
                                                }),
                                            )}
                                        />
                                    </Field>
                                ))}
                            </div>
                        </fieldset>

                        <Field
                            label="Наблюдения фасилитатора"
                            htmlFor="session-notes"
                            hint="Без ФИО и контактных данных."
                            error={fieldError('notes')}
                        >
                            <Textarea
                                id="session-notes"
                                rows={4}
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                maxLength={2000}
                            />
                        </Field>

                        <Button
                            type="submit"
                            variant="primary"
                            className="min-h-11!"
                            loading={form.processing}
                            icon={<Save aria-hidden="true" size={16} />}
                        >
                            Сохранить сессию
                        </Button>
                    </form>
                </Blueprint>
            </section>

            {sessions.length > 0 && (
                <section
                    aria-labelledby="usability-sessions-heading"
                    className="mt-5"
                >
                    <h2
                        id="usability-sessions-heading"
                        className="text-xl font-semibold"
                    >
                        Сохранённые анонимные сессии
                    </h2>
                    <Blueprint className="overflow-x-auto p-0">
                        <table className="w-full border-collapse text-left text-sm">
                            <caption className="sr-only">
                                Последние анонимные usability-сессии
                            </caption>
                            <thead>
                                <tr className="border-b border-(--color-divider)">
                                    <th scope="col" className="px-4 py-3">
                                        Код
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Роль
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Опыт
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        SUS
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Завершена
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {sessions.map((session) => (
                                    <tr
                                        key={session.participant_code}
                                        className="border-b border-(--color-divider) last:border-0"
                                    >
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-mono"
                                        >
                                            {session.participant_code}
                                        </th>
                                        <td className="px-4 py-3">
                                            {ROLE_LABELS[session.role] ??
                                                session.role}
                                        </td>
                                        <td className="px-4 py-3">
                                            {EXPERIENCE_LABELS[
                                                session.experience_level
                                            ] ?? session.experience_level}
                                        </td>
                                        <td className="px-4 py-3 font-mono">
                                            {session.sus_score}
                                        </td>
                                        <td className="px-4 py-3">
                                            {new Intl.DateTimeFormat('ru-RU', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            }).format(
                                                new Date(session.completed_at),
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Blueprint>
                </section>
            )}
        </>
    );
}
