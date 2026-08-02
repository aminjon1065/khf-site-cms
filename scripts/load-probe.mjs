import process from 'node:process';

// Нагрузочный замер публичного API. Отвечает на три вопроса, на которые нельзя
// ответить рассуждением: сколько запросов в секунду держит стенд, что он отдаёт
// под нагрузкой (200 или 429/5xx) и сколько запросов из волны действительно
// пересобирают read-модель на холодном кэше.
//
//   npm run load:probe -- <база> <путь> <одновременных> <волн>
//   npm run load:probe -- https://cms.khf.tj/api/v1 "/home?locale=ru" 50 4
//
// Числа привязаны к стенду: тот же код на ноутбуке в контейнере и на боевом
// узле даст разные значения. Сравнивать имеет смысл замеры одного стенда до и
// после изменения, а не абсолютные цифры между машинами.

const base = process.argv[2] ?? 'https://khf-site-cms.test/api/v1';
const path = process.argv[3] ?? '/home?locale=ru';
const concurrency = Number(process.argv[4] ?? 50);
const rounds = Number(process.argv[5] ?? 4);

// Локальный стенд ходит по сертификату mkcert, которого нет в хранилище Node.
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

function percentile(values, share) {
    const sorted = [...values].sort((left, right) => left - right);

    return sorted[
        Math.min(sorted.length - 1, Math.floor(share * sorted.length))
    ];
}

async function probe() {
    const startedAt = performance.now();

    try {
        const response = await fetch(base + path, {
            headers: { 'accept-encoding': 'gzip' },
        });
        await response.arrayBuffer();

        return {
            ms: performance.now() - startedAt,
            status: response.status,
            cache: response.headers.get('x-cache') ?? '—',
        };
    } catch {
        return { ms: performance.now() - startedAt, status: 0, cache: 'error' };
    }
}

async function burst(size) {
    const startedAt = performance.now();
    const results = await Promise.all(Array.from({ length: size }, probe));
    const wall = performance.now() - startedAt;
    const durations = results.map((result) => result.ms);
    const byStatus = {};
    const byCache = {};

    for (const result of results) {
        byStatus[result.status] = (byStatus[result.status] ?? 0) + 1;
        byCache[result.cache] = (byCache[result.cache] ?? 0) + 1;
    }

    return {
        wall: Math.round(wall),
        rps: Math.round((size / wall) * 1000),
        p50: Math.round(percentile(durations, 0.5)),
        p95: Math.round(percentile(durations, 0.95)),
        max: Math.round(Math.max(...durations)),
        byStatus,
        byCache,
    };
}

console.log(
    `цель: ${base}${path}, ${concurrency} одновременных, ${rounds} волн`,
);

for (let round = 1; round <= rounds; round++) {
    const stats = await burst(concurrency);

    console.log(
        `волна ${round}: ${stats.wall} мс всего, ~${stats.rps} rps, ` +
            `p50=${stats.p50} p95=${stats.p95} max=${stats.max} мс, ` +
            `статусы=${JSON.stringify(stats.byStatus)}, ` +
            `кэш=${JSON.stringify(stats.byCache)}`,
    );
}
