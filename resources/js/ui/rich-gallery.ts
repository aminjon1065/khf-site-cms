import { Node, mergeAttributes } from '@tiptap/core';

/**
 * Обработчик клика по чипу «Фотогалерея» (открыть медиатеку). Живёт вне опций
 * расширения: @tiptap/react сравнивает опции useEditor по ссылке на каждом
 * рендере, и колбэк в configure() каждый раз «новый» → setOptions →
 * ре-рендер → React #185 (краш редактора по клику в текст). Форма с
 * редактором ставит обработчик через registerGalleryPickerHandler в эффекте.
 */
let openPickerHandler: (() => void) | null = null;

export function registerGalleryPickerHandler(
    handler: (() => void) | null,
): void {
    openPickerHandler = handler;
}

/**
 * Маркер фотогалереи в теле материала: атомарный блочный узел, который
 * редактор ставит в произвольное место текста. Кадры берутся из коллекции
 * `gallery` материала — маркер только запоминает ГДЕ показать карусель.
 *
 * Сериализуется как `<figure class="cms-gallery">…</figure>`: этот тег
 * разрешён профилем санитайзера 'news' (figure[class]) и не пуст внутри,
 * поэтому AutoFormat.RemoveEmpty его не вырезает. Публичная часть ищет
 * маркер этим классом и подставляет карусель (см. фронт, splitBodyByGallery).
 *
 * Чип в редакторе кликабелен: открывает медиатеку для выбора кадров.
 * Перетаскивание за любое место блока, удаление — Backspace.
 */
export const RichGallery = Node.create({
    name: 'gallery',
    group: 'block',
    atom: true,
    draggable: true,

    parseHTML() {
        return [{ tag: 'figure[class~="cms-gallery"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'figure',
            mergeAttributes(HTMLAttributes, { class: 'cms-gallery' }),
            ['span', { class: 'cms-gallery-chip' }, 'Фотогалерея'],
        ];
    },

    addNodeView() {
        return () => {
            const dom = document.createElement('figure');
            dom.className = 'cms-gallery';
            dom.setAttribute('data-drag-handle', 'true');

            const chip = document.createElement('span');
            chip.className = 'cms-gallery-chip';
            chip.textContent = 'Фотогалерея';
            chip.setAttribute('role', 'button');
            chip.setAttribute('tabindex', '0');

            const hint = document.createElement('span');
            hint.className = 'cms-gallery-hint';
            hint.textContent =
                'Кадры — клик по чипу или блок «Фотогалерея» под текстом. Перетащите, чтобы переместить; Backspace — удалить.';

            const open = () => openPickerHandler?.();

            chip.addEventListener('click', (event) => {
                event.stopPropagation();
                open();
            });
            chip.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    event.stopPropagation();
                    open();
                }
            });

            dom.append(chip, hint);

            return { dom };
        };
    },

    addCommands() {
        return {
            insertGalleryMarker:
                () =>
                ({ chain }) =>
                    chain().focus().insertContent({ type: this.name }).run(),
        };
    },
});

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        gallery: {
            insertGalleryMarker: () => ReturnType;
        };
    }
}
