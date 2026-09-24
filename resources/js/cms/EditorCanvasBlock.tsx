import type { ReactNode } from 'react';

/**
 * Structured content on the editor canvas, below the text: a project's goals
 * and timeline, a document's files. A heading with a short hint, an action
 * (usually «Добавить») and the rows.
 */
export function EditorCanvasBlock({
    title,
    hint,
    action,
    children,
}: {
    title: string;
    hint?: ReactNode;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="wp-canvas-block">
            <div className="wp-canvas-block-header">
                <div>
                    <h2 className="wp-canvas-block-title">{title}</h2>
                    {hint && <p className="wp-canvas-block-hint">{hint}</p>}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
