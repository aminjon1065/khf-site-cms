import type { ReactNode } from 'react';

export function PageHeader({
    title,
    subtitle,
    eyebrow,
    actions,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    eyebrow?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <div className="ui-page-head">
            <div style={{ minWidth: 0 }}>
                {eyebrow && (
                    <div className="ui-kicker" style={{ marginBottom: 4 }}>
                        {eyebrow}
                    </div>
                )}
                <h1 className="ui-page-title">{title}</h1>
                {subtitle && <div className="ui-page-sub">{subtitle}</div>}
            </div>
            {actions && (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {actions}
                </div>
            )}
        </div>
    );
}
