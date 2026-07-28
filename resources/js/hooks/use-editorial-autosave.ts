import { useCallback, useEffect, useRef, useState } from 'react';
import {
    index as revisionIndex,
    restore,
    store,
} from '@/actions/App/Http/Controllers/Cms/EditorialAutosaveController';

export interface EditorialAutosaveConfig<T extends object> {
    contentType:
        | 'news'
        | 'pages'
        | 'projects'
        | 'instructions'
        | 'announcements'
        | 'documents';
    contentId: number | null;
    baseVersion: string | null;
    data: T;
    onRecover: (data: Partial<T>) => void;
}

interface RecoveryCopy<T> {
    data: Partial<T>;
    draftKey: string;
    savedAt: string;
}

interface Conflict<T> {
    remote: Partial<T>;
    savedAt: string | null;
    savedBy: string | null;
}

export interface EditorialRevisionItem {
    id: number;
    source: string;
    saved_by: string | null;
    saved_at: string;
}

type AutosaveState = 'idle' | 'saving' | 'saved' | 'offline' | 'conflict';

export function useEditorialAutosave<T extends object>(
    config: EditorialAutosaveConfig<T>,
    isDirty: boolean,
) {
    const storageKey = `editorial-recovery:${config.contentType}:${config.contentId ?? 'new'}`;
    const initialCopy = readRecoveryCopy<T>(storageKey);
    const draftKey = useRef(initialCopy?.draftKey ?? crypto.randomUUID());
    const openedAt = useRef(new Date().toISOString());
    const revisionCursor = useRef<number | null>(null);
    const serverVersion = useRef(config.baseVersion);
    const [state, setState] = useState<AutosaveState>('idle');
    const [savedAt, setSavedAt] = useState<string | null>(null);
    const [recovery, setRecovery] = useState<RecoveryCopy<T> | null>(
        initialCopy,
    );
    const [conflict, setConflict] = useState<Conflict<T> | null>(null);
    const [history, setHistory] = useState<EditorialRevisionItem[]>([]);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [historyLoading, setHistoryLoading] = useState(false);

    const saveLocalCopy = useCallback(() => {
        const copy: RecoveryCopy<T> = {
            data: serializableData(config.data),
            draftKey: draftKey.current,
            savedAt: new Date().toISOString(),
        };

        localStorage.setItem(storageKey, JSON.stringify(copy));

        return copy;
    }, [config.data, storageKey]);

    const autosave = useCallback(
        async (force = false) => {
            const localCopy = saveLocalCopy();
            setState('saving');

            try {
                const response = await fetch(store.url(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        content_type: config.contentType,
                        content_id: config.contentId,
                        draft_key: draftKey.current,
                        data: localCopy.data,
                        base_version: serverVersion.current,
                        revision_cursor: revisionCursor.current,
                        opened_at: openedAt.current,
                        force,
                    }),
                });
                const result = (await response.json()) as {
                    message?: string;
                    remote?: Partial<T>;
                    revision_id?: number | null;
                    saved_at?: string | null;
                    saved_by?: string | null;
                    server_version?: string | null;
                };

                if (response.status === 409) {
                    setConflict({
                        remote: result.remote ?? {},
                        savedAt: result.saved_at ?? null,
                        savedBy: result.saved_by ?? null,
                    });
                    setState('conflict');

                    return;
                }

                if (!response.ok || !result.revision_id) {
                    throw new Error(result.message ?? 'Autosave failed');
                }

                revisionCursor.current = result.revision_id;
                serverVersion.current =
                    result.server_version ?? serverVersion.current;
                setSavedAt(result.saved_at ?? new Date().toISOString());
                setConflict(null);
                setState('saved');
            } catch {
                setState('offline');
            }
        },
        [config.contentId, config.contentType, saveLocalCopy],
    );

    useEffect(() => {
        if (!isDirty) {
            return;
        }

        saveLocalCopy();
        const timeout = window.setTimeout(() => void autosave(), 1500);

        return () => window.clearTimeout(timeout);
    }, [autosave, isDirty, saveLocalCopy]);

    const recoverLocal = () => {
        if (recovery) {
            config.onRecover(recovery.data);
            setRecovery(null);
        }
    };

    const discardRecovery = () => {
        localStorage.removeItem(storageKey);
        setRecovery(null);
    };

    const useRemoteConflict = () => {
        if (conflict) {
            config.onRecover(conflict.remote);
            setConflict(null);
            setState('idle');
            openedAt.current = new Date().toISOString();
        }
    };

    const keepLocalConflict = async () => {
        await autosave(true);
    };

    const loadHistory = async () => {
        if (config.contentId === null) {
            return;
        }

        setHistoryOpen(true);
        setHistoryLoading(true);

        try {
            const response = await fetch(
                revisionIndex.url({
                    contentType: config.contentType,
                    contentId: config.contentId,
                }),
                {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                },
            );
            const result = (await response.json()) as {
                data?: EditorialRevisionItem[];
            };

            if (response.ok) {
                setHistory(result.data ?? []);
            }
        } finally {
            setHistoryLoading(false);
        }
    };

    const restoreRevision = async (revisionId: number) => {
        const response = await fetch(restore.url(revisionId), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });
        const result = (await response.json()) as {
            data?: Partial<T>;
            server_version?: string | null;
        };

        if (!response.ok || !result.data) {
            return;
        }

        serverVersion.current = result.server_version ?? serverVersion.current;
        config.onRecover(result.data);
        setHistoryOpen(false);
        setState('saved');
        setSavedAt(new Date().toISOString());
    };

    return {
        conflict,
        discardRecovery,
        history,
        historyLoading,
        historyOpen,
        keepLocalConflict,
        loadHistory,
        recoverLocal,
        recovery,
        restoreRevision,
        savedAt,
        setHistoryOpen,
        state,
        useRemoteConflict,
    };
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function readRecoveryCopy<T extends object>(
    storageKey: string,
): RecoveryCopy<T> | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const value = localStorage.getItem(storageKey);

        return value ? (JSON.parse(value) as RecoveryCopy<T>) : null;
    } catch {
        localStorage.removeItem(storageKey);

        return null;
    }
}

function serializableData<T extends object>(data: T): Partial<T> {
    return JSON.parse(
        JSON.stringify(data, (_key, value: unknown) => {
            if (value instanceof File || value instanceof Blob) {
                return undefined;
            }

            return value;
        }),
    ) as Partial<T>;
}
