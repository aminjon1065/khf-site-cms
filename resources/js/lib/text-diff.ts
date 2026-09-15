/**
 * Построчный diff (LCS) для сравнения ревизий редакционных материалов.
 * Чистая функция без зависимостей от React/DOM — тестируется в Node.
 */

export interface DiffSegment {
    type: 'context' | 'added' | 'removed';
    lines: string[];
}

/** Ограничение LCS-матрицы: сверх него сравнение вырождается в замену. */
const MAX_LCS_LINES = 1500;
const CONTEXT_RADIUS = 2;

export function diffLines(before: string, after: string): DiffSegment[] {
    const oldLines = before === '' ? [] : before.split('\n');
    const newLines = after === '' ? [] : after.split('\n');

    if (oldLines.length > MAX_LCS_LINES || newLines.length > MAX_LCS_LINES) {
        return [
            { type: 'removed', lines: oldLines },
            { type: 'added', lines: newLines },
        ];
    }

    const marks = lcsMarks(oldLines, newLines);
    const raw: DiffSegment[] = [];
    let i = 0;
    let j = 0;

    const push = (type: DiffSegment['type'], lines: string[]) => {
        const last = raw[raw.length - 1];

        if (last && last.type === type) {
            last.lines.push(...lines);
        } else {
            raw.push({ type, lines: [...lines] });
        }
    };

    while (i < oldLines.length && j < newLines.length) {
        if (marks[i][j]) {
            push('context', [oldLines[i]]);
            i += 1;
            j += 1;
        } else {
            // Кусок удаления/вставки: удаляем, пока не встретим общий
            // элемент, попутно собирая вставленные строки.
            let delCount = 0;

            while (
                i + delCount < oldLines.length &&
                !rowHasMark(marks, i + delCount, j)
            ) {
                delCount += 1;
            }

            let addCount = 0;

            while (
                j + addCount < newLines.length &&
                !marks[i + delCount]?.[j + addCount]
            ) {
                addCount += 1;
            }

            if (delCount > 0) {
                push('removed', oldLines.slice(i, i + delCount));
            }

            if (addCount > 0) {
                push('added', newLines.slice(j, j + addCount));
            }

            i += delCount;
            j += addCount;
        }
    }

    if (i < oldLines.length) {
        push('removed', oldLines.slice(i));
    }

    if (j < newLines.length) {
        push('added', newLines.slice(j));
    }

    return collapseContext(raw);
}

function rowHasMark(marks: boolean[][], row: number, fromCol: number): boolean {
    for (let col = fromCol; col < (marks[row]?.length ?? 0); col += 1) {
        if (marks[row][col]) {
            return true;
        }
    }

    return false;
}

/**
 * Таблица общих элементов: marks[i][j] = строка i старого текста равна
 * строке j нового И входит в наибольшую общую подпоследовательность
 * от позиции (i, j) до конца.
 */
function lcsMarks(oldLines: string[], newLines: string[]): boolean[][] {
    const n = oldLines.length;
    const m = newLines.length;
    const dp: number[][] = Array.from({ length: n + 1 }, () =>
        new Array<number>(m + 1).fill(0),
    );

    for (let i = n - 1; i >= 0; i -= 1) {
        for (let j = m - 1; j >= 0; j -= 1) {
            dp[i][j] =
                oldLines[i] === newLines[j]
                    ? dp[i + 1][j + 1] + 1
                    : Math.max(dp[i + 1][j], dp[i][j + 1]);
        }
    }

    const marks: boolean[][] = Array.from({ length: n }, () =>
        new Array<boolean>(m).fill(false),
    );
    let i = 0;
    let j = 0;

    while (i < n && j < m) {
        if (oldLines[i] === newLines[j]) {
            marks[i][j] = true;
            i += 1;
            j += 1;
        } else if (dp[i + 1][j] >= dp[i][j + 1]) {
            i += 1;
        } else {
            j += 1;
        }
    }

    return marks;
}

/** Сжимает дальние контекстные строки до ±CONTEXT_RADIUS строк вокруг изменений. */
function collapseContext(segments: DiffSegment[]): DiffSegment[] {
    const hasChanges = segments.some((s) => s.type !== 'context');

    if (!hasChanges) {
        return segments;
    }

    const entries = segments.flatMap((s) =>
        s.lines.map((line) => ({ type: s.type, line })),
    );
    const keep = entries.map((entry) => entry.type !== 'context');

    entries.forEach((entry, index) => {
        if (entry.type === 'context') {
            return;
        }

        for (let delta = -CONTEXT_RADIUS; delta <= CONTEXT_RADIUS; delta += 1) {
            const near = index + delta;

            if (near >= 0 && near < entries.length) {
                keep[near] = true;
            }
        }
    });

    const result: DiffSegment[] = [];
    let skipped = false;

    entries.forEach((entry, index) => {
        if (keep[index]) {
            pushSegment(result, entry.type, entry.line);
            skipped = false;
        } else if (!skipped) {
            pushSegment(result, 'context', '…');
            skipped = true;
        }
    });

    return result;
}

function pushSegment(
    target: DiffSegment[],
    type: DiffSegment['type'],
    line: string,
): void {
    const last = target[target.length - 1];

    if (last && last.type === type) {
        last.lines.push(line);
    } else {
        target.push({ type, lines: [line] });
    }
}

/** Сравнивает два значения поля ревизии: скаляры — как текст. */
export function fieldChanged(before: unknown, after: unknown): boolean {
    return normalizeField(before) !== normalizeField(after);
}

export function normalizeField(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}
