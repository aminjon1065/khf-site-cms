import { CheckCircle2, Monitor, Smartphone, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import type { ContentLocale } from '@/lib/domain';
import { Button } from '@/ui/Button';
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
    const [locale, setLocale] = useState(initialLocale);
    const [mode, setMode] = useState<'desktop' | 'mobile' | 'og'>('desktop');
    const selected = preview.locales[locale];
    const fallback = selected.title.trim() === '';
    const visible = fallback ? preview.locales.ru : selected;

    return (
        <Modal
            open={open}
            onClose={onClose}
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
                            onClick={() => setLocale(item)}
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

            {fallback && (
                <div className="editorial-preview-fallback" role="status">
                    Для {locale.toUpperCase()} нет заголовка — показана русская
                    fallback-версия.
                </div>
            )}

            <div className="editorial-preview-layout">
                <div
                    className={`editorial-preview-canvas is-${mode}`}
                    aria-label={`${mode} preview`}
                >
                    {mode === 'og' ? (
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
                                    {visible.seoTitle || visible.title}
                                </strong>
                                <p>
                                    {visible.seoDescription ||
                                        visible.summary ||
                                        'Описание будет сформировано из текста публикации.'}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <article>
                            <span className="editorial-preview-kicker">
                                КЧС Республики Таджикистан
                            </span>
                            <h1>{visible.title || 'Заголовок публикации'}</h1>
                            {visible.summary && <p>{visible.summary}</p>}
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
                                        visible.body ?? '',
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
