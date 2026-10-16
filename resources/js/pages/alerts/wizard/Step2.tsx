import { useState } from 'react';
import type { ContentLocale } from '@/lib/domain';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Checkbox, Field, Radio, Textarea } from '@/ui/Field';
import { LanguageTabs } from '@/ui/Nav';
import type { Reference } from './types';

export function Step2({
    data,
    setData,
    reference,
    toggleRegion,
    toggleDistrict,
    lang,
    setLang,
}: {
    data: any;
    setData: (k: string, v: unknown) => void;
    reference: Reference;
    toggleRegion: (id: number) => void;
    toggleDistrict: (id: number) => void;
    /** Shared with «Содержание»: the language picked here opens there too. */
    lang: ContentLocale;
    setLang: (l: ContentLocale) => void;
}) {
    const [expanded, setExpanded] = useState<number | null>(null);
    const noteFilled = (locale: ContentLocale): boolean =>
        (data.territory_note[locale] ?? '').trim() !== '';

    return (
        <div
            style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}
            className="cms-two-col"
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Field label="Затронутая территория" required>
                    <div style={{ display: 'flex', gap: 18 }}>
                        <Radio
                            name="territory"
                            label="Вся страна"
                            checked={data.territory_type === 'country'}
                            onChange={() =>
                                setData('territory_type', 'country')
                            }
                        />
                        <Radio
                            name="territory"
                            label="Отдельные регионы и районы"
                            checked={data.territory_type === 'regions'}
                            onChange={() =>
                                setData('territory_type', 'regions')
                            }
                        />
                    </div>
                </Field>

                {data.territory_type === 'regions' && (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 6,
                        }}
                    >
                        {reference.regions.map((r) => {
                            const selectedDistricts = r.districts.filter((d) =>
                                data.districts.includes(d.id),
                            ).length;

                            return (
                                <Blueprint
                                    key={r.id}
                                    corners={false}
                                    style={{ padding: 10 }}
                                >
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                        }}
                                    >
                                        <Checkbox
                                            checked={data.regions.includes(
                                                r.id,
                                            )}
                                            onChange={() => toggleRegion(r.id)}
                                        />
                                        <div style={{ flex: 1 }}>
                                            <div
                                                style={{
                                                    fontSize: 13.5,
                                                    fontWeight: 500,
                                                }}
                                            >
                                                {r.name}
                                            </div>
                                            <div
                                                style={{
                                                    fontSize: 11.5,
                                                    color: 'var(--color-neutral-500)',
                                                }}
                                            >
                                                {r.districts_count} районов
                                                {selectedDistricts > 0
                                                    ? ` · выбрано ${selectedDistricts}`
                                                    : ''}
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setExpanded(
                                                    expanded === r.id
                                                        ? null
                                                        : r.id,
                                                )
                                            }
                                        >
                                            Районы
                                        </Button>
                                    </div>
                                    {expanded === r.id && (
                                        <div
                                            style={{
                                                display: 'flex',
                                                flexWrap: 'wrap',
                                                gap: 10,
                                                marginTop: 8,
                                                paddingLeft: 26,
                                            }}
                                        >
                                            {r.districts.map((d) => (
                                                <Checkbox
                                                    key={d.id}
                                                    label={d.name}
                                                    checked={data.districts.includes(
                                                        d.id,
                                                    )}
                                                    onChange={() =>
                                                        toggleDistrict(d.id)
                                                    }
                                                />
                                            ))}
                                        </div>
                                    )}
                                </Blueprint>
                            );
                        })}
                    </div>
                )}

                {/* Shown on the site under the map, on each language
                    version that has it. */}
                <Field
                    label="Текстовое описание территории"
                    hint="Видно на сайте под картой — на каждом языке, где заполнено."
                >
                    <div style={{ marginBottom: 8 }}>
                        <LanguageTabs
                            active={lang}
                            onChange={setLang}
                            completeness={{
                                tg: noteFilled('tg') ? 100 : 0,
                                ru: noteFilled('ru') ? 100 : 0,
                                en: noteFilled('en') ? 100 : 0,
                            }}
                        />
                    </div>
                    <Textarea
                        value={data.territory_note[lang] ?? ''}
                        onChange={(e) =>
                            setData('territory_note', {
                                ...data.territory_note,
                                [lang]: e.target.value,
                            })
                        }
                        placeholder="Например: предгорные районы Хатлонской области — Дангара, Фархор, Восе…"
                    />
                </Field>
            </div>

            <div>
                <div className="ui-kicker" style={{ marginBottom: 8 }}>
                    Схема регионов · выбор кликом
                </div>
                <Blueprint
                    className="cms-stack-narrow"
                    style={{
                        padding: 16,
                        display: 'grid',
                        gridTemplateColumns: '1fr 1fr',
                        gap: 10,
                    }}
                >
                    {reference.regions.map((r) => {
                        const active = data.regions.includes(r.id);

                        return (
                            <button
                                key={r.id}
                                type="button"
                                onClick={() => toggleRegion(r.id)}
                                style={{
                                    padding: 14,
                                    border: `1px solid ${active ? 'var(--sev-warning)' : 'var(--color-divider)'}`,
                                    background: active
                                        ? 'var(--sev-warning-soft)'
                                        : 'var(--color-surface)',
                                    cursor: 'pointer',
                                    textAlign: 'left',
                                    fontSize: 12.5,
                                }}
                            >
                                {r.name}
                            </button>
                        );
                    })}
                </Blueprint>
                <div
                    style={{
                        display: 'flex',
                        gap: 14,
                        marginTop: 10,
                        fontSize: 12,
                        color: 'var(--color-neutral-600)',
                    }}
                >
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                        }}
                    >
                        <span
                            style={{
                                width: 12,
                                height: 12,
                                background: 'var(--color-surface)',
                                border: '1px solid var(--color-divider)',
                            }}
                        />{' '}
                        штатно
                    </span>
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                        }}
                    >
                        <span
                            style={{
                                width: 12,
                                height: 12,
                                background: 'var(--sev-warning-soft)',
                                border: '1px solid var(--sev-warning)',
                            }}
                        />{' '}
                        в зоне предупреждения
                    </span>
                </div>
            </div>
        </div>
    );
}
