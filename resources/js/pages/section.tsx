import { Head } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import { Blueprint } from '@/ui/Blueprint';
import { LinkButton } from '@/ui/Button';
import { PageHeader } from '@/ui/PageHeader';

export default function Section({
    title,
}: {
    sectionKey: string;
    title: string;
}) {
    return (
        <>
            <Head title={title} />
            <PageHeader
                title={title}
                subtitle="Раздел входит в структуру CMS · экран строится по тем же паттернам: таблица, фильтры, боковая панель"
            />
            <Blueprint
                style={{
                    display: 'grid',
                    placeItems: 'center',
                    minHeight: 320,
                    textAlign: 'center',
                    padding: 32,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        gap: 12,
                        maxWidth: 440,
                    }}
                >
                    <LayoutGrid
                        size={34}
                        strokeWidth={1.25}
                        style={{ color: 'var(--color-neutral-400)' }}
                    />
                    <div
                        style={{
                            fontFamily: 'var(--font-heading)',
                            fontWeight: 600,
                            fontSize: 18,
                        }}
                    >
                        Типовой список раздела «{title}»
                    </div>
                    <p
                        style={{
                            fontSize: 13.5,
                            color: 'var(--color-neutral-600)',
                            margin: 0,
                        }}
                    >
                        Использует компоненты DataTable, FilterBar и SavedViews
                        из дизайн-системы — см. разделы «Предупреждения» и
                        «Дашборд».
                    </p>
                    <LinkButton href="/alerts" variant="secondary">
                        Открыть образец таблицы
                    </LinkButton>
                </div>
            </Blueprint>
        </>
    );
}
