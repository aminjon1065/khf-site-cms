import { useId, useState } from 'react';
import { Button } from './Button';
import { Checkbox, Field, Input } from './Field';
import { Modal } from './Overlay';
import { normalizeLinkUrl, parseYoutubeUrl } from './rich-editor';

export interface LinkDialogValue {
    href: string;
    text: string;
    newTab: boolean;
}

export function LinkDialog({
    open,
    initial,
    onClose,
    onApply,
}: {
    open: boolean;
    initial: LinkDialogValue;
    onClose: () => void;
    onApply: (value: LinkDialogValue) => void;
}) {
    const hrefId = useId();
    const textId = useId();
    const [href, setHref] = useState(initial.href);
    const [text, setText] = useState(initial.text);
    const [newTab, setNewTab] = useState(initial.newTab);
    const [error, setError] = useState<string | null>(null);
    const [wasOpen, setWasOpen] = useState(open);

    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setHref(initial.href);
            setText(initial.text);
            setNewTab(initial.newTab);
            setError(null);
        }
    }

    const submit = () => {
        const normalized = normalizeLinkUrl(href);

        if (normalized === null) {
            setError('Введите корректный адрес, например https://khf.tj');

            return;
        }

        if (normalized === '') {
            setError('Укажите адрес ссылки');

            return;
        }

        onApply({ href: normalized, text: text.trim(), newTab });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Ссылка"
            width={440}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Отмена
                    </Button>
                    <Button variant="primary" onClick={submit}>
                        Вставить
                    </Button>
                </>
            }
        >
            <Field label="Адрес ссылки" htmlFor={hrefId} required error={error}>
                <Input
                    id={hrefId}
                    value={href}
                    onChange={(e) => {
                        setHref(e.target.value);
                        setError(null);
                    }}
                    placeholder="https://khf.tj или /alerts"
                    autoFocus
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            submit();
                        }
                    }}
                />
            </Field>
            <Field
                label="Текст"
                htmlFor={textId}
                hint="Если выделен фрагмент, он станет текстом ссылки."
            >
                <Input
                    id={textId}
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    placeholder="Как показать ссылку в тексте"
                />
            </Field>
            <Checkbox
                label="Открывать в новой вкладке"
                checked={newTab}
                onChange={(e) => setNewTab(e.target.checked)}
            />
        </Modal>
    );
}

export function YoutubeDialog({
    open,
    onClose,
    onApply,
}: {
    open: boolean;
    onClose: () => void;
    onApply: (src: string) => void;
}) {
    const urlId = useId();
    const [url, setUrl] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [wasOpen, setWasOpen] = useState(open);

    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setUrl('');
            setError(null);
        }
    }

    const submit = () => {
        const src = parseYoutubeUrl(url);

        if (src === null) {
            setError('Нужна ссылка YouTube: watch, youtu.be или embed');

            return;
        }

        onApply(src);
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Видео YouTube"
            width={440}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Отмена
                    </Button>
                    <Button variant="primary" onClick={submit}>
                        Вставить
                    </Button>
                </>
            }
        >
            <Field
                label="Ссылка на видео"
                htmlFor={urlId}
                required
                error={error}
                hint="Редактор вставит безопасный проигрыватель youtube-nocookie."
            >
                <Input
                    id={urlId}
                    value={url}
                    onChange={(e) => {
                        setUrl(e.target.value);
                        setError(null);
                    }}
                    placeholder="https://www.youtube.com/watch?v=…"
                    autoFocus
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            submit();
                        }
                    }}
                />
            </Field>
        </Modal>
    );
}
