import type { DiffSegment } from '@/lib/text-diff';
import { Blueprint } from '@/ui/Blueprint';

/**
 * Line-by-line comparison: removed lines on red, added on green, unchanged
 * context muted. Shared by the revision history and the approval center.
 */
export function DiffSegments({ segments }: { segments: DiffSegment[] }) {
    return (
        <Blueprint style={{ padding: '8px 0', fontSize: 12.5 }}>
            {segments.map((segment, i) => (
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
                                : segment.type === 'removed'
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
    );
}
