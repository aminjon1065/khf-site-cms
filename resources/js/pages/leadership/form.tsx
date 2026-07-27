import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Images, Save, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import LeaderController from '@/actions/App/Http/Controllers/Cms/LeaderController';
import type { ContentLocale } from '@/lib/domain';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Checkbox, Field, Input, Textarea } from '@/ui/Field';
import { MediaPicker } from '@/ui/MediaPicker';
import type { MediaItem } from '@/ui/MediaPicker';
import { LanguageTabs } from '@/ui/Nav';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };

interface LeaderData {
    id: number;
    role: LocaleMap;
    name: LocaleMap;
    meta: LocaleMap;
    bio: LocaleMap;
    is_chairman: boolean;
    sort: number;
    photo_url: string | null;
}

interface Props {
    leader: LeaderData | null;
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

function toLocaleMap(value?: Partial<LocaleMap> | null): LocaleMap {
    return { ...EMPTY, ...(value ?? {}) };
}

export default function LeadershipForm({ leader }: Props) {
    const isEdit = !!leader;
    const [lang, setLang] = useState<ContentLocale>('ru');
    const [photoPicker, setPhotoPicker] = useState(false);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const photoFileRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        role: toLocaleMap(leader?.role),
        name: toLocaleMap(leader?.name),
        meta: toLocaleMap(leader?.meta),
        bio: toLocaleMap(leader?.bio),
        is_chairman: leader?.is_chairman ?? false,
        sort: leader?.sort ?? 0,
        photo: null as File | null,
        photo_media_id: null as number | null,
        photo_remove: false,
    });
    const { data, setData, processing, errors } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const setLocale = (
        field: 'role' | 'name' | 'meta' | 'bio',
        value: string,
    ) => setData(field, { ...data[field], [lang]: value });

    const completeness: Partial<Record<ContentLocale, number>> = {
        ru: data.role.ru.trim() && data.name.ru.trim() ? 100 : 0,
        tg: data.role.tg.trim() && data.name.tg.trim() ? 100 : 0,
        en: data.role.en.trim() && data.name.en.trim() ? 100 : 0,
    };

    const pickPhotoFromLibrary = (item: MediaItem) => {
        setData('photo', null);
        setData('photo_media_id', item.id);
        setData('photo_remove', false);
        setPhotoPreview(item.url);
        setPhotoPicker(false);
    };

    const onPhotoFileChange = (file: File | null) => {
        if (file) {
            setData('photo', file);
            setData('photo_media_id', null);
            setData('photo_remove', false);
            setPhotoPreview(URL.createObjectURL(file));
        }
    };

    // Превью: свежий выбор (файл/медиатека) приоритетнее существующего фото.
    const photoSrc =
        photoPreview ??
        (leader?.photo_url && !data.photo_remove ? leader.photo_url : null);

    const submit = () => {
        if (isEdit && leader) {
            form.put(LeaderController.update.url(leader.id), {
                preserveScroll: true,
            });
        } else {
            form.post(LeaderController.store.url(), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Редактирование записи' : 'Новая запись'} />

            <PageHeader
                eyebrow={
                    <Link
                        href={LeaderController.index.url()}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Руководство
                    </Link>
                }
                title={isEdit ? leader!.name.ru || 'Запись' : 'Новая запись'}
                subtitle="Должность, ФИО и биография для страницы «Руководство»."
                actions={
                    <Button
                        variant="primary"
                        icon={<Save size={16} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={submit}
                    >
                        Сохранить
                    </Button>
                }
            />

            <div style={{ marginBottom: 14 }}>
                <LanguageTabs
                    active={lang}
                    onChange={setLang}
                    completeness={completeness}
                />
            </div>

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                <Blueprint style={{ padding: 20 }}>
                    <h3 className="ui-card-title" style={{ marginTop: 0 }}>
                        Основные данные
                    </h3>
                    <Field
                        label={`Должность (${lang.toUpperCase()})`}
                        error={fieldError('role.ru')}
                        required={lang === 'ru'}
                    >
                        <Input
                            value={data.role[lang]}
                            onChange={(e) => setLocale('role', e.target.value)}
                            placeholder="Председатель Комитета"
                        />
                    </Field>
                    <Field
                        label={`ФИО (${lang.toUpperCase()})`}
                        error={fieldError('name.ru')}
                        required={lang === 'ru'}
                    >
                        <Input
                            value={data.name[lang]}
                            onChange={(e) => setLocale('name', e.target.value)}
                            placeholder="Рустам Назарзода"
                        />
                    </Field>
                    <Field label={`Звание и стаж (${lang.toUpperCase()})`}>
                        <Input
                            value={data.meta[lang]}
                            onChange={(e) => setLocale('meta', e.target.value)}
                            placeholder="Генерал-лейтенант · руководит Комитетом с 2016 года"
                        />
                    </Field>
                    <Field label={`Биография (${lang.toUpperCase()})`}>
                        <Textarea
                            value={data.bio[lang]}
                            onChange={(e) => setLocale('bio', e.target.value)}
                            rows={5}
                            placeholder="Зона ответственности и краткая биография."
                        />
                    </Field>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 12,
                            alignItems: 'end',
                        }}
                    >
                        <Field label="Порядок">
                            <Input
                                type="number"
                                min={0}
                                value={String(data.sort)}
                                onChange={(e) =>
                                    setData('sort', Number(e.target.value) || 0)
                                }
                            />
                        </Field>
                        <Checkbox
                            label="Председатель Комитета"
                            checked={data.is_chairman}
                            onChange={(e) =>
                                setData('is_chairman', e.target.checked)
                            }
                        />
                    </div>
                    {data.is_chairman && (
                        <p
                            style={{
                                margin: '4px 0 0',
                                fontSize: 12,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            Председатель показывается отдельной широкой
                            карточкой; текущий председатель (если есть) будет
                            снят с этой роли автоматически.
                        </p>
                    )}
                </Blueprint>

                <Blueprint style={{ padding: 20 }}>
                    <h3
                        className="ui-card-title"
                        style={{ marginTop: 0, marginBottom: 14 }}
                    >
                        Фотография
                    </h3>
                    {photoSrc && (
                        <img
                            src={photoSrc}
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
                        ref={photoFileRef}
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        hidden
                        onChange={(e) => {
                            const file = e.target.files?.[0] ?? null;
                            onPhotoFileChange(file);
                            e.target.value = '';
                        }}
                    />
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <Button
                            variant="secondary"
                            icon={<Upload size={15} strokeWidth={1.75} />}
                            onClick={() => photoFileRef.current?.click()}
                        >
                            Загрузить
                        </Button>
                        <Button
                            variant="secondary"
                            icon={<Images size={15} strokeWidth={1.75} />}
                            onClick={() => setPhotoPicker(true)}
                        >
                            Из медиатеки
                        </Button>
                    </div>

                    {!!leader?.photo_url && (
                        <Checkbox
                            className="mt-2"
                            label="Удалить текущее фото"
                            checked={data.photo_remove}
                            onChange={(e) =>
                                setData('photo_remove', e.target.checked)
                            }
                        />
                    )}

                    <MediaPicker
                        open={photoPicker}
                        onClose={() => setPhotoPicker(false)}
                        onSelect={pickPhotoFromLibrary}
                    />
                </Blueprint>
            </div>

            <div className="news-form-actions">
                <div style={{ flex: 1 }} />
                <Button
                    variant="primary"
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={submit}
                >
                    Сохранить запись
                </Button>
            </div>
        </>
    );
}
