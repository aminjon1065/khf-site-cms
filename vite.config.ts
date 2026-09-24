import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        /**
         * Самый большой чанк — `RichEditorField` (~570 kB): prosemirror +
         * tiptap core/starter-kit/расширения. Он уже async-чанк за `lazy()`
         * в `ui/RichEditor.tsx`, поэтому вне критического пути: его платят
         * только формы, которые реально рендерят редактор. Дробить дальше
         * нечего — весь этот код нужен разом при монтировании редактора,
         * так что два чанка грузились бы всегда вместе. Порог поднят, чтобы
         * warning не заглушал реальные регрессии размера в других чанках.
         */
        chunkSizeWarningLimit: 600,
        rolldownOptions: {
            output: {
                codeSplitting: {
                    groups: [
                        {
                            /**
                             * Рантайм, без которого не открывается ни одна
                             * страница (React, Inertia и их зависимости), —
                             * отдельным чанком: он меняется только при
                             * обновлении пакетов, поэтому после релиза
                             * браузер берёт его из кеша. Без явной группы
                             * Rolldown то выносит его, то вклеивает в entry
                             * — в зависимости от формы графа страниц.
                             */
                            name: 'framework',
                            test: /node_modules[\\/](react|react-dom|scheduler|@inertiajs[\\/][\w-]+)[\\/]/,
                        },
                    ],
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
