import type { ContentLocale } from '@/lib/domain';
import { Blueprint } from '@/ui/Blueprint';
import { Field, Input, Textarea } from '@/ui/Field';
import { LanguageTabs } from '@/ui/Nav';
import { RichEditor } from '@/ui/RichEditor';

export function DescriptionCard({
    data,
    lang,
    setLang,
    compAll,
    fieldError,
    setLocaleField,
}: {
    data: any;
    lang: ContentLocale;
    setLang: (l: ContentLocale) => void;
    compAll: Record<string, number>;
    fieldError: (key: string) => string | undefined;
    setLocaleField: (
        field: 'title' | 'summary' | 'body',
        value: string,
    ) => void;
}) {
    return (
        <Blueprint style={{ padding: 20 }}>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    marginBottom: 14,
                    flexWrap: 'wrap',
                    gap: 10,
                }}
            >
                <h3 className="ui-card-title" style={{ margin: 0 }}>
                    Описание
                </h3>
                <LanguageTabs
                    active={lang}
                    onChange={setLang}
                    completeness={compAll}
                />
            </div>

            <Field
                label="Название проекта"
                required={lang === 'ru'}
                error={lang === 'ru' ? fieldError('title.ru') : undefined}
            >
                <Input
                    value={data.title[lang]}
                    onChange={(e) => setLocaleField('title', e.target.value)}
                    hasError={lang === 'ru' && !!fieldError('title.ru')}
                    placeholder={
                        lang === 'ru'
                            ? 'Например: Модернизация системы оповещения'
                            : 'Перевод названия'
                    }
                    maxLength={255}
                />
            </Field>

            <Field
                label="Краткое описание"
                hint="Показывается в карточке проекта и как вступление."
            >
                <Textarea
                    value={data.summary[lang]}
                    onChange={(e) => setLocaleField('summary', e.target.value)}
                    style={{ minHeight: 72 }}
                    maxLength={1000}
                />
            </Field>

            <Field label="Подробное описание">
                <RichEditor
                    key={lang}
                    value={data.body[lang]}
                    onChange={(html) => setLocaleField('body', html)}
                    placeholder="Подробно опишите проект…"
                />
            </Field>
        </Blueprint>
    );
}
