import { lazy, Suspense } from 'react';
import type { Editor } from './RichEditorField';
import type { Props } from './RichEditorField';

const LazyRichEditorField = lazy(() =>
    import('./RichEditorField').then((m) => ({ default: m.RichEditorField })),
);

/**
 * `RichEditorField`'s Tiptap bundle is ~500 KB — the single largest chunk
 * in the build (D-6, CMS_AUDIT.md P2) — because it used to be a static
 * import in every one of its 4 consumers, so all 4 edit forms paid for it
 * on first load whether or not anyone ever touched the editor. Loading it
 * on demand here means only forms that actually render a RichEditor (and
 * only once they do) fetch that chunk; callers are unaffected — same
 * import path, same props, same instant-looking editor once Tiptap
 * itself finishes initializing (see RichEditorField's own `!editor`
 * loading state for that).
 */
export function RichEditor(props: Props) {
    return (
        <Suspense
            fallback={
                <div className="re-shell re-loading">Загрузка редактора…</div>
            }
        >
            <LazyRichEditorField {...props} />
        </Suspense>
    );
}

export type { Editor };
