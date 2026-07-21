import { Head } from '@inertiajs/react';
import { Building2, Mail, MapPin, Phone, Siren } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCan } from '@/lib/auth';
import { Blueprint } from '@/ui/Blueprint';
import { LinkButton } from '@/ui/Button';
import { PageHeader } from '@/ui/PageHeader';

interface ContactSet {
    emergency_number: string;
    trust_phone: string | null;
    duty_phone: string | null;
    press_phone: string | null;
    press_email: string | null;
    address: string | null;
    email: string | null;
}

interface RegionContact {
    id: number;
    name: string;
    center: string | null;
    phone: string | null;
    duty_phone: string | null;
    email: string | null;
    address: string | null;
}

interface Props {
    central: ContactSet;
    services: { num: string; label: string }[];
    regions: RegionContact[];
}

export default function EmergencyContacts({
    central,
    services,
    regions,
}: Props) {
    const can = useCan();

    return (
        <>
            <Head title="Экстренные контакты" />
            <PageHeader
                title="Экстренные контакты"
                subtitle="Центральные службы и контакты региональных управлений КЧС"
                actions={
                    <div className="flex gap-2">
                        {can('settings.edit') && (
                            <LinkButton href="/settings" size="sm">
                                Настройки
                            </LinkButton>
                        )}
                        {can('regions.edit') && (
                            <LinkButton href="/regions" size="sm">
                                Регионы
                            </LinkButton>
                        )}
                    </div>
                }
            />

            <div className="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {services.map((service) => (
                    <Blueprint
                        key={`${service.num}-${service.label}`}
                        className="flex items-center gap-3 p-4"
                    >
                        <Siren
                            size={22}
                            strokeWidth={1.4}
                            className="text-(--danger)"
                        />
                        <span>
                            <a
                                href={`tel:${service.num}`}
                                className="block font-mono text-2xl font-semibold"
                            >
                                {service.num}
                            </a>
                            <span className="text-xs text-(--color-neutral-600)">
                                {service.label}
                            </span>
                        </span>
                    </Blueprint>
                ))}
            </div>

            <Blueprint className="mb-5 p-5">
                <h2 className="ui-card-title mt-0 mb-4">Центральный аппарат</h2>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Contact
                        icon={Phone}
                        label="Единый номер"
                        value={central.emergency_number}
                        href={`tel:${central.emergency_number}`}
                    />
                    <Contact
                        icon={Phone}
                        label="Дежурная часть"
                        value={central.duty_phone}
                        href={
                            central.duty_phone
                                ? `tel:${central.duty_phone}`
                                : undefined
                        }
                    />
                    <Contact
                        icon={Phone}
                        label="Телефон доверия"
                        value={central.trust_phone}
                        href={
                            central.trust_phone
                                ? `tel:${central.trust_phone}`
                                : undefined
                        }
                    />
                    <Contact
                        icon={Mail}
                        label="E-mail"
                        value={central.email}
                        href={
                            central.email
                                ? `mailto:${central.email}`
                                : undefined
                        }
                    />
                    <Contact
                        icon={Mail}
                        label="Пресс-служба"
                        value={central.press_email}
                        href={
                            central.press_email
                                ? `mailto:${central.press_email}`
                                : undefined
                        }
                    />
                    <Contact
                        icon={MapPin}
                        label="Адрес"
                        value={central.address}
                    />
                </div>
            </Blueprint>

            <div className="grid gap-4 lg:grid-cols-2">
                {regions.map((region) => (
                    <Blueprint key={region.id} className="p-5">
                        <div className="mb-3 flex items-start gap-3">
                            <Building2
                                size={20}
                                strokeWidth={1.4}
                                className="mt-0.5 text-(--color-accent-700)"
                            />
                            <span>
                                <h2 className="m-0 text-base font-semibold">
                                    {region.name}
                                </h2>
                                {region.center && (
                                    <span className="text-xs text-(--color-neutral-600)">
                                        {region.center}
                                    </span>
                                )}
                            </span>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Contact
                                icon={Phone}
                                label="Телефон"
                                value={region.phone}
                                href={
                                    region.phone
                                        ? `tel:${region.phone}`
                                        : undefined
                                }
                            />
                            <Contact
                                icon={Phone}
                                label="Дежурный"
                                value={region.duty_phone}
                                href={
                                    region.duty_phone
                                        ? `tel:${region.duty_phone}`
                                        : undefined
                                }
                            />
                            <Contact
                                icon={Mail}
                                label="E-mail"
                                value={region.email}
                                href={
                                    region.email
                                        ? `mailto:${region.email}`
                                        : undefined
                                }
                            />
                            <Contact
                                icon={MapPin}
                                label="Адрес"
                                value={region.address}
                            />
                        </div>
                    </Blueprint>
                ))}
            </div>
        </>
    );
}

function Contact({
    icon: Icon,
    label,
    value,
    href,
}: {
    icon: LucideIcon;
    label: string;
    value: string | null;
    href?: string;
}) {
    return (
        <span className="flex min-w-0 gap-2">
            <Icon
                size={15}
                strokeWidth={1.5}
                className="mt-0.5 shrink-0 text-(--color-neutral-500)"
            />
            <span className="min-w-0">
                <span className="block text-xs text-(--color-neutral-500)">
                    {label}
                </span>
                {href && value ? (
                    <a
                        href={href}
                        className="text-sm break-words text-(--color-accent-700)"
                    >
                        {value}
                    </a>
                ) : (
                    <span className="text-sm break-words">{value || '—'}</span>
                )}
            </span>
        </span>
    );
}
