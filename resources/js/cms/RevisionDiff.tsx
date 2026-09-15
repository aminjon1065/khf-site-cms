import { show as showRevision } from '@/actions/App/Http/Controllers/Cms/EditorialAutosaveController';
import { diffLines, fieldChanged, normalizeField } from '@/lib/text-diff';
import { Blueprint } from '@/ui/Blueprint';
import { Modal } from '@/ui/Overlay';
import { useEffect, useState } from 'react';

interface Props {
    revisionId: number;
    onClose: () => void;
}

type RevisionData = Record<string, unknown>;

const FIELD_LABELS: Record<string, string> = {
    title: 'Заголовок',
    name: 'Название',
    summary: 'Краткое описание',
    body: 'Текст',
    slug: 'Адрес (slug)',
    seo: 'SEO',
    cover_alt: 'Alt-текст обложки',
    cover_caption: 'Подпись под фото',
    is_pinned: 'Закрепление',
    show_on_home: 'Показ на главной',
    scheduled_at: 'Запланированная публикация',
    category_id: 'Категория',
    tags: 'Теги',
    goals: 'Цели',
    timeline: 'Ход реализации',
    lifecycle_status: 'Статус проекта',
};

/**
 * Служебные поля состояния формы: они меняются при каждом сохранении,
 * но не являются содержимым материала — в сравнении только шум.
 */
const IGNORED_FIELDS = new Set([
    'action',
    'publish_mode',
    'stay',
    'cover',
    'cover_media_id',
    'cover_remove',
    'attachments',
    'attachments_remove',
    '_method',
    '_editorial_version',
    'draft_key',
]);

const LOCALES = ['ru', 'tg', 'en'];

/**
 * Сравнение сохранённой ревизии с текущим состоянием материала:
 * по полям и локаалям, построчный diff с подсветкой удаления/вставки.
 * HTML-поля сравниваются как текст без разметки — diff тегов нечитаем.
 */
export function RevisionDiff({ revisionId, onClose }: Props) {
    const [state, setState] = useState<'loading' | 'ready' | 'error'>(
        'loading',
    );
    const [revisionData, setRevisionData] = useState<RevisionData>({});
    const [currentData, setCurrentData] = useState<RevisionData>({});
    const [meta, setMeta] = useState<{ by: string; at: string } | null>(null);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            try {
                const response = await fetch(showRevision.url(revisionId), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const result = (await response.json()) as {
                    revision_data?: RevisionData;
                    current_data?: RevisionData;
                    revision?: { saved_by: string | null; saved_at: string };
                };

                if (cancelled) {
                    return;
                }

                if (!response.ok) {
                    setState('error');
                } else {
                    setRevisionData(result.revision_data ?? {});
                    setCurrentData(result.current_data ?? {});
                    setMeta({
                        by: result.revision?.saved_by ?? '—',
                        at: result.revision?.saved_at ?? '',
                    });
                    setState('ready');
                }
            } catch {
                if (!cancelled) {
                    setState('error');
                }
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
    }, [revisionId]);

    const sections =
        state === 'ready' ? buildSections(revisionData, currentData) : [];

    return (
        <Modal open onClose={onClose} title="Сравнение версии" width={760}>
            {state === 'loading' && <p>Загрузка версии…</p>}
            {state === 'error' && (
                <p style={{ color: 'var(--danger)' }}>
                    Не удалось загрузить версию.
                </p>
            )}
            {state === 'ready' && (
                <>
                    <p
                        style={{
                            margin: '0 0 14px',
                            fontSize: 12.5,
                            color: 'var(--color-neutral-600)',
                        }}
                    >
                        Сохранено{' '}
                        {meta?.at
                            ? new Date(meta.at).toLocaleString('ru-RU')
                            : ''}
                        {meta?.by && meta.by !== '—' ? ` · ${meta.by}` : ''} →
                        сравнивается с текущим состоянием материала.
                    </p>
                    {sections.length === 0 ? (
                        <p>Отличий от текущего состояния нет.</p>
                    ) : (
                        sections.map((section) => (
                            <section
                                key={section.key}
                                style={{ marginBottom: 16 }}
                            >
                                <h4
                                    className="ui-card-title"
                                    style={{ margin: '0 0 6px' }}
                                >
                                    {section.label}
                                </h4>
                                <Blueprint
                                    style={{ padding: '8px 0', fontSize: 12.5 }}
                                >
                                    {section.segments.map((segment, i) => (
                                        <pre
                                            key={i}
                                            className="rev-diff-line"
                                            data-diff={segment.type}
                                            style={{
                                                margin: 0,
                                                padding: '1px 12px',
                                                whiteSpace: 'pre-wrap',
                                                wordBreak: 'break-word',
                                                fontFamily: 'inherit',
                                                color:
                                                    segment.type === 'context'
                                                        ? 'var(--color-neutral-600)'
                                                        : 'var(--color-text)',
                                                background:
                                                    segment.type === 'added'
                                                        ? 'color-mix(in srgb, var(--ok) 12%, transparent)'
                                                        : segment.type ===
                                                            'removed'
                                                          ? 'color-mix(in srgb, var(--danger) 12%, transparent)'
                                                          : 'transparent',
                                            }}
                                        >
                                            {segment.type === 'added'
                                                ? '+ '
                                                : segment.type === 'removed'
                                                  ? '− '
                                                  : '  '}
                                            {segment.lines.join('\n')}
                                        </pre>
                                    ))}
                                </Blueprint>
                            </section>
                        ))
                    )}
                </>
            )}
        </Modal>
    );
}

interface FieldSection {
    key: string;
    label: string;
    segments: ReturnType<typeof diffLines>;
}

function buildSections(
    revision: RevisionData,
    current: RevisionData,
): FieldSection[] {
    const keys = new Set([...Object.keys(revision), ...Object.keys(current)]);
    const sections: FieldSection[] = [];

    const addField = (
        key: string,
        label: string,
        before: string,
        after: string,
    ) => {
        if (!fieldChanged(before, after)) {
            return;
        }

        sections.push({
            key,
            label,
            segments: diffLines(before, after),
        });
    };

    for (const key of keys) {
        if (IGNORED_FIELDS.has(key)) {
            continue;
        }

        if (isLocaleMap(revision[key]) || isLocaleMap(current[key])) {
            for (const locale of LOCALES) {
                const before = localeValue(revision[key], locale);
                const after = localeValue(current[key], locale);

                addField(
                    `${key}.${locale}`,
                    `${labelFor(key)} (${locale.toUpperCase()})`,
                    before,
                    after,
                );
            }

            continue;
        }

        addField(
            key,
            labelFor(key),
            normalizeField(revision[key]),
            normalizeField(current[key]),
        );
    }

    return sections.sort((a, b) => a.key.localeCompare(b.key, 'ru'));
}

function labelFor(key: string): string {
    return FIELD_LABELS[key] ?? key;
}

function isLocaleMap(value: unknown): value is Record<string, string> {
    return (
        typeof value === 'object' &&
        value !== null &&
        !Array.isArray(value) &&
        LOCALES.some((locale) => locale in (value as Record<string, unknown>))
    );
}

function localeValue(value: unknown, locale: string): string {
    if (typeof value !== 'object' || value === null) {
        return '';
    }

    return htmlToText(String((value as Record<string, unknown>)[locale] ?? ''));
}

/** HTML-текст → читаемые строки: теги вырезаются, блочные — переносами. */
function htmlToText(html: string): string {
    if (html === '' || !html.includes('<')) {
        return html;
    }

    const container = document.createElement('div');
    container.innerHTML = html
        .replace(/<\/(p|div|li|h[1-6]|tr|blockquote)>/gi, '\n')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<[^>]+>/g, '');

    return container.textContent ?? '';
}
