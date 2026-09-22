import { mergeAttributes, Node } from '@tiptap/core';
import { ReactNodeViewRenderer } from '@tiptap/react';
import { RichCalloutView } from './RichCalloutView';
import type { CalloutType } from './RichCalloutView';

export type { CalloutType };

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        callout: {
            setCallout: (attributes?: { type?: CalloutType }) => ReturnType;
            toggleCallout: (attributes?: { type?: CalloutType }) => ReturnType;
        };
    }
}

export const RichCallout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            type: {
                default: 'warning',
                parseHTML: (element) =>
                    element.getAttribute('data-callout-type') || 'warning',
                renderHTML: (attributes) => ({
                    'data-callout-type': attributes.type,
                    class: `re-callout re-callout-${attributes.type}`,
                }),
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'aside.re-callout',
                getAttrs: (element) => {
                    const el = element as HTMLElement;
                    return {
                        type: el.getAttribute('data-callout-type') || 'warning',
                    };
                },
            },
        ];
    },

    renderHTML({ HTMLAttributes }) {
        return ['aside', mergeAttributes(HTMLAttributes), 0];
    },

    addNodeView() {
        return ReactNodeViewRenderer(RichCalloutView);
    },

    addCommands() {
        return {
            setCallout:
                (attributes = { type: 'warning' }) =>
                ({ commands }) => {
                    return commands.wrapIn(this.name, attributes);
                },
            toggleCallout:
                (attributes = { type: 'warning' }) =>
                ({ commands }) => {
                    return commands.toggleWrap(this.name, attributes);
                },
        };
    },
});

