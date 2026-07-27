import { Copy } from 'lucide-react';
import type { ContentLocale } from '@/lib/domain';
import { Button } from '@/ui/Button';
import { Field, Input, Textarea } from '@/ui/Field';
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
