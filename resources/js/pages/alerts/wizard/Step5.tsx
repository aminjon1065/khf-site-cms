import type { Severity } from '@/lib/domain';
import { SeverityBadge } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import {
    Checkbox,
    DatePicker,
    Field,
    Radio,
    Segmented,
    Select,
} from '@/ui/Field';
import { ChecklistPanel } from './ChecklistPanel';
import type { Reference } from './types';

export function Step5({
    data,
    setData,
    reference,
    publishMode,
    setPublishMode,
    preview,
    setPreview,
    toggleChannel,
    checklist,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    reference: Reference;
    publishMode: 'now' | 'schedule' | 'review';
    setPublishMode: (m: 'now' | 'schedule' | 'review') => void;
    preview: 'desktop' | 'mobile';
    setPreview: (p: 'desktop' | 'mobile') => void;
    toggleChannel: (v: string) => void;
    checklist: { label: string; ok: boolean }[];
}) {
    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'minmax(0,1fr) 420px',
                gap: 20,
            }}
            className="cms-two-col"
        >
            <div
                style={{
                    maxWidth: 520,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 16,
                }}
            >
                <Field label="Способ публикации">
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                        }}
                    >
                        <Radio
                            name="pm"
                            label="Опубликовать сейчас"
                            checked={publishMode === 'now'}
                            onChange={() => setPublishMode('now')}
                        />
                        <Radio
                            name="pm"
                            label="Запланировать дату и время"
                            checked={publishMode === 'schedule'}
                            onChange={() => setPublishMode('schedule')}
                        />
                        <Radio
                            name="pm"
                            label="Отправить на согласование руководителю"
                            checked={publishMode === 'review'}
                            onChange={() => setPublishMode('review')}
                        />
                    </div>
                </Field>
                {publishMode === 'schedule' && (
                    <Field label="Дата и время публикации">
                        <DatePicker
                            withTime
                            value={data.scheduled_at}
                            onChange={(e) =>
                                setData('scheduled_at', e.target.value)
                            }
                        />
                    </Field>
                )}
                {publishMode === 'review' && (
                    <Field label="Согласующий">
                        <Select
                            placeholder="Выберите согласующего"
                            value={data.approver_id}
                            onChange={(e) =>
                                setData(
                                    'approver_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                        >
                            {reference.approvers.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                )}
                <Field label="Каналы публикации">
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                        }}
                    >
                        {reference.channels
                            .filter((c) => c.value !== 'sms')
                            .map((c) => (
                                <Checkbox
                                    key={c.value}
                                    label={c.label}
                                    checked={data.channels.includes(c.value)}
                                    onChange={() => toggleChannel(c.value)}
                                />
                            ))}
                    </div>
                </Field>
            </div>

            <div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        marginBottom: 8,
                    }}
                >
                    <div className="ui-kicker">
                        Предпросмотр · карточка на главной
                    </div>
                    <Segmented
                        value={preview}
                        onChange={setPreview}
                        options={[
                            { value: 'desktop', label: 'Desktop' },
                            { value: 'mobile', label: 'Mobile' },
                        ]}
                    />
                </div>
                <Blueprint
                    style={{
                        padding: 14,
                        borderTop: '3px solid var(--sev-warning)',
                        maxWidth: preview === 'mobile' ? 320 : '100%',
                    }}
                >
                    <SeverityBadge severity={data.severity as Severity} />
                    <div
                        style={{
                            fontFamily: 'var(--font-heading)',
                            fontWeight: 600,
                            fontSize: 16,
                            marginTop: 8,
                        }}
                    >
                        {data.title.ru || 'Заголовок предупреждения'}
                    </div>
                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--color-neutral-600)',
                            marginTop: 4,
                        }}
                    >
                        {data.ends_at
                            ? `Действует до ${new Date(data.ends_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}`
                            : 'Срок не задан'}
                    </div>
                    <p style={{ fontSize: 13, marginTop: 8, marginBottom: 0 }}>
                        {data.summary.ru || 'Краткое описание появится здесь.'}
                    </p>
                </Blueprint>
                <div style={{ marginTop: 16 }}>
                    <ChecklistPanel checklist={checklist} />
                </div>
            </div>
        </div>
    );
}
