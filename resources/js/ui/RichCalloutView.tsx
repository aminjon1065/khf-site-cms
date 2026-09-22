import type { ReactNodeViewProps } from '@tiptap/react';
import { NodeViewContent, NodeViewWrapper } from '@tiptap/react';
import { AlertTriangle, Info, Quote, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export type CalloutType = 'warning' | 'info' | 'quote';

const TYPES: {
    value: CalloutType;
    label: string;
    icon: typeof AlertTriangle;
}[] = [
    { value: 'warning', label: 'Предупреждение КЧС', icon: AlertTriangle },
    { value: 'info', label: 'Важная информация', icon: Info },
    { value: 'quote', label: 'Официальная цитата', icon: Quote },
];

export function RichCalloutView({
    node,
    selected,
    updateAttributes,
    deleteNode,
}: ReactNodeViewProps) {
    const type = (node.attrs.type as CalloutType) || 'warning';
    const activeConfig = TYPES.find((t) => t.value === type) ?? TYPES[0];
    const Icon = activeConfig.icon;

    return (
        <NodeViewWrapper
            as="aside"
            className={cn(
                're-callout',
                `re-callout-${type}`,
                selected && 'is-selected',
            )}
            data-callout-type={type}
        >
            <div className="re-callout-header" contentEditable={false}>
                <div className="re-callout-type-pill">
                    <Icon
                        size={16}
                        strokeWidth={2}
                        className="re-callout-icon"
                    />
                    <select
                        value={type}
                        onChange={(e) =>
                            updateAttributes({
                                type: e.target.value as CalloutType,
                            })
                        }
                        className="re-callout-select"
                        aria-label="Тип врезки"
                    >
                        {TYPES.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                </div>
                <button
                    type="button"
                    onClick={deleteNode}
                    className="re-callout-del-btn"
                    title="Удалить врезку"
                    aria-label="Удалить врезку"
                >
                    <Trash2 size={14} strokeWidth={1.75} />
                </button>
            </div>
            <NodeViewContent className="re-callout-content" />
        </NodeViewWrapper>
    );
}
