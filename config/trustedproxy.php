<?php

// Глобальный middleware TrustProxies читает этот конфиг, когда proxies не
// заданы статически в bootstrap/app.php (audit I-1, 2026-09-20). Без него
// все per-IP rate limits (api-public, rum, submissions 10/мин) считаются
// по IP ближайшего хопа — то есть на всех посетителей суммарно.
//
// Формат TRUSTED_PROXIES:
//   - пусто          — прокси не доверяются, $request->ip() = REMOTE_ADDR;
//   - 'REMOTE_ADDR'  — доверять прямому хопу (nginx/LB на том же хосте);
//   - список         — IP/подсети через запятую: подсеть LB и SSR-сервера Next;
//   - '*'            — доверять всем (ТОЛЬКО локальная разработка).
//
// Цепочка должна доносить клиентский IP: nginx обязан прокинуть
// X-Forwarded-For ($proxy_add_x_forwarded_for) — см. deploy/nginx.
$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'proxies' => match (true) {
        $proxies === '' => null,
        $proxies === '*' => '*',
        default => array_map(trim(...), explode(',', $proxies)),
    },
];
