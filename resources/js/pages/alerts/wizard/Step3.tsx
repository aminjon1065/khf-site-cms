import { Copy } from 'lucide-react';
import { Trash2 } from 'lucide-react';
import type { ContentLocale } from '@/lib/domain';
import { Button } from '@/ui/Button';
import { Field, Input, Textarea } from '@/ui/Field';

/** Запись истории: метка времени одна, текст — по локалям. */
interface AlertUpdate {
    at: string;
    text: Record<string, string>;
}

/**
 * Значение для <input type="datetime-local">: он не понимает ISO со смещением
 * и молча остаётся пустым, если передать строку с зоной.
 */
function toLocalInput(value: string): string {
    if (!value) {
        return '';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    const pad = (n: number) => String(n).padStart(2, '0');

    return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`;
}
import { LanguageTabs } from '@/ui/Nav';
import { ChecklistPanel } from './ChecklistPanel';

export function Step3({
    data,
    setData,
    lang,
    setLang,
    compAll,
    copyFromRu,
    checklist,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    lang: ContentLocale;
    setLang: (l: ContentLocale) => void;
    compAll: Record<string, number>;
    copyFromRu: () => void;
    checklist: { label: string; ok: boolean }[];
}) {
    const setLoc = (field: string, value: string) =>
        setData(field, { ...data[field], [lang]: value });

    const setUpdate = (index: number, patch: Partial<AlertUpdate>) =>
        setData(
            'updates',
            (data.updates ?? []).map((entry: AlertUpdate, i: number) =>
                i === index ? { ...entry, ...patch } : entry,
            ),
        );

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'minmax(0,1fr) 300px',
                gap: 20,
            }}
            className="cms-two-col"
        >
            <div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        marginBottom: 14,
                    }}
                >
                    <LanguageTabs
                        active={lang}
                        onChange={setLang}
                        completeness={compAll}
                    />
                    {lang !== 'ru' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            icon={<Copy size={14} strokeWidth={1.5} />}
                            onClick={copyFromRu}
                        >
                            Скопировать из русского
                        </Button>
                    )}
                </div>
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 14,
                    }}
                >
                    <Field label="Заголовок" required>
                        <Input
                            value={data.title[lang] ?? ''}
                            onChange={(e) => setLoc('title', e.target.value)}
                            placeholder="Публичный заголовок предупреждения"
                        />
                    </Field>
                    <Field
                        label="Краткое описание"
                        required
                        hint="Для карточки на главной и push-уведомлений"
                    >
                        <Textarea
                            value={data.summary[lang] ?? ''}
                            onChange={(e) => setLoc('summary', e.target.value)}
                            style={{ minHeight: 70 }}
                        />
                    </Field>
                    <Field label="Полное описание" required>
                        <Textarea
                            value={data.body[lang] ?? ''}
                            onChange={(e) => setLoc('body', e.target.value)}
                            style={{ minHeight: 120 }}
                        />
                    </Field>
                    <Field label="Инструкция населению" required>
                        <Textarea
                            value={data.instructions[lang] ?? ''}
                            onChange={(e) =>
                                setLoc('instructions', e.target.value)
                            }
                            style={{ minHeight: 90 }}
                        />
                    </Field>
                    {/* История обновлений. Предупреждение живёт часами и
                        меняется: зона расширилась, уровень снижен, угроза
                        снята. Читателю важно не только текущее состояние, но и
                        что изменилось и когда. */}
                    <Field
                        label="История обновлений"
                        hint="Новые записи показываются первыми. Запись без текста на языке страницы на ней не выводится."
                    >
                        <div className="flex flex-col gap-2">
                            {(data.updates ?? []).map(
                                (entry: AlertUpdate, index: number) => (
                                    <div
                                        key={index}
                                        className="flex items-start gap-2"
                                    >
                                        <Input
                                            type="datetime-local"
                                            value={toLocalInput(entry.at)}
                                            aria-label="Время обновления"
                                            style={{ width: 200 }}
                                            onChange={(e) =>
                                                setUpdate(index, {
                                                    at: e.target.value,
                                                })
                                            }
                                        />
                                        <Textarea
                                            value={entry.text?.[lang] ?? ''}
                                            aria-label="Текст обновления"
                                            placeholder="Что изменилось"
                                            style={{ minHeight: 44, flex: 1 }}
                                            onChange={(e) =>
                                                setUpdate(index, {
                                                    text: {
                                                        ...(entry.text ?? {}),
                                                        [lang]: e.target.value,
                                                    },
                                                })
                                            }
                                        />
                                        <button
                                            type="button"
                                            className="btn btn-icon"
                                            aria-label="Удалить обновление"
                                            onClick={() =>
                                                setData(
                                                    'updates',
                                                    (data.updates ?? []).filter(
                                                        (_: AlertUpdate, i: number) =>
                                                            i !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2
                                                size={15}
                                                strokeWidth={1.5}
                                            />
                                        </button>
                                    </div>
                                ),
                            )}
                            <div>
                                <button
                                    type="button"
                                    className="btn"
                                    onClick={() =>
                                        setData('updates', [
                                            ...(data.updates ?? []),
                                            {
                                                at: toLocalInput(
                                                    new Date().toISOString(),
                                                ),
                                                text: {},
                                            },
                                        ])
                                    }
                                >
                                    Добавить обновление
                                </button>
                            </div>
                        </div>
                    </Field>

                    <Field label="Контактная информация">
                        <Input
                            value={data.contacts[lang] ?? ''}
                            onChange={(e) => setLoc('contacts', e.target.value)}
                            placeholder="Единая служба спасения — 112"
                        />
                    </Field>
                </div>
            </div>
            <ChecklistPanel checklist={checklist} compAll={compAll} />
        </div>
    );
}
