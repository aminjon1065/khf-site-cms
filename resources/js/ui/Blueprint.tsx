import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface BlueprintProps extends HTMLAttributes<HTMLDivElement> {
    /** Draw the four registration "+" corner marks (default true). */
    corners?: boolean;
    children?: ReactNode;
}

/**
 * The wireframe frame every card, panel, dropdown and modal wears:
 * a hairline border with four "+" registration marks at the corners.
 */
export function Blueprint({
    corners = true,
    className,
    children,
    ...props
}: BlueprintProps) {
    return (
        <div className={cn('ui-blueprint', className)} {...props}>
            {children}
            {corners && (
                <>
                    <i className="ui-corner tl" aria-hidden />
                    <i className="ui-corner tr" aria-hidden />
                    <i className="ui-corner bl" aria-hidden />
                    <i className="ui-corner br" aria-hidden />
                </>
            )}
        </div>
    );
}
