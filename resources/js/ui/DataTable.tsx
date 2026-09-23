import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
} from 'lucide-react';
import { Fragment } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { Blueprint } from './Blueprint';
import { EmptyState, Skeleton } from './Feedback';
import { Checkbox } from './Field';

export interface Column<T> {
    key: string;
    header: ReactNode;
    width?: number | string;
    align?: 'left' | 'right' | 'center';
    sortable?: boolean;
    render: (row: T) => ReactNode;
    className?: string;
    /**
     * A secondary column the table drops when it gets narrow, so the title
     * keeps room to read: 1 goes first (table under 1180 px), 2 next
     * (under 980 px). Like WordPress list tables.
     */
    optional?: 1 | 2;
}

/**
 * The title column has no width of its own and takes what the fixed columns
 * leave; on a phone they left it nothing. A narrow table keeps it this much
 * room and scrolls sideways instead (`--ui-table-min-width`, components.css).
 */
const FLEXIBLE_COLUMN_MIN_WIDTH = 200;
const CHECK_COLUMN_WIDTH = 36;

export interface SortState {
    key: string;
    dir: 'asc' | 'desc';
}

interface DataTableProps<T> {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    emptyTitle?: ReactNode;
    emptyHint?: ReactNode;
    emptyAction?: ReactNode;
    selectable?: boolean;
    selected?: Set<string | number>;
    onSelectedChange?: (keys: Set<string | number>) => void;
    /** Accessible name of a row's checkbox, e.g. «Выбрать «Паводок»». */
    selectLabel?: (row: T) => string;
    sort?: SortState | null;
    onSortChange?: (sort: SortState) => void;
    onRowClick?: (row: T) => void;
    bulkActions?: ReactNode;
    maxBodyHeight?: number | string;
    renderSubRow?: (row: T) => ReactNode;
}

export function DataTable<T>({
    columns,
    rows,
    rowKey,
    loading = false,
    error = null,
    onRetry,
    emptyTitle = 'Ничего не найдено',
    emptyHint,
    emptyAction,
    selectable = false,
    selected = new Set(),
    onSelectedChange,
    selectLabel,
    sort = null,
    onSortChange,
    onRowClick,
    bulkActions,
    maxBodyHeight,
    renderSubRow,
}: DataTableProps<T>) {
    const allKeys = rows.map(rowKey);
    // Optional columns collapse on narrow tables, so they don't count.
    const shownColumns = columns.filter((col) => !col.optional);
    const minTableWidth = shownColumns.some(
        (col) => typeof col.width !== 'number',
    )
        ? shownColumns.reduce(
              (sum, col) =>
                  typeof col.width === 'number' ? sum + col.width : sum,
              (selectable ? CHECK_COLUMN_WIDTH : 0) + FLEXIBLE_COLUMN_MIN_WIDTH,
          )
        : undefined;
    const allSelected =
        allKeys.length > 0 && allKeys.every((k) => selected.has(k));

    const toggleAll = () => {
        if (!onSelectedChange) {
            return;
        }

        onSelectedChange(allSelected ? new Set() : new Set(allKeys));
    };

    const toggleOne = (key: string | number) => {
        if (!onSelectedChange) {
            return;
        }

        const next = new Set(selected);

        if (next.has(key)) {
            next.delete(key);
        } else {
            next.add(key);
        }

        onSelectedChange(next);
    };

    const handleSort = (col: Column<T>) => {
        if (!col.sortable || !onSortChange) {
            return;
        }

        const dir =
            sort?.key === col.key && sort.dir === 'asc' ? 'desc' : 'asc';
        onSortChange({ key: col.key, dir });
    };

    return (
        <Blueprint style={{ overflow: 'hidden' }}>
            {selectable && selected.size > 0 && (
                <div
                    style={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        alignItems: 'center',
                        gap: 12,
                        padding: '8px 12px',
                        background: 'var(--brand-100)',
                        borderBottom: '1px solid var(--color-divider)',
                        fontSize: 13,
                    }}
                >
                    <strong aria-live="polite">Выбрано: {selected.size}</strong>
                    {bulkActions}
                </div>
            )}
            <div
                className="ui-scroll ui-table-container"
                style={{ overflow: 'auto', maxHeight: maxBodyHeight }}
            >
                <table
                    className="ui-table"
                    style={
                        minTableWidth === undefined
                            ? undefined
                            : ({
                                  '--ui-table-min-width': `${minTableWidth}px`,
                              } as CSSProperties)
                    }
                >
                    <thead>
                        <tr>
                            {selectable && (
                                <th className="ui-th-check">
                                    <Checkbox
                                        checked={allSelected}
                                        onChange={toggleAll}
                                        aria-label="Выбрать все"
                                    />
                                </th>
                            )}
                            {columns.map((col) => (
                                <th
                                    key={col.key}
                                    className={cn(
                                        col.sortable && 'sortable',
                                        col.optional &&
                                            `ui-col-optional-${col.optional}`,
                                    )}
                                    style={{
                                        width: col.width,
                                        textAlign: col.align ?? 'left',
                                    }}
                                    onClick={() => handleSort(col)}
                                >
                                    <span
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 4,
                                        }}
                                    >
                                        {col.header}
                                        {col.sortable &&
                                            (sort?.key === col.key ? (
                                                sort.dir === 'asc' ? (
                                                    <ArrowUp
                                                        size={13}
                                                        strokeWidth={1.5}
                                                    />
                                                ) : (
                                                    <ArrowDown
                                                        size={13}
                                                        strokeWidth={1.5}
                                                    />
                                                )
                                            ) : (
                                                <ChevronsUpDown
                                                    size={13}
                                                    strokeWidth={1.5}
                                                    style={{ opacity: 0.4 }}
                                                />
                                            ))}
                                    </span>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            Array.from({ length: 6 }).map((_, i) => (
                                <tr key={i}>
                                    {selectable && (
                                        <td className="ui-td-check">
                                            <Skeleton w={16} h={16} />
                                        </td>
                                    )}
                                    {columns.map((col) => (
                                        <td key={col.key}>
                                            <Skeleton w="70%" />
                                        </td>
                                    ))}
                                </tr>
                            ))
                        ) : error ? (
                            <tr>
                                <td
                                    colSpan={
                                        columns.length + (selectable ? 1 : 0)
                                    }
                                >
                                    <EmptyState
                                        title="Ошибка загрузки"
                                        hint={error}
                                        action={
                                            onRetry && (
                                                <button
                                                    type="button"
                                                    className="ui-btn ui-btn-secondary ui-btn-sm"
                                                    onClick={onRetry}
                                                >
                                                    Повторить
                                                </button>
                                            )
                                        }
                                    />
                                </td>
                            </tr>
                        ) : rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={
                                        columns.length + (selectable ? 1 : 0)
                                    }
                                >
                                    <EmptyState
                                        title={emptyTitle}
                                        hint={emptyHint}
                                        action={emptyAction}
                                    />
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => {
                                const key = rowKey(row);
                                const subRow = renderSubRow
                                    ? renderSubRow(row)
                                    : null;

                                return (
                                    <Fragment key={key}>
                                        <tr
                                            className={cn(
                                                selected.has(key) &&
                                                    'is-selected',
                                            )}
                                            onClick={
                                                onRowClick
                                                    ? () => onRowClick(row)
                                                    : undefined
                                            }
                                            style={
                                                onRowClick
                                                    ? { cursor: 'pointer' }
                                                    : undefined
                                            }
                                        >
                                            {selectable && (
                                                <td
                                                    className="ui-td-check"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    <Checkbox
                                                        checked={selected.has(
                                                            key,
                                                        )}
                                                        onChange={() =>
                                                            toggleOne(key)
                                                        }
                                                        aria-label={
                                                            selectLabel?.(
                                                                row,
                                                            ) ??
                                                            'Выбрать строку'
                                                        }
                                                    />
                                                </td>
                                            )}
                                            {columns.map((col) => (
                                                <td
                                                    key={col.key}
                                                    className={cn(
                                                        col.className,
                                                        col.key === 'actions' &&
                                                            'cell-actions',
                                                        col.optional &&
                                                            `ui-col-optional-${col.optional}`,
                                                    )}
                                                    style={{
                                                        textAlign:
                                                            col.align ?? 'left',
                                                    }}
                                                    onClick={
                                                        col.key === 'actions'
                                                            ? (event) =>
                                                                  event.stopPropagation()
                                                            : undefined
                                                    }
                                                >
                                                    {col.render(row)}
                                                </td>
                                            ))}
                                        </tr>
                                        {subRow && (
                                            <tr className="ui-table-subrow">
                                                <td
                                                    colSpan={
                                                        columns.length +
                                                        (selectable ? 1 : 0)
                                                    }
                                                    style={{ padding: 0 }}
                                                >
                                                    {subRow}
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </Blueprint>
    );
}

export function Pagination({
    from,
    to,
    total,
    onPrev,
    onNext,
    perPage,
    onPerPageChange,
    perPageOptions = [25, 50, 100],
}: {
    from: number;
    to: number;
    total: number;
    onPrev?: () => void;
    onNext?: () => void;
    perPage?: number;
    onPerPageChange?: (n: number) => void;
    perPageOptions?: number[];
}) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 14,
                padding: '10px 2px',
                fontSize: 13,
                color: 'var(--color-neutral-600)',
            }}
        >
            {perPage !== undefined && onPerPageChange && (
                <label
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                    }}
                >
                    Строк на странице:
                    <select
                        className="ui-select"
                        style={{
                            width: 'auto',
                            minHeight: 30,
                            padding: '2px 26px 2px 8px',
                        }}
                        value={perPage}
                        onChange={(e) =>
                            onPerPageChange(Number(e.target.value))
                        }
                    >
                        {perPageOptions.map((n) => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </select>
                </label>
            )}
            <span className="ui-mono" style={{ marginLeft: 'auto' }}>
                {total === 0 ? '0' : `${from}–${to}`} из {total}
            </span>
            <div style={{ display: 'inline-flex', gap: 4 }}>
                <button
                    type="button"
                    className="ui-btn ui-btn-icon ui-btn-secondary"
                    disabled={!onPrev}
                    onClick={onPrev}
                    aria-label="Назад"
                >
                    <ChevronLeft size={17} strokeWidth={1.5} />
                </button>
                <button
                    type="button"
                    className="ui-btn ui-btn-icon ui-btn-secondary"
                    disabled={!onNext}
                    onClick={onNext}
                    aria-label="Вперёд"
                >
                    <ChevronRight size={17} strokeWidth={1.5} />
                </button>
            </div>
        </div>
    );
}
