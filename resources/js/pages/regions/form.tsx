import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowLeft, ArrowUp, Plus, Save, X } from 'lucide-react';
import { useState } from 'react';
import type { ContentLocale } from '@/lib/domain';
import { Blueprint } from '@/ui/Blueprint';
import { Button, IconButton } from '@/ui/Button';
import { Field, Input, Select } from '@/ui/Field';
import { LanguageTabs } from '@/ui/Nav';
import { PageHeader } from '@/ui/PageHeader';

type LocaleMap = { ru: string; tg: string; en: string };
interface Option {
    value: string;
    label: string;
}
interface DistrictRow {
    id: number | null;
    name: LocaleMap;
}

interface RegionData {
    id: number;
    name: LocaleMap;
    head: LocaleMap;
    code: string;
    type: string;
    regional_center: string | null;
    address: LocaleMap;
    phone: string | null;
    duty_phone: string | null;
    email: string | null;
    districts_count: number;
    sort: number;
    districts: { id: number; name: LocaleMap }[];
}

interface Props {
    region: RegionData | null;
    reference: { types: Option[] };
}

const EMPTY: LocaleMap = { ru: '', tg: '', en: '' };

function toLocaleMap(value?: Partial<LocaleMap> | null): LocaleMap {
    return { ...EMPTY, ...(value ?? {}) };
}

export default function RegionForm({ region, reference }: Props) {
    const isEdit = !!region;
    const [lang, setLang] = useState<ContentLocale>('ru');

    const form = useForm({
        name: toLocaleMap(region?.name),
        head: toLocaleMap(region?.head),
        address: toLocaleMap(region?.address),
        code: region?.code ?? '',
        type: region?.type ?? reference.types[0]?.value ?? 'oblast',
        regional_center: region?.regional_center ?? '',
        phone: region?.phone ?? '',
        duty_phone: region?.duty_phone ?? '',
        email: region?.email ?? '',
        districts_count: region?.districts_count ?? 0,
        sort: region?.sort ?? 0,
        districts: (region?.districts ?? []).map((d) => ({
            id: d.id,
            name: toLocaleMap(d.name),
        })) as DistrictRow[],
    });
    const { data, setData, processing, errors } = form;

    const fieldError = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const completeness: Partial<Record<ContentLocale, number>> = {
        ru: data.name.ru.trim() ? 100 : 0,
        tg: data.name.tg.trim() ? 100 : 0,
        en: data.name.en.trim() ? 100 : 0,
    };

    const setLocale = (field: 'name' | 'head' | 'address', value: string) =>
        setData(field, { ...data[field], [lang]: value });

    // --- districts (curated) ---
    const setDistricts = (next: DistrictRow[]) => setData('districts', next);
    const addDistrict = () =>
        setDistricts([...data.districts, { id: null, name: { ...EMPTY } }]);
    const updateDistrict = (i: number, value: string) =>
        setDistricts(
            data.districts.map((d, idx) =>
                idx === i ? { ...d, name: { ...d.name, [lang]: value } } : d,
            ),
        );
    const removeDistrict = (i: number) =>
        setDistricts(data.districts.filter((_, idx) => idx !== i));
    const moveDistrict = (i: number, dir: -1 | 1) => {
        const j = i + dir;

        if (j < 0 || j >= data.districts.length) {
            return;
        }

        const next = [...data.districts];
        [next[i], next[j]] = [next[j], next[i]];
        setDistricts(next);
    };

    const submit = () => {
        if (isEdit && region) {
            form.put(`/regions/${region.id}`, { preserveScroll: true });
        } else {
            form.post('/regions', { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Редактирование региона' : 'Новый регион'} />

            <PageHeader
                eyebrow={
                    <Link
                        href="/regions"
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            color: 'var(--color-neutral-600)',
                            textDecoration: 'none',
                        }}
                    >
                        <ArrowLeft size={14} strokeWidth={1.75} /> Регионы и
                        районы
                    </Link>
                }
                title={isEdit ? region!.name.ru || 'Регион' : 'Новый регион'}
                subtitle="Реквизиты регионального управления, контакты и справочник районов."
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
                        label={`Название региона (${lang.toUpperCase()})`}
                        error={fieldError('name.ru')}
                        required={lang === 'ru'}
                    >
                        <Input
                            value={data.name[lang]}
                            onChange={(e) => setLocale('name', e.target.value)}
                            placeholder="Согдийская область"
                        />
                    </Field>
                    <Field label="Код" error={fieldError('code')} required>
                        <Input
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            placeholder="sughd"
                            className="ui-mono"
                        />
                    </Field>
                    <Field label="Тип" error={fieldError('type')} required>
                        <Select
                            value={data.type}
                            options={reference.types}
                            onChange={(e) => setData('type', e.target.value)}
                        />
                    </Field>
                    <Field label="Областной центр">
                        <Input
                            value={data.regional_center}
                            onChange={(e) =>
                                setData('regional_center', e.target.value)
                            }
                            placeholder="Худжанд"
                        />
                    </Field>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: 12,
                        }}
                    >
                        <Field
                            label="Всего районов"
                            error={fieldError('districts_count')}
                        >
                            <Input
                                type="number"
                                min={0}
                                value={String(data.districts_count)}
                                onChange={(e) =>
                                    setData(
                                        'districts_count',
                                        Number(e.target.value) || 0,
                                    )
                                }
                            />
                        </Field>
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
                    </div>
                </Blueprint>

                <Blueprint style={{ padding: 20 }}>
                    <h3 className="ui-card-title" style={{ marginTop: 0 }}>
                        Региональное управление
                    </h3>
                    <Field
                        label={`Название управления (${lang.toUpperCase()})`}
                    >
                        <Input
                            value={data.head[lang]}
                            onChange={(e) => setLocale('head', e.target.value)}
                            placeholder="Управление по Согдийской области"
                        />
                    </Field>
                    <Field label={`Адрес (${lang.toUpperCase()})`}>
                        <Input
                            value={data.address[lang]}
                            onChange={(e) =>
                                setLocale('address', e.target.value)
                            }
                            placeholder="г. Худжанд, ул. Камола Худжанди, 120"
                        />
                    </Field>
                    <Field label="Телефон">
                        <Input
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder="+992 (3422) 6-44-71"
                        />
                    </Field>
                    <Field label="Дежурная часть">
                        <Input
                            value={data.duty_phone}
                            onChange={(e) =>
                                setData('duty_phone', e.target.value)
                            }
                            placeholder="+992 (3422) 6-25-11"
                        />
                    </Field>
                    <Field label="E-mail" error={fieldError('email')}>
                        <Input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="sughd@khf.tj"
                        />
                    </Field>
                </Blueprint>
            </div>

            <Blueprint style={{ padding: 20, marginTop: 16 }}>
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        marginBottom: 4,
                    }}
                >
                    <h3 className="ui-card-title" style={{ margin: 0 }}>
                        Районы в справочнике
                    </h3>
                    <Button
                        variant="ghost"
                        size="sm"
                        icon={<Plus size={14} strokeWidth={2} />}
                        onClick={addDistrict}
                    >
                        Район
                    </Button>
                </div>
                <p
                    style={{
                        margin: '0 0 12px',
                        fontSize: 12,
                        color: 'var(--color-neutral-500)',
                    }}
                >
                    Названия районов для выбора зоны в предупреждениях (
                    {lang.toUpperCase()}).
                </p>

                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 8,
                    }}
                >
                    {data.districts.map((row, i) => (
                        <div
                            key={row.id ?? `new-${i}`}
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '26px 1fr 28px',
                                gap: 6,
                                alignItems: 'center',
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                }}
                            >
                                <button
                                    type="button"
                                    className="ui-btn ui-btn-icon ui-btn-ghost"
                                    aria-label="Выше"
                                    disabled={i === 0}
                                    onClick={() => moveDistrict(i, -1)}
                                    style={{ minHeight: 18, padding: 1 }}
                                >
                                    <ArrowUp size={13} strokeWidth={1.75} />
                                </button>
                                <button
                                    type="button"
                                    className="ui-btn ui-btn-icon ui-btn-ghost"
                                    aria-label="Ниже"
                                    disabled={i === data.districts.length - 1}
                                    onClick={() => moveDistrict(i, 1)}
                                    style={{ minHeight: 18, padding: 1 }}
                                >
                                    <ArrowDown size={13} strokeWidth={1.75} />
                                </button>
                            </div>
                            <Input
                                value={row.name[lang]}
                                onChange={(e) =>
                                    updateDistrict(i, e.target.value)
                                }
                                placeholder="Название района"
                                style={{ fontSize: 13 }}
                            />
                            <IconButton
                                label="Удалить"
                                variant="ghost"
                                onClick={() => removeDistrict(i)}
                            >
                                <X size={14} strokeWidth={1.5} />
                            </IconButton>
                        </div>
                    ))}
                    {data.districts.length === 0 && (
                        <p
                            style={{
                                margin: 0,
                                fontSize: 12.5,
                                color: 'var(--color-neutral-500)',
                            }}
                        >
                            Районы ещё не добавлены.
                        </p>
                    )}
                </div>
            </Blueprint>

            <div className="news-form-actions">
                <div style={{ flex: 1 }} />
                <Button
                    variant="primary"
                    icon={<Save size={15} strokeWidth={1.75} />}
                    loading={processing}
                    onClick={submit}
                >
                    Сохранить регион
                </Button>
            </div>
        </>
    );
}
