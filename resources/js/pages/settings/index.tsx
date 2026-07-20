import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useCan } from '@/lib/auth';
import { Blueprint } from '@/ui/Blueprint';
import { Button } from '@/ui/Button';
import { Field, Input, Textarea } from '@/ui/Field';
import { PageHeader } from '@/ui/PageHeader';

interface FieldDef {
    key: string;
    label: string;
    type: string;
    value: string;
}

interface Section {
    group: string;
    label: string;
    fields: FieldDef[];
}

interface Props {
    sections: Section[];
}

export default function SettingsIndex({ sections }: Props) {
    const can = useCan();
    const editable = can('settings.edit');

    const initial: Record<string, Record<string, string>> = {};
    sections.forEach((s) => {
        initial[s.group] = {};
        s.fields.forEach((f) => {
            initial[s.group][f.key] = f.value;
        });
    });

    const form = useForm({ settings: initial });
    const { data, setData, processing } = form;

    const update = (group: string, key: string, value: string) =>
        setData('settings', {
            ...data.settings,
            [group]: { ...data.settings[group], [key]: value },
        });

    const save = () => form.put('/settings', { preserveScroll: true });

    return (
        <>
            <Head title="Настройки сайта" />
            <PageHeader
                title="Настройки сайта"
                subtitle="Реквизиты организации, контакты, соцсети и SEO · используются в шапке и подвале публичного сайта"
                actions={
                    editable && (
                        <Button
                            variant="primary"
                            icon={<Save size={16} strokeWidth={1.75} />}
                            loading={processing}
                            onClick={save}
                        >
                            Сохранить
                        </Button>
                    )
                }
            />

            <div
                className="cms-two-col"
                style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: 16,
                    alignItems: 'start',
                }}
            >
                {sections.map((section) => (
                    <Blueprint key={section.group} style={{ padding: 20 }}>
                        <h3
                            className="ui-card-title"
                            style={{ marginTop: 0, marginBottom: 14 }}
                        >
                            {section.label}
                        </h3>
                        {section.fields.map((field) => (
                            <Field key={field.key} label={field.label}>
                                {field.type === 'textarea' ? (
                                    <Textarea
                                        value={
                                            data.settings[section.group]?.[
                                                field.key
                                            ] ?? ''
                                        }
                                        onChange={(e) =>
                                            update(
                                                section.group,
                                                field.key,
                                                e.target.value,
                                            )
                                        }
                                        disabled={!editable}
                                        style={{ minHeight: 70 }}
                                        maxLength={5000}
                                    />
                                ) : (
                                    <Input
                                        value={
                                            data.settings[section.group]?.[
                                                field.key
                                            ] ?? ''
                                        }
                                        onChange={(e) =>
                                            update(
                                                section.group,
                                                field.key,
                                                e.target.value,
                                            )
                                        }
                                        disabled={!editable}
                                        maxLength={255}
                                    />
                                )}
                            </Field>
                        ))}
                    </Blueprint>
                ))}
            </div>

            {editable && (
                <div className="news-form-actions">
                    <div style={{ flex: 1 }} />
                    <Button
                        variant="primary"
                        icon={<Save size={15} strokeWidth={1.75} />}
                        loading={processing}
                        onClick={save}
                    >
                        Сохранить настройки
                    </Button>
                </div>
            )}
        </>
    );
}
