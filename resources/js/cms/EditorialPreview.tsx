import {
    CheckCircle2,
    Languages,
    Monitor,
    Smartphone,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import type { ContentLocale } from '@/lib/domain';
import {
    missingVersionNotice,
    previewLocale,
} from '@/lib/publication-languages';
import type { TitleWord } from '@/lib/publication-languages';
import { Button } from '@/ui/Button';
import { EmptyState } from '@/ui/Feedback';
import { Modal } from '@/ui/Overlay';

export interface PublicationCheck {
    label: string;
    ok: boolean;
    blocking?: boolean;
    detail?: string | null;
}

interface LocalePreview {
    title: string;
    summary?: string;
    body?: string;
    seoTitle?: string;
    seoDescription?: string;
}

export interface EditorialPreviewConfig {
    locales: Record<ContentLocale, LocalePreview>;
    /** How the notice names a missing title; documents and instructions have a name. */
    titleWord?: TitleWord;
    imageUrl?: string | null;
    imageAlt?: string | null;
    signedUrl?: string | null;
    checklist: PublicationCheck[];
}

export function EditorialPreview({
    open,
    onClose,
    preview,
    initialLocale,
}: {
    open: boolean;
    onClose: () => void;
    preview: EditorialPreviewConfig;
    initialLocale: ContentLocale;
}) {
    // The modal stays mounted with the form, so the language is derived on
    // every open from the tab being edited; a language picked inside the modal
    // only lasts until it is closed.
    const [chosenLocale, setChosenLocale] = useState<ContentLocale | null>(
        null,
    );
    const [mode, setMode] = useState<'desktop' | 'mobile' | 'og'>('desktop');
    const locale =
        chosenLocale ?? previewLocale(preview.locales, initialLocale);
    const selected = preview.locales[locale];
    const available = selected.title.trim() !== '';

    const close = () => {
        setChosenLocale(null);
        onClose();
    };

    return (
        <Modal
            open={open}
            onClose={close}
            width={1040}
            title="Предпросмотр публикации"
        >
            <div className="editorial-preview-toolbar">
                <div role="group" aria-label="Язык предпросмотра">
                    {(['tg', 'ru', 'en'] as ContentLocale[]).map((item) => (
                        <Button
                            key={item}
                            size="sm"
                            variant={locale === item ? 'primary' : 'ghost'}
                            onClick={() => setChosenLocale(item)}
                        >
                            {item.toUpperCase()}
                        </Button>
                    ))}
                </div>
                <div role="group" aria-label="Размер предпросмотра">
                    <Button
                        size="sm"
                        variant={mode === 'desktop' ? 'primary' : 'ghost'}
                        icon={<Monitor size={14} />}
                        onClick={() => setMode('desktop')}
                    >
                        Desktop
                    </Button>
                    <Button
                        size="sm"
                        variant={mode === 'mobile' ? 'primary' : 'ghost'}
                        icon={<Smartphone size={14} />}
                        onClick={() => setMode('mobile')}
                    >
                        Mobile
                    </Button>
                    <Button
                        size="sm"
                        variant={mode === 'og' ? 'primary' : 'ghost'}
                        onClick={() => setMode('og')}
                    >
                        Share / OG
                    </Button>
                </div>
                {preview.signedUrl && (
                    <a
                        href={preview.signedUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="editorial-preview-signed"
                    >
                        Открыть приватную ссылку
                    </a>
                )}
            </div>

            {!available && (
                <div className="editorial-preview-fallback" role="status">
                    {missingVersionNotice(locale, preview.titleWord)}
                </div>
            )}

            <div className="editorial-preview-layout">
                <div
                    className={`editorial-preview-canvas is-${mode}`}
                    aria-label={`${mode} preview`}
                >
                    {!available ? (
                        <EmptyState
                            icon={<Languages size={28} strokeWidth={1.25} />}
                            title="Этой языковой версии нет"
                            hint="Выберите язык, на котором материал заполнен."
                        />
                    ) : mode === 'og' ? (
                        <div className="editorial-og-card">
                            {preview.imageUrl && (
                                <img
                                    src={preview.imageUrl}
                                    alt={preview.imageAlt ?? ''}
                                />
                            )}
                            <div>
                                <span>khf.tj</span>
                                <strong>
                                    {selected.seoTitle || selected.title}
                                </strong>
                                <p>
                                    {selected.seoDescription ||
                                        selected.summary ||
                                        'Описание будет сформировано из текста публикации.'}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <article>
                            <span className="editorial-preview-kicker">
                                КЧС Республики Таджикистан
                            </span>
                            <h1>{selected.title}</h1>
                            {selected.summary && <p>{selected.summary}</p>}
                            {preview.imageUrl && (
                                <img
                                    src={preview.imageUrl}
                                    alt={preview.imageAlt ?? ''}
                                />
                            )}
                            <div
                                className="editorial-preview-body"
                                dangerouslySetInnerHTML={{
                                    __html: sanitizePreviewHtml(
                                        selected.body ?? '',
                                    ),
                                }}
                            />
                        </article>
                    )}
                </div>

                <aside className="editorial-preview-checklist">
                    <strong>Проверка перед публикацией</strong>
                    <ul>
                        {preview.checklist.map((check) => (
                            <li key={check.label}>
                                {check.ok ? (
                                    <CheckCircle2
                                        size={16}
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <TriangleAlert
                                        size={16}
                                        aria-hidden="true"
                                    />
                                )}
                                <span>
                                    {check.label}
                                    {check.detail && (
                                        <small>{check.detail}</small>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </aside>
            </div>
        </Modal>
    );
}

function sanitizePreviewHtml(html: string): string {
    if (typeof window === 'undefined') {
        return '';
    }

    const document = new DOMParser().parseFromString(html, 'text/html');
    document
        .querySelectorAll('script,style,iframe,object,embed')
        .forEach((element) => element.remove());
    document.querySelectorAll('*').forEach((element) => {
        for (const attribute of Array.from(element.attributes)) {
            if (
                attribute.name.startsWith('on') ||
                attribute.value.toLowerCase().startsWith('javascript:')
            ) {
                element.removeAttribute(attribute.name);
            }
        }
    });

    return document.body.innerHTML;
}
