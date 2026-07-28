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

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
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
    root /var/www/khf-site-cms/public;
    index index.php;

    ssl_certificate     /etc/ssl/khf/cms.crt;
    ssl_certificate_key /etc/ssl/khf/cms.key;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    client_max_body_size 20M;   # загрузка медиа (лимит 15 МБ + запас)
    location ~ /\.(?!well-known).* { deny all; }
}
```

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
WorkingDirectory=/var/www/khf-site-front
ExecStart=/usr/bin/npm run start -- -p 3000
Environment=NODE_ENV=production
Restart=always
User=www-data

[Install]
WantedBy=multi-user.target
```

Nginx-прокси на порт 3000:

```nginx
server {
    listen 443 ssl http2;
    server_name khf.tj www.khf.tj;

    ssl_certificate     /etc/ssl/khf/site.crt;
    ssl_certificate_key /etc/ssl/khf/site.key;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

> Данные кэшируются через ISR (`revalidate = 60`) — изменения в CMS появляются на сайте в течение минуты. При недоступности API страницы деградируют мягко (пустые списки / статические заглушки), а не падают.

---

## 4. Обновление (redeploy)

**CMS:**
```bash
cd /var/www/khf-site-cms && php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan up
```

**Публичный сайт:**
```bash
cd /var/www/khf-site-front && git pull
npm ci && npm run build
systemctl restart khf-front     # или: pm2 reload khf-front
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
