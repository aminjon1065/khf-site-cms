import { Head } from '@inertiajs/react';
import { CheckCircle2, TriangleAlert } from 'lucide-react';

interface PreviewData {
    locale: string;
    title: string;
    body: string;
    image: string | null;
    fallback: boolean;
    checklist: {
        key: string;
        label: string;
        ok: boolean;
        blocking: boolean;
        detail: string | null;
    }[];
}

export default function SignedEditorialPreview({
    preview,
}: {
    preview: PreviewData;
}) {
    return (
        <main className="signed-editorial-preview">
            <Head title={`Предпросмотр: ${preview.title}`} />
            <header>
                <span>Приватный предпросмотр · {preview.locale}</span>
                <h1>{preview.title}</h1>
                {preview.fallback && (
                    <p role="status">
                        Часть полей показана из русской fallback-версии.
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
