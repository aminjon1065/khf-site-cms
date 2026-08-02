# Развёртывание · CMS КЧС Республики Таджикистан

Система состоит из **двух приложений**, которые разворачиваются отдельно:

| Приложение | Репозиторий | Роль | Технологии |
|---|---|---|---|
| **CMS / API** | `khf-site-cms` (Laravel) | Панель управления + публичный API `/api/v1` | PHP 8.5, Laravel 13, MySQL, Inertia/React, Fortify |
| **Публичный сайт** | `khf-site-front` (Next.js) | Сайт для граждан (SSR/ISR) | Node 20+, Next.js 16, React 19 |

Публичный сайт получает данные только через API CMS. Схема:

```
Браузер посетителя ──▶ Next.js (SSR/ISR) ──▶ CMS API (/api/v1) ──▶ MySQL
Браузер сотрудника ──▶ CMS (панель, Inertia) ──┘
```

---

## 1. Требования к серверу

- **PHP 8.5** с расширениями: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` (медиа), `bcmath`, `ctype`, `json`, `tokenizer`, `xml`, `curl`, `zip`, `intl`.
- **Composer 2**.
- **MySQL 8** (или MariaDB 10.6+).
- **Node.js 22 LTS** + npm (для сборки админ-ассетов CMS и запуска публичного сайта).
- **Веб-сервер**: Nginx (рекомендуется) + PHP-FPM.
- **HTTPS** обязателен (сессии, cookie, 2FA).

---

## 2. CMS (Laravel) — `khf-site-cms`

### 2.1. Установка зависимостей

```bash
cd /var/www/khf-site-cms
composer install --no-dev --optimize-autoloader
npm ci
```

### 2.2. Файл окружения `.env`

Скопируйте `.env.example` в `.env` и задайте **production-значения**:

```dotenv
APP_NAME="КЧС Республики Таджикистан"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cms.khf.tj
APP_TIMEZONE=Asia/Dushanbe

# Язык интерфейса и контента по умолчанию — русский
APP_LOCALE=ru
APP_FALLBACK_LOCALE=ru

# База данных (создайте БД и пользователя заранее)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=khf_site_cms
DB_USERNAME=khf
DB_PASSWORD=«надёжный-пароль»

# Сессии остаются в БД; кэш и очередь используют Redis с fallback в БД.
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_LIFETIME=120
CACHE_STORE=failover
QUEUE_CONNECTION=failover
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_CONNECT_TIMEOUT=1
REDIS_READ_TIMEOUT=1
REDIS_RETRY_INTERVAL=100
REDIS_MAX_RETRIES=3
REDIS_QUEUE_RETRY_AFTER=180
REDIS_QUEUE_BLOCK_FOR=5
QUEUE_CRITICAL=critical
QUEUE_NOTIFICATIONS=notifications
QUEUE_REVALIDATION=revalidation
MEDIA_QUEUE=media
QUEUE_MONITOR_MAX=100

# Медиа хранится на публичном диске (нужен storage:link)
MEDIA_DISK=public
MEDIA_PUBLIC_URL=https://media.khf.tj

# Рассчитайте по фактическому RSS одного FPM worker:
# floor((RAM для PHP − запас ОС/БД) / p95 RSS worker).
PHP_FPM_MAX_CHILDREN=8
OPCACHE_MEMORY_CONSUMPTION=192
OPCACHE_MAX_ACCELERATED_FILES=20000

# Daily database + immutable media originals backup and weekly restore drill.
# Use a mounted/encrypted path outside the release directory.
BACKUP_ENABLED=true
BACKUP_PATH=/var/backups/khf-cms
MYSQLDUMP_BINARY=/usr/bin/mysqldump
MYSQL_BINARY=/usr/bin/mysql
MEDIA_ORPHAN_GRACE_HOURS=24

# CORS: перечислите ТОЧНЫЕ origin публичного сайта (через запятую)
CORS_ALLOWED_ORIGINS=https://khf.tj,https://www.khf.tj

# Ревалидация публичного сайта при публикации/изменении контента.
# FRONTEND_REVALIDATION_SECRET должен побайтово совпадать с
# REVALIDATION_SECRET в .env.local публичного сайта (сгенерируйте один
# раз: openssl rand -hex 32, скопируйте в оба .env). Пусто ⇒ вебхук
# молча выключен — сайт всё ещё обновится, но только по истечении ISR
# (см. §3.1), не сразу после публикации.
FRONTEND_REVALIDATION_URL=https://khf.tj/api/revalidate
FRONTEND_REVALIDATION_SECRET=«тот же секрет, что и REVALIDATION_SECRET фронта»

# Почта (уведомления о согласовании, сброс пароля)
MAIL_MAILER=smtp
MAIL_HOST=«smtp-хост»
MAIL_PORT=587
MAIL_USERNAME=«...»
MAIL_PASSWORD=«...»
MAIL_FROM_ADDRESS=noreply@khf.tj
MAIL_FROM_NAME="КЧС Республики Таджикистан"
```

> Для production обязательно переопределите реквизиты БД, почты, `APP_URL`, CORS origins и оставьте `APP_DEBUG=false`. Бизнес-таймзона CMS — `Asia/Dushanbe`.

### 2.3. Ключ, миграции, данные

```bash
php artisan key:generate
php artisan migrate --force
```

**Заполнение справочных данных.** Минимальный набор для production (роли, регионы, настройки, меню, блоки главной, категории, страницы):

```bash
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=RegionSeeder --force
php artisan db:seed --class=TaxonomySeeder --force
php artisan db:seed --class=SettingSeeder --force
php artisan db:seed --class=MenuSeeder --force
php artisan db:seed --class=HomeBlockSeeder --force
php artisan db:seed --class=PageSeeder --force
```

> `php artisan db:seed --force` (без `--class`) дополнительно создаёт **демо-контент** (новости, предупреждения, документы, проекты, объявления, тестовых пользователей) — используйте только на staging.

### 2.4. Первый администратор

Демо-сидер создаёт `admin@khf.tj` / `password`. **На production не используйте демо-пароль.** Создайте суперадминистратора с собственным паролем:

```bash
php artisan tinker
```
```php
$u = App\Models\User::create([
    'name' => 'Системный администратор',
    'email' => 'admin@khf.tj',
    'password' => 'ВАШ-НАДЁЖНЫЙ-ПАРОЛЬ',   // будет захеширован автоматически
    'is_active' => true,
    'interface_locale' => 'ru',
    'email_verified_at' => now(),
]);
$u->assignRole(App\Enums\RoleName::Superadmin->value);
```

> В production действуют строгие требования к паролю (мин. 12 символов, разный регистр, цифры, спецсимволы, проверка по утечкам). Настроено в `AppServiceProvider`.

### 2.5. Хранилище медиа и кэши

```bash
php artisan storage:link          # public/storage → storage/app/public (медиа)

php artisan optimize
php artisan ops:production-check
```

Права доступа:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 2.6. Сборка админ-интерфейса

```bash
npm run build   # Vite: собирает Inertia/React-панель в public/build
```

### 2.7. Планировщик (обязательно)

Команда `content:process-scheduled` публикует отложенные материалы, автоматически завершает истёкшие предупреждения и рассылает уведомления. Запускается планировщиком каждые 5 минут. Добавьте **одну** cron-строку:

```cron
* * * * * cd /var/www/khf-site-cms && php artisan schedule:run >> /dev/null 2>&1
```

### 2.8. Очередь (обязательно)

Production использует отдельные очереди: `critical`, `notifications`,
`revalidation`, `default` и CPU-heavy `media`. Основные worker-процессы читают
Redis, а резервные database workers забирают задания, которые были сохранены в
БД при недоступности Redis:

```bash
php artisan queue:work redis --queue=critical,notifications,revalidation,default --sleep=1 --tries=3 --timeout=60 --max-time=3600
php artisan queue:work redis --queue=media --sleep=1 --tries=3 --timeout=150 --max-time=3600
php artisan queue:work database --queue=critical,notifications,revalidation,default --sleep=3 --tries=3 --timeout=60 --max-time=3600
php artisan queue:work database --queue=media --sleep=3 --tries=3 --timeout=150 --max-time=3600
```

Каждая команда должна управляться Supervisor/systemd с автоматическим
перезапуском и `stopwaitsecs` больше 180 секунд. После каждого deploy выполните
`php artisan queue:restart`, чтобы воркеры загрузили новый код. Scheduler каждую
минуту ставит heartbeat в `critical` и запускает `queue:monitor` для Redis и
database fallback; `/api/v1/ready` возвращает 503, если worker heartbeat старше
пяти минут.

### 2.9. Политика 2FA

Fortify 2FA включён. Настройки `security.require_2fa` и `security.require_2fa_from` определяют дату обязательного включения для привилегированных ролей. До этой даты администраторы должны настроить TOTP и сохранить recovery codes в защищённом месте.

### 2.10. Nginx (пример)

```nginx
server {
    listen 443 ssl http2;
    server_name cms.khf.tj;
    root /var/www/khf-site-cms/current/public;
    index index.php;

    ssl_certificate     /etc/ssl/khf/cms.crt;
    ssl_certificate_key /etc/ssl/khf/cms.key;

    # Версии ПО — бесплатная разведка для сканера. `expose_php=Off` в php.ini,
    # заголовок PHP-FPM снимается тут же.
    server_tokens off;
    fastcgi_hide_header X-Powered-By;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    # Заголовки безопасности PHP-ответов ставит middleware SecurityHeaders
    # (он же покрывает 404 несовпавших маршрутов). Здесь — те же заголовки для
    # ответов, которые отдаёт сам nginx и которые до PHP не доходят: медиа,
    # собранные ассеты, статические ошибки. `always` обязателен, иначе на
    # 4xx/5xx заголовка не будет.
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains" always;

    brotli on;                  # модуль ngx_brotli; без него останется gzip
    brotli_comp_level 5;
    brotli_types application/json application/javascript application/xml text/css text/plain image/svg+xml;

    gzip on;
    gzip_vary on;
    gzip_comp_level 6;
    gzip_types application/json application/javascript application/xml text/css text/plain image/svg+xml;

    location ~* ^/storage/.*/conversions/ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }

    location ~* ^/build/ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    client_max_body_size 20M;   # загрузка медиа (лимит 15 МБ + запас)
    location ~ /\.(?!well-known).* { deny all; }
}
```

В PHP-FPM включите OPcache и задайте pool по измеренной памяти, а не по числу
CPU наугад:

```ini
; php.ini / conf.d/99-khf-production.ini
expose_php=Off
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=24
; Не круглое число «на глаз»: в релизе 11 944 PHP-файла (`php artisan
; ops:capacity`), с запасом 20 % это 14 333. Значение ниже реального числа
; означает, что OPcache вытесняет классы и компилирует их заново на каждом
; запросе — при формально включённом кэше.
opcache.max_accelerated_files=20000
; Файлы релиза неизменяемы (каталог не правится после переключения ссылки),
; поэтому сверять mtime незачем. При откате меняется путь — кэш холодный,
; но корректный.
opcache.validate_timestamps=0
```

```ini
; pool.d/khf.conf
pm=dynamic
; Считается от измеренной памяти, а не «максимально»: `php artisan ops:capacity
; --ram=<MB>` замеряет пик на запрос (на текущем релизе — 43 MB при базовой
; загрузке 36 MB) и печатает, сколько воркеров помещается в бюджет. При 4 GB,
; отданных PHP-FPM, это 95; ниже — консервативное значение для узла, который
; делит память с MySQL и Redis. `ops:capacity` завершается ошибкой, если
; настроенное число в бюджет не помещается.
pm.max_children=8
pm.start_servers=2
pm.min_spare_servers=2
pm.max_spare_servers=4
pm.max_requests=500
```

После атомарного переключения release выполните `php artisan queue:restart` и
graceful reload PHP-FPM. CDN должен проксировать `MEDIA_PUBLIC_URL`, уважать
immutable headers derivatives и не кэшировать `/api/v1/health`,
`/api/v1/ready` или ответы CMS-сессии.

### 2.11. Backup, restore drill и аварийные процедуры

Проверка вручную после настройки отдельного backup volume:

```bash
php artisan ops:backup
php artisan ops:restore-drill
php artisan schedule:list
```

Backup содержит консистентный dump БД и все immutable media originals с
SHA-256 manifest; regenerable derivatives туда не копируются. Scheduler делает
backup ежедневно, а раз в неделю восстанавливает последний снимок в
изолированную временную БД и удаляет только временную БД после проверки.
Оповещение должно срабатывать по ненулевому exit code обеих команд.

Полный порядок диагностики Redis/queue/scheduler/webhook/media, controlled chaos
и безопасного отката: [`OPS_RUNBOOK.md`](./OPS_RUNBOOK.md).

Проверки после старта:

- `GET https://cms.khf.tj/api/v1/health` — liveness и соединение с БД;
- `GET https://cms.khf.tj/api/v1/ready` — БД, writable storage, heartbeat планировщика и heartbeat queue worker; также возвращает число failed jobs.

---

## 3. Публичный сайт (Next.js) — `khf-site-front`

### 3.1. Установка и окружение

```bash
cd /var/www/khf-site-front
npm ci
```

Создайте `.env.local`:

```dotenv
# База API CMS для серверных вызовов Next.js (SSR/ISR)
API_URL=https://cms.khf.tj/api/v1

# Та же база для клиентских вызовов (форма обращений, поиск).
# Должна быть доступна из браузера и разрешена в CORS_ALLOWED_ORIGINS на стороне CMS.
NEXT_PUBLIC_API_URL=https://cms.khf.tj/api/v1

# Абсолютный origin самого сайта — используется в canonical/hreflang,
# sitemap.xml, robots.txt и JSON-LD.
NEXT_PUBLIC_SITE_URL=https://khf.tj

# Принимает вебхук ревалидации от CMS (POST /api/revalidate). Должен
# побайтово совпадать с FRONTEND_REVALIDATION_SECRET в .env CMS (см.
# §2.2) — сгенерируйте один раз: openssl rand -hex 32. Пусто ⇒ вебхук
# выключен (503), сайт обновляется только по истечении ISR (60 сек).
REVALIDATION_SECRET=«тот же секрет, что и FRONTEND_REVALIDATION_SECRET CMS»
```

### 3.2. Изображения из CMS

Если где-либо используется `next/image`, добавьте хост CMS в `next.config` → `images.remotePatterns` (например, `{ protocol: 'https', hostname: 'cms.khf.tj' }`), чтобы разрешить медиа с `/storage/...`.

### 3.3. Сборка и запуск

```bash
npm run build
npm run start -- -p 3000     # Node-сервер (нужен для ISR/SSR — не статический экспорт)
```

Рекомендуется держать процесс под `pm2` или systemd:

```ini
# /etc/systemd/system/khf-front.service
[Unit]
Description=KHF public site (Next.js)
After=network.target

[Service]
WorkingDirectory=/var/www/khf-site-front/current
ExecStart=/usr/bin/npm run start -- -p 3000
Environment=NODE_ENV=production
Restart=always
User=www-data

[Install]
WantedBy=multi-user.target
```

Nginx-прокси на порт 3000:

```nginx
# Пул соединений к Next: без него nginx ходит к апстриму по HTTP/1.0 и
# закрывает соединение после каждого ответа — лишний TCP-handshake на каждый
# запрос страницы.
upstream khf_front {
    server 127.0.0.1:3000;
    keepalive 32;
}

server {
    listen 443 ssl http2;
    server_name khf.tj www.khf.tj;

    ssl_certificate     /etc/ssl/khf/site.crt;
    ssl_certificate_key /etc/ssl/khf/site.key;

    server_tokens off;

    # Сжатие делает edge, а не Next. Поэтому у апстрима запрашивается несжатый
    # ответ: иначе Next вернул бы gzip, и перепаковать его в Brotli уже нельзя.
    # Заголовки безопасности приходят от самого приложения (next.config.ts) —
    # дублировать их здесь не нужно.
    proxy_set_header Accept-Encoding "";

    brotli on;                  # модуль ngx_brotli; без него останется gzip
    brotli_comp_level 5;
    brotli_types text/html application/json application/javascript application/xml text/css text/plain image/svg+xml;

    gzip on;
    gzip_vary on;
    gzip_comp_level 6;
    gzip_types application/json application/javascript application/xml text/css text/plain image/svg+xml;

    location / {
        proxy_pass http://khf_front;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

**Почему сжатие вынесено на nginx, а не оставлено Next.** `next start` умеет
только gzip и не сжимает `/sitemap.xml` вовсе. Замер на текущей сборке
(идентичный контент, gzip -6 против brotli -5):

| Ответ | без сжатия | gzip | brotli | выигрыш brotli |
|---|---:|---:|---:|---:|
| `/ru` (HTML) | 102 250 B | 20 059 B | 16 093 B | −19,8 % |
| `/ru/news` (HTML) | 68 911 B | 13 610 B | 11 635 B | −14,5 % |
| `/sitemap.xml` | 13 185 B | 635 B | 509 B | **Next не сжимает вовсе** |
| `.css` чанк | 47 471 B | 10 327 B | 9 345 B | −9,5 % |
| `.js` чанк | 17 597 B | 5 639 B | 5 471 B | −3,0 % |

`compress: true` в `next.config.ts` при этом остаётся: он защищает случай,
когда до origin ходят напрямую (проверка, обход CDN, локальный `next start`).
Если модуля `ngx_brotli` на сервере нет, ничего не ломается — остаётся gzip,
но строку `proxy_set_header Accept-Encoding "";` тогда стоит убрать, чтобы
gzip хотя бы делал сам Next.

> Данные кэшируются через ISR (`revalidate = 60`) — изменения в CMS появляются на сайте в течение минуты. При недоступности API страницы деградируют мягко (пустые списки / статические заглушки), а не падают.

---

## 4. Обновление (redeploy)

Развёртывание — переключение символической ссылки на готовый каталог релиза, а
не сборка поверх работающего кода. Прежний способ (`php artisan down`, `git
pull` в рабочем каталоге, сборка на месте) означал заведомый простой и не
имел отката: если сборка падала на середине, в каталоге оставалось смешанное
состояние, а `artisan up` возвращать было некуда.

**CMS:**
```bash
RELEASE=/var/www/khf-site-cms/releases/$(date +%Y%m%d%H%M%S)
SHARED=/var/www/khf-site-cms/shared          # .env, storage/, база SQLite (если есть)

git clone --depth 1 --branch main git@github.com:…/khf-site-cms.git "$RELEASE"
ln -s "$SHARED/.env" "$RELEASE/.env"
rm -rf "$RELEASE/storage" && ln -s "$SHARED/storage" "$RELEASE/storage"

cd "$RELEASE"
# `--classmap-authoritative`: автозагрузчик перестаёт искать классы по файловой
# системе, что и требуется в неизменяемом релизе.
composer install --no-dev --prefer-dist --classmap-authoritative --no-interaction
npm ci && npm run build
php artisan migrate --force                  # миграции обязаны быть совместимы с текущим релизом
php artisan optimize                         # config + route + view + event в один шаг
php artisan ops:capacity --ram=4096          # OPcache и воркеры — по измерению, а не на глаз
php artisan ops:production-check

ln -sfn "$RELEASE" /var/www/khf-site-cms/current
systemctl reload php8.5-fpm                  # graceful: текущие запросы дорабатывают
php artisan queue:restart                    # воркеры подхватят новый код после текущей задачи
```

Откат — переключение ссылки обратно, без сборки:

```bash
ln -sfn /var/www/khf-site-cms/releases/<предыдущий> /var/www/khf-site-cms/current
systemctl reload php8.5-fpm && php artisan queue:restart
```

> Откат кода мгновенный, откат схемы БД — нет. Поэтому миграции пишутся так,
> чтобы предыдущий релиз продолжал работать с новой схемой (сначала добавить
> колонку, потом перестать писать в старую, и только следующим релизом её
> удалить). Иначе «мгновенный откат» существует только на бумаге.

**Публичный сайт:** та же схема — сборка в новом каталоге релиза, затем
переключение ссылки и `systemctl reload khf-front`. `next build` обязан
завершиться до переключения: сборка проверяет доступность CMS и падает, если
та не отвечает (fail-fast против пустого релиза).

```bash
RELEASE=/var/www/khf-site-front/releases/$(date +%Y%m%d%H%M%S)
git clone --depth 1 --branch main git@github.com:…/khf-site-front.git "$RELEASE"
ln -s /var/www/khf-site-front/shared/.env.production "$RELEASE/.env.production"
cd "$RELEASE" && npm ci && npm run build
ln -sfn "$RELEASE" /var/www/khf-site-front/current
systemctl reload khf-front
```

---

## 5. Проверка после развёртывания

- [ ] `https://cms.khf.tj/api/v1/health` → `{"status":"ok"}`.
- [ ] После первого запуска scheduler `https://cms.khf.tj/api/v1/ready` → `{"status":"ready"}`.
- [ ] Вход в панель под созданным администратором; демо-пароль не работает.
- [ ] Публичный сайт открывается, шапка/подвал/меню приходят из CMS.
- [ ] `/api/v1/settings`, `/api/v1/menu`, `/api/v1/regions` возвращают данные.
- [ ] Форма обращения на `/contacts` отправляется (проверяет CORS + throttle).
- [ ] Загрузка файла в медиабиблиотеку и `php artisan storage:link` работают (файл доступен по URL).
- [ ] Cron `schedule:run` активен (`php artisan schedule:list`).
- [ ] Queue worker активен и перезапущен после deploy (`php artisan queue:restart`).
- [ ] Ревалидация сайта: опубликуйте тестовую новость в CMS, страница `/ru/news` на публичном сайте должна показать её при следующем заходе за секунды, не за 60 сек (проверяет, что `FRONTEND_REVALIDATION_SECRET`/`REVALIDATION_SECRET` реально совпадают, а не просто оба заполнены).

---

## 6. Чек-лист безопасности (production)

- [ ] `APP_DEBUG=false`, `APP_ENV=production`.
- [ ] HTTPS на обоих доменах; `SESSION_SECURE_COOKIE=true`.
- [ ] `CORS_ALLOWED_ORIGINS` — только реальные origin публичного сайта (не `*`).
- [ ] Демо-пароль `password` заменён; включена двухфакторная аутентификация (Fortify) для администраторов.
- [ ] Деактивированные учётные записи (`is_active=false`) не могут войти — проверено.
- [ ] Строгие требования к паролю активны в production (`AppServiceProvider`).
- [ ] Публичный API отдаёт только опубликованный контент; служебные группы настроек (`security`, `integrations`, `backup`) наружу не выводятся.
- [ ] Загрузка медиа ограничена типами (изображения + документы, без SVG) и размером 15 МБ.
- [ ] Регулярные резервные копии БД `khf_site_cms` и каталога `storage/app/public`.
- [ ] Логи активности (`activity_log`) и журнал согласований сохраняются.
- [ ] Заголовки безопасности приходят с **обоих** доменов, включая 404 и статику:
      `curl -sI https://khf.tj/ru | grep -iE 'content-security-policy|x-frame|x-content-type|referrer|permissions|strict-transport'`
      и то же для `https://cms.khf.tj/login`, `https://cms.khf.tj/nope` (несовпавший маршрут — заголовки должны быть и там).
- [ ] Версии ПО не раскрываются: в ответах нет `X-Powered-By`, а `Server` без номера версии.
- [ ] `php artisan ops:production-check` и `php artisan ops:capacity --ram=<бюджет>` на релизе — оба успешны
      (второй падает, если OPcache настроен меньше, чем файлов в релизе, или воркеры не помещаются в память).
- [ ] Откат проверен на самом релизе: переключение ссылки `current` на предыдущий каталог и `reload` возвращают рабочую версию.
- [ ] Сжатие работает на edge: `curl -sI -H 'Accept-Encoding: br' https://khf.tj/ru | grep -i content-encoding` → `br`;
      `curl -sI -H 'Accept-Encoding: br' https://khf.tj/sitemap.xml | grep -i content-encoding` → не пусто
      (карта сайта — единственный ответ, который сам Next не сжимает вовсе).
