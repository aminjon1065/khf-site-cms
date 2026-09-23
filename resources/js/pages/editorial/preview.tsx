import { Head } from '@inertiajs/react';
import { CheckCircle2, TriangleAlert } from 'lucide-react';
import type { ContentLocale } from '@/lib/domain';
import { missingVersionNotice } from '@/lib/publication-languages';
import type {
    MissingPart,
    TextWord,
    TitleWord,
} from '@/lib/publication-languages';

interface PreviewData {
    locale: ContentLocale;
    title: string;
    body: string;
    image: string | null;
    available: boolean;
    /** What keeps this language version off the site, when it is off. */
    missing: MissingPart | null;
    title_word: TitleWord;
    text_word: TextWord;
    checklist: {
        key: string;
        label: string;
        ok: boolean;
        blocking: boolean;
        detail: string | null;
    }[];
}

const LOCALE_VERSIONS: Record<ContentLocale, string> = {
    tg: 'таджикская версия',
    ru: 'русская версия',
    en: 'английская версия',
};

export default function SignedEditorialPreview({
    preview,
}: {
    preview: PreviewData;
}) {
    return (
        <main className="signed-editorial-preview">
            <Head
                title={
                    preview.available
                        ? `Предпросмотр: ${preview.title}`
                        : 'Предпросмотр'
                }
            />
            <header>
                <span>
                    Закрытый предпросмотр · {LOCALE_VERSIONS[preview.locale]}
                </span>
                {preview.available ? (
                    <h1>{preview.title}</h1>
                ) : (
                    <p role="status">
                        {missingVersionNotice(
                            preview.locale,
                            preview.title_word,
                            preview.missing ?? 'title',
                            preview.text_word,
                        )}
                    </p>
                )}
            </header>
            {preview.image && <img src={preview.image} alt="" />}
            <div
                className="signed-editorial-preview-body"
                dangerouslySetInnerHTML={{ __html: preview.body }}
            />
            <aside aria-label="Проверка перед публикацией">
                <h2>Проверка перед публикацией</h2>
                <ul>
                    {preview.checklist.map((item) => (
                        <li key={item.key}>
                            {item.ok ? (
                                <CheckCircle2 size={16} />
                            ) : (
                                <TriangleAlert size={16} />
                            )}
                            {item.label}
                            {item.detail ? ` — ${item.detail}` : ''}
                        </li>
                    ))}
                </ul>
            </aside>
        </main>
    );
}
