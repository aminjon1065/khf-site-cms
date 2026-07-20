import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    Images,
    Plus,
    Save,
    Send,
    Upload,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { useSaveShortcut } from '@/hooks/use-save-shortcut';
import { useCan } from '@/lib/auth';
import type { ContentLocale, ContentStatus } from '@/lib/domain';
import { StatusBadge } from '@/ui/Badge';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton, LinkButton } from '@/ui/Button';
import { Checkbox, Field, Input, Select, Textarea } from '@/ui/Field';
import { MediaPicker  } from '@/ui/MediaPicker';
import type {MediaItem} from '@/ui/MediaPicker';
import { LanguageTabs } from '@/ui/Nav';
import { Dropdown } from '@/ui/Overlay';
import { PageHeader } from '@/ui/PageHeader';
import { RichEditor } from '@/ui/RichEditor';

type LocaleMap = { ru: string; tg: string; en: string };
type StepMap = { ru: string[]; tg: string[]; en: string[] };
type SectionKey = 'before' | 'during' | 'after' | 'prohibited';
type Sections = Record<SectionKey, StepMap>;
type PublishMode = 'now' | 'schedule' | 'review';

interface Option {
    value: string;
    label: string;
}

interface InstructionData {
    id: number;
    name: LocaleMap;
    summary: LocaleMap;
    body: LocaleMap;
    slug: string | null;
    status: ContentStatus;
    hazard_type: string | null;
    is_priority: boolean;
    sort: number;
    sections: Sections;
    image_url: string | null;
    languages: Record<string, number>;
}

interface Props {
    instruction: InstructionData | null;
    reference: {
        hazards: Option[];
        authors: Option[];
        sectionKeys: { key: SectionKey; label: string }[];
    };
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

function emptySections(): Sections {
    return {
        before: { ru: [], tg: [], en: [] },
        during: { ru: [], tg: [], en: [] },
        after: { ru: [], tg: [], en: [] },
        prohibited: { ru: [], tg: [], en: [] },
    };
}

export default function InstructionForm({ instruction, reference }: Props) {
    const can = useCan();
    const isEdit = !!instruction;
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [imagePicker, setImagePicker] = useState(false);
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const imageFileRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        name: { ...EMPTY, ...instruction?.name } as LocaleMap,
        summary: { ...EMPTY, ...instruction?.summary } as LocaleMap,
        body: { ...EMPTY, ...instruction?.body } as LocaleMap,
        slug: instruction?.slug ?? '',
        hazard_type: (instruction?.hazard_type ?? '') as string,
        is_priority: instruction?.is_priority ?? false,
        sort: instruction?.sort ?? 0,
        sections: (instruction?.sections ?? emptySections()) as Sections,
        image: null as File | null,
        image_media_id: null as number | null,
        image_remove: false,
        publish_mode: 'review' as PublishMode,
        action: 'draft' as 'draft' | 'submit',
    });
    const { data, setData, processing, errors } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const pickImageFromLibrary = (item: MediaItem) => {
        setData('image', null);
        setData('image_media_id', item.id);
        setData('image_remove', false);
        setImagePreview(item.url);
        setImagePicker(false);
    };

    // Превью: свежий выбор (файл/медиатека) приоритетнее существующего файла.
    const imageSrc =
        imagePreview ??
        (instruction?.image_url && !data.image_remove
            ? instruction.image_url
            : null);

    const completeness = (locale: ContentLocale): number => {
        const filled = (['name', 'summary'] as const).filter(
            (f) => (data[f][locale] ?? '').trim() !== '',
        ).length;

        return Math.round((filled / 2) * 100);
    };
    const compAll = {
        tg: completeness('tg'),
        ru: completeness('ru'),
        en: completeness('en'),
    };

    const setLocaleField = (
        field: 'name' | 'summary' | 'body',
        value: string,
    ) => {
        setData(field, { ...data[field], [lang]: value });
    };

    // Immutably update the step list of one section for the active language.
    const mutateSteps = (
        section: SectionKey,
        next: (steps: string[]) => string[],
    ) => {
        setData('sections', {
            ...data.sections,
            [section]: {
                ...data.sections[section],
                [lang]: next(data.sections[section][lang]),
            },
        });
    };

    const addStep = (section: SectionKey) =>
        mutateSteps(section, (steps) => [...steps, '']);
    const updateStep = (section: SectionKey, i: number, value: string) =>
        mutateSteps(section, (steps) =>
            steps.map((s, idx) => (idx === i ? value : s)),
        );
    const removeStep = (section: SectionKey, i: number) =>
        mutateSteps(section, (steps) => steps.filter((_, idx) => idx !== i));

    const submit = (
        action: 'draft' | 'submit',
        mode?: PublishMode,
        stay = false,
    ) => {
        form.transform((d) => ({
            ...d,
            action,
            publish_mode: mode ?? d.publish_mode,
            stay,
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        form.post(
            isEdit ? `/instructions/${instruction!.id}` : '/instructions',
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: stay,
            },
        );
    };

    // Ctrl/Cmd+S — сохранить черновик и остаться в редакторе (stay = true).
    useSaveShortcut(() => submit('draft', undefined, true), !processing);

    return (
        <>
            <Head
                title={
                    isEdit ? 'Редактирование инструкции' : 'Новая инструкция'
                }
            />

            <PageHeader
                eyebrow={
                    <Link
                        href="/instructions"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Инструкции
                    </Link>
                }
                title={
                    isEdit ? 'Редактирование инструкции' : 'Новая инструкция'
                }
                subtitle="Заполните название, краткое описание и шаги по блокам «До / Во время / После / Нельзя»."
                actions={
                    isEdit && instruction ? (
                        <StatusBadge status={instruction.status} />
                    ) : null
                }
            />

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1.7fr 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                {/* ------------------------------------------------ main */}
                <div
                    style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
                >
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
                                Основное
                            </h3>
                            <LanguageTabs
                                active={lang}
                                onChange={setLang}
                                completeness={compAll}
                            />
                        </div>

                        <Field
                            label="Название"
                            required={lang === 'ru'}
                            error={
                                lang === 'ru' ? fieldError('name.ru') : undefined
                            }
                        >
                            <Input
                                value={data.name[lang]}
                                onChange={(e) =>
                                    setLocaleField('name', e.target.value)
                                }
                                hasError={lang === 'ru' && !!fieldError('name.ru')}
                                placeholder={
                                    lang === 'ru'
                                        ? 'Например: Действия при землетрясении'
                                        : 'Перевод названия'
                                }
                                maxLength={255}
                            />
                        </Field>

                        <Field
                            label="Краткое описание"
                            hint="Короткая подпись в каталоге инструкций."
                        >
                            <Textarea
                                value={data.summary[lang]}
                                onChange={(e) =>
                                    setLocaleField('summary', e.target.value)
                                }
                                style={{ minHeight: 70 }}
                                maxLength={1000}
                            />
                        </Field>
                    </Blueprint>

                    {/* ------------------------------------ sections editor */}
                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 4 }}
                        >
                            Шаги инструкции
                        </h3>
                        <p
                            style={{
                                margin: '0 0 12px',
                                fontSize: 12.5,
                                color: 'var(--color-neutral-600)',
                            }}
                        >
                            Язык блоков переключается вкладками выше (сейчас:{' '}
                            <b>{lang.toUpperCase()}</b>).
                        </p>

                        {reference.sectionKeys.map(({ key, label }) => (
                            <div key={key} style={{ marginBottom: 18 }}>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        marginBottom: 8,
                                    }}
                                >
                                    <span
                                        style={{
                                            fontFamily: 'var(--font-heading)',
                                            fontWeight: 600,
                                            fontSize: 13.5,
                                        }}
                                    >
                                        {label}
                                    </span>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        icon={<Plus size={14} strokeWidth={2} />}
                                        onClick={() => addStep(key)}
                                    >
                                        Шаг
                                    </Button>
                                </div>

                                {data.sections[key][lang].length === 0 ? (
                                    <p
                                        style={{
                                            margin: 0,
                                            fontSize: 12.5,
                                            color: 'var(--color-neutral-400)',
                                        }}
                                    >
                                        Шаги не добавлены.
                                    </p>
                                ) : (
                                    <div
                                        style={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 6,
                                        }}
                                    >
                                        {data.sections[key][lang].map(
                                            (step, i) => (
                                                <div
                                                    key={i}
                                                    style={{
                                                        display: 'flex',
                                                        gap: 6,
                                                        alignItems: 'flex-start',
                                                    }}
                                                >
                                                    <span
                                                        className="ui-mono"
                                                        style={{
                                                            width: 22,
                                                            paddingTop: 8,
                                                            fontSize: 12.5,
                                                            color: 'var(--color-neutral-500)',
                                                        }}
                                                    >
                                                        {String(i + 1).padStart(
                                                            2,
                                                            '0',
                                                        )}
                                                    </span>
                                                    <Textarea
                                                        value={step}
                                                        onChange={(e) =>
                                                            updateStep(
                                                                key,
                                                                i,
                                                                e.target.value,
                                                            )
                                                        }
                                                        style={{
                                                            minHeight: 40,
                                                            flex: 1,
                                                        }}
                                                        maxLength={1000}
                                                    />
                                                    <IconButton
                                                        label="Удалить шаг"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            removeStep(key, i)
                                                        }
                                                    >
                                                        <X
                                                            size={15}
                                                            strokeWidth={1.5}
                                                        />
                                                    </IconButton>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>
                        ))}
                    </Blueprint>

                    {/* ------------------------------------ detail body */}
                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 4 }}
                        >
                            Подробное описание
                        </h3>
                        <p
                            style={{
                                margin: '0 0 12px',
                                fontSize: 12.5,
                                color: 'var(--color-neutral-600)',
                            }}
                        >
                            Необязательный развёрнутый текст под шагами (язык:{' '}
                            <b>{lang.toUpperCase()}</b>).
                        </p>
                        <RichEditor
                            key={lang}
                            value={data.body[lang]}
                            onChange={(html) => setLocaleField('body', html)}
                            placeholder="Развёрнутое описание, контекст, ссылки на документы…"
                        />
                    </Blueprint>
                </div>

                {/* --------------------------------------------- sidebar */}
                <div
                    style={{ display: 'flex', flexDirection: 'column', gap: 16 }}
                >
                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            Параметры
                        </h3>

                        <Field label="Тип события" error={fieldError('hazard_type')}>
                            <Select
                                value={data.hazard_type}
                                onChange={(e) =>
                                    setData('hazard_type', e.target.value)
                                }
                                placeholder="Не выбран"
                                options={reference.hazards}
                            />
                        </Field>

                        <Field
                            label="Порядок сортировки"
                            hint="Меньше — выше в каталоге."
                        >
                            <Input
                                type="number"
                                min={0}
                                value={String(data.sort)}
                                onChange={(e) =>
                                    setData('sort', Number(e.target.value) || 0)
                                }
                            />
                        </Field>

                        <Field
                            label="Адрес (slug)"
                            hint="Пусто — сгенерируется из названия."
                            error={fieldError('slug')}
                        >
                            <Input
                                value={data.slug}
                                onChange={(e) => setData('slug', e.target.value)}
                                hasError={!!fieldError('slug')}
                                placeholder="deystviya-pri-zemletryasenii"
                            />
                        </Field>

                        <Checkbox
                            label="Закрепить (приоритетная плитка на сайте)"
                            checked={data.is_priority}
                            onChange={(e) =>
                                setData('is_priority', e.target.checked)
                            }
                        />
                    </Blueprint>

                    <Blueprint style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            Иллюстрация
                        </h3>
                        {imageSrc && (
                            <img
                                src={imageSrc}
                                alt=""
                                style={{
                                    width: '100%',
                                    borderRadius: 6,
                                    marginBottom: 10,
                                    border: '1px solid var(--color-divider)',
                                }}
                            />
                        )}

                        <input
                            ref={imageFileRef}
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            hidden
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;

                                if (file) {
                                    setData('image', file);
                                    setData('image_media_id', null);
                                    setData('image_remove', false);
                                    setImagePreview(URL.createObjectURL(file));
                                }

                                e.target.value = '';
                            }}
                        />
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <Button
                                variant="secondary"
                                icon={<Upload size={15} strokeWidth={1.75} />}
                                onClick={() => imageFileRef.current?.click()}
                            >
                                Загрузить
                            </Button>
                            <Button
                                variant="secondary"
                                icon={<Images size={15} strokeWidth={1.75} />}
                                onClick={() => setImagePicker(true)}
                            >
                                Из медиатеки
                            </Button>
                        </div>

                        {fieldError('image') && (
                            <div
                                style={{
                                    color: 'var(--danger)',
                                    fontSize: 12,
                                    marginTop: 6,
                                }}
                            >
                                {fieldError('image')}
                            </div>
                        )}
                        {instruction?.image_url && (
                            <Checkbox
                                className="mt-2"
                                label="Удалить текущее изображение"
                                checked={data.image_remove}
                                onChange={(e) =>
                                    setData('image_remove', e.target.checked)
                                }
                            />
                        )}

                        <MediaPicker
                            open={imagePicker}
                            onClose={() => setImagePicker(false)}
                            onSelect={pickImageFromLibrary}
                        />
                    </Blueprint>
                </div>
            </div>

            {/* --------------------------------------------- sticky actions */}
            <div className="news-form-actions">
                <LinkButton href="/instructions" variant="ghost">
                    Отмена
                </LinkButton>
                <div style={{ flex: 1 }} />
                <Button
                    variant="secondary"
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={() => submit('draft')}
                >
                    Сохранить черновик
                </Button>
                {can('instructions.publish') ? (
                    <Dropdown
                        align="right"
                        trigger={({ toggle }) => (
                            <Button
                                variant="primary"
                                iconRight={
                                    <ChevronDown size={15} strokeWidth={2} />
                                }
                                onClick={toggle}
                            >
                                Опубликовать
                            </Button>
                        )}
                        items={[
                            {
                                label: 'Опубликовать сейчас',
                                onSelect: () => submit('submit', 'now'),
                            },
                            { separator: true },
                            {
                                label: 'Отправить на согласование',
                                onSelect: () => submit('submit', 'review'),
                            },
                        ]}
                    />
                ) : (
                    <Button
                        variant="primary"
                        icon={<Send size={15} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={() => submit('submit', 'review')}
                    >
                        На согласование
                    </Button>
                )}
            </div>
        </>
    );
}
