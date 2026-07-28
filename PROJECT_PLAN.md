# Портал КЧС (khf.tj) — сквозной план работ: CMS + публичный сайт

**Составлен:** 27 июля 2026
**Область:** `khf-site-cms` (Laravel 13 / PHP 8.5 / Inertia+React) и `khf-site-front` (Next.js 16 / React 19)
**Назначение:** рабочий документ для ИИ-агента и разработчика. Каждая задача самодостаточна: цель → файлы → шаги → критерий приёмки → команда проверки.

Сопутствующие документы: [`AGENT_PROMPT.md`](./AGENT_PROMPT.md) (промпт запуска агента), [`CMS_AUDIT.md`](./CMS_AUDIT.md) (аудит бэкенда), [`DEPLOYMENT.md`](./DEPLOYMENT.md) (развёртывание), [`CMS_IMPLEMENTATION_PLAN.md`](./CMS_IMPLEMENTATION_PLAN.md) (архивный baseline).

---

## 1. Карта системы

```
Гражданин ──▶ Next.js (SSR/ISR, /ru /tj /en) ──▶ CMS API (/api/v1) ──▶ БД
Сотрудник ──▶ CMS-панель (Inertia/React) ──────┘
                    │
                    └── публикация → unique Job RevalidateFrontend → POST /api/revalidate → granular revalidateTag(...)
```

| | `khf-site-cms` | `khf-site-front` |
|---|---|---|
| Репозиторий | `github.com/aminjon1065/khf-site-cms` | `github.com/aminjon1065/khf-site-front` |
| Стек | PHP 8.5, Laravel 13, Inertia 3, React 19, Tailwind 4, Vite 8 | Next.js 16 (App Router, Turbopack), React 19, Tailwind 4 |
| Пакеты | fortify, spatie/{permission,translatable,medialibrary,activitylog}, mews/purifier, wayfinder, pest 4, larastan 3 | d3-geo, topojson-client, lucide-react |
| БД (dev) | SQLite `database/database.sqlite` (в git НЕ хранится) | — |
| БД (prod) | MySQL 8 | — |
| Тесты | Pest: 338 тестов | нет ни одного |
| CI | `.github/workflows/tests.yml` (`composer ci:check`) | нет |

**Локали.** В URL фронта таджикский — `tj`, в API/CMS/ISO — `tg`. Маппинг изолирован в `lib/i18n/config.ts` (`toApiLocale`, `htmlLang`). Решение зафиксировано: split сохраняем, миграцию CMS на `tj` не делаем. **При отладке всегда `curl '...?locale=tg'`**, иначе `?locale=tj` тихо откатится на `ru` и создаст ложное впечатление «таджикский не работает».

---

## 2. Текущее состояние (замеры 27.07.2026)

### 2.1. Что уже работает

**CMS (готово и покрыто тестами):**
- 21 модуль админки: предупреждения, новости, инструкции, документы, проекты, объявления, страницы, регионы/районы, обращения граждан, медиатека, таксономия, меню, блоки главной, настройки, пользователи, роли, согласование, уведомления, dashboard, журнал действий, центр контроля.
- Единый workflow (черновик → на согласование → опубликовано → архив) для всех редакционных типов, региональная авторизация, policy-проверки объектов, 2FA (Fortify), плановая публикация через `content:process-scheduled`.
- Публичный read-only API `api/v1`: 25 маршрутов, ETag + `Cache-Control: public, max-age=30, stale-while-revalidate=60`, `X-Request-ID`, структурированный access-лог (`PublicApiResponse`), `health`/`ready`.
- Мультиязычность контента `ru/tg/en` через spatie/translatable; `ResolveApiLocale` резолвит `?locale=`.
- Rich-text: Tiptap v3 + санитайзинг `mews/purifier` (профиль `news`), адаптивные превью (srcset), медиатека с метаданными и кроппером.
- Вебхук ревалидации фронта при публикации.

**Фронт (готово):**
- 20 публичных маршрутов × 3 локали (`app/[locale]/...`), `proxy.ts` (Next 16 переименовал middleware в proxy) с редиректом и cookie `NEXT_LOCALE`.
- Данные из CMS: главная, новости (+деталь), инструкции (+деталь), документы, проекты (+деталь), объявления, предупреждения (+деталь), карта рисков, поиск, произвольные страницы, шапка/подвал/меню/настройки.
- SEO: `lib/seo.ts` — canonical + hreflang (`ru`/`tg`/`en`/`x-default`) + OpenGraph + Twitter; `app/sitemap.ts` (динамический, из CMS), `app/robots.ts`.
- ISR (`revalidate = 60`) + гранулярная инвалидация по вебхуку (`cms:{type}:{locale}`, detail/home/shell/sitemap tags; `revalidateTag(tag, "max")`, 2-арг форма Next 16).
- Мягкая деградация: при недоступности API списки пустые, страница не падает.
- Доступность: skip-link, `:focus-visible`, `prefers-reduced-motion`, aria-разметка.
- Шрифт Fira Sans с `cyrillic-ext` — таджикские ҳ ҷ ӣ ӯ қ ғ рендерятся тем же начертанием.

### 2.2. Baseline проверок (что я реально запускал)

| Проверка | Команда | Результат |
|---|---|---|
| CMS тесты | `php artisan test` | **FAIL — 338 тестов, 320 passed, 11 failed**, 1202 assertions, 116 c |
| Фронт типы | `npx tsc --noEmit` | PASS (0 ошибок) |
| Фронт линт | `npx eslint` | PASS (0 ошибок) |
| Git | `git status` в обоих репо | чисто, всё запушено в `origin/main` |

> В `CMS_AUDIT.md` зафиксировано «PASS: 309 tests». Сейчас тестов 338 и 11 из них красные — **регрессия появилась после аудита**, вместе с фичей ревалидации фронта. Первая же задача плана — вернуть CI в зелёное.

---

## 3. Реестр найденных проблем

### P0 — блокеры (чинить первыми)

**P0-1. Тестовый прогон CMS делает реальные HTTP-запросы и роняет 10 тестов.**
`WorkflowService::publish()` диспатчит `RevalidateFrontend`; в тестах `QUEUE_CONNECTION=sync`, поэтому джоба выполняется inline, `Http::post()` идёт на реальный `http://localhost:3000/api/revalidate` из `.env`, соединение падает, `->throw()` выбрасывает `ConnectException` → CMS отдаёт **500** вместо 302/201.
Затронуты: `AnnouncementTest`, `ApprovalTest`, `MediaVisibilityTest`, `SchedulerTest` (×2), `WorkflowTest` (×3) и др. — 30 cURL-ошибок за прогон.
Побочный эффект: любой контур, где очередь настроена как `sync`, будет ронять публикацию 500-й ошибкой, если фронт недоступен.

**P0-2. Сортировка объявлений сравнивает таймзоны неправильно.**
`Announcement::scopeOrdered()` использует `orderByRaw('(deadline IS NULL OR deadline >= CURRENT_DATE) DESC')`. `CURRENT_DATE` в SQLite — **UTC-дата без времени**, а `deadline` хранится в `Asia/Dushanbe` (+5) как полный datetime. Сравнение строковое: `'2026-07-26 00:06:50' >= '2026-07-26'` → **true**, хотя срок истёк.
Проверено на живой БД: `now()` приложения = `2026-07-27 00:06`, `CURRENT_DATE` SQLite = `2026-07-26`.
Следствие: **ежедневно с 19:00 до 24:00 по Душанбе** просроченные объявления поднимаются в начало публичного списка — при этом `open` в ресурсе считается на PHP (Carbon, корректно) и рисует «Приём закрыт». Пользователь видит закрытую вакансию первой. На MySQL проблема того же класса (`CURRENT_DATE` берёт таймзону сессии сервера).
Красный тест: `AnnouncementApiTest::it returns only publicly visible announcements, open ones first`.

**P0-3. У фронта нет ни одного теста и нет CI.**
`tests/e2e` и `tests/unit` — пустые каталоги, в `package.json` нет тест-раннера, `.github/` отсутствует. Любая правка i18n/SEO/API-слоя проверяется только руками. При объёме в 13 500 строк и трёх локалях это основной источник будущих регрессий.

### P1 — функциональные пробелы

| ID | Проблема | Детали |
|---|---|---|
| P1-1 | **Нет пагинации ни на одном списке фронта** | Все страницы тянут `per_page: 50` и рисуют всё разом. Материал №51 на публичном сайте недостижим в принципе. Затронуты: новости, документы, проекты, объявления, инструкции, поиск. |
| P1-2 | **Фильтр новостей по категории — клиентский** | `NewsList.tsx` фильтрует только те 50 записей, что уже пришли. Серверный `?category=` в API есть (`NewsController`), `GET /categories` тоже — не используются. |
| P1-3 | **Нет страницы объявления** | API отдаёт `GET /announcements/{slug}`, ресурс возвращает `slug` и `application_url` — на фронте нет ни маршрута `/announcements/[slug]`, ни кнопки подачи заявки. `application_url` не упоминается в коде фронта ни разу. |
| P1-4 | **`next/image` не используется нигде** | 10 сырых `<img>` (обложки новостей/проектов, логотипы, символы). `next.config.ts` уже содержит `remotePatterns` — оптимизация просто не подключена. Бьёт по LCP и трафику (актуально для мобильного интернета в регионах). |
| P1-5 | **404 на `/tj` и `/en` — по-русски** | Известное ограничение Next: `not-found` под динамическим `[locale]` пре-рендерится с дефолтной локалью. Инлайн-вариант с `headers()` уже пробовали — давал прод-500 в `projects/[slug]`. Нужен `global-not-found` либо per-route `not-found.tsx`. |
| P1-6 | **Публичный API без общего rate limit** | Троттлинг стоит только на `search` (60/мин) и `submissions` (10/мин). Остальные 23 GET-маршрута ничем не ограничены. |
| P1-7 | **Нет OpenAPI и contract-тестов** | Фронт и CMS связаны рукописными TypeScript-интерфейсами в `lib/api.ts` (760 строк). Расхождение контракта обнаружится только в рантайме. |

### P2 — контент и данные

| ID | Проблема | Детали |
|---|---|---|
| P2-1 | **5 страниц захардкожены в коде** | `leadership`, `structure`, `symbols`, `sos`, `sitemap` живут в `content.ts` с ручными переводами ru/tj/en. Смена председателя = релиз фронта. Нужно решение: перенести в CMS (сущность или `Page`) либо осознанно оставить статикой. |
| P2-2 | **Мёртвые демо-фолбэки** | `getGuide/getArticle/getProjectContent`, `alerts/content.ts` целиком, массивы `posts`/`items`/`priority`/`catalog` — не импортируются никем (данные идут из CMS). ~1500 строк мусора, который путает при чтении кода. |
| P2-3 | **EN-контент в БД отсутствует** | Поиск на `/en` часто пуст, часть настроек не переведена. `NewsController::show` намеренно отдаёт 404 без перевода заголовка. Это редакторская, а не программная задача — но нужен список того, что обязано быть переведено до запуска. |
| P2-4 | **Слаги алертов могут быть `null`** | Миграция `add_slug_to_alerts_table` пришла после первого сида. Защита в `generateStaticParams` есть, но при пересеве проверять. Бэкофилл: `php artisan tinker --execute 'App\Models\Alert::withTrashed()->get()->each->save();'` |

### P3 — техдолг и качество

| ID | Проблема |
|---|---|
| P3-1 | Фронт: README — дефолтный от `create-next-app`, `docs/` пуст. |
| P3-2 | Фронт: в репозитории лежат `package-lock.json`, `pnpm-lock.yaml` **и** `pnpm-workspace.yaml` одновременно — неопределённость менеджера пакетов. В CMS вдобавок `yarn.lock`. |
| P3-3 | CMS: dev-БД — SQLite, prod — MySQL. Класс багов P0-2 (SQL-функции дат) на SQLite не ловится. |
| P3-4 | CMS: нет кэша для `settings`/`menu`/`home`/`regions` — они дёргают БД на каждый ISR-реген. |
| P3-5 | CMS: крупные компоненты `alerts/wizard.tsx`, `RichEditor.tsx`, `projects/form.tsx` — предупреждения о размере чанка при сборке. |
| P3-6 | CMS: нет DB-constraint на `parent_id` для `Page`/`MenuItem` (защита только в приложении). |
| P3-7 | Обе базы: нет E2E/браузерных тестов форм, медиапикера, меню, workflow. |
| P3-8 | Инфраструктура (из `CMS_AUDIT.md`, не закрыто): backup/restore, постоянные queue worker и scheduler, мониторинг `/health` `/ready`, dependency audit в доверенном CI, ручная приёмка ролей, security review. |

---

## 4. Этап 0 — развернуть окружение на рабочем ПК (Windows + Laragon)

> На macOS среда — `lerd` (Podman) с доменом `https://khf-site-cms.test`. **На рабочем ПК её не будет.** Ниже — путь через `php artisan serve`, он не требует vhost и TLS. `.env.example` фронта уже рассчитан на порт `8848`.

### E0-1. Требования

- PHP **8.3+** (в проекте зафиксирован 8.5; `composer.json` требует `^8.3`). Расширения: `pdo_sqlite`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `bcmath`, `ctype`, `json`, `tokenizer`, `xml`, `curl`, `zip`, `intl`.
- Composer 2, Node.js **22 LTS** (версия из CI), Git.
- Проверка: `php -v`, `node -v`, `composer -V`, `git --version`.

### E0-2. Клонирование

```powershell
mkdir C:\projects\khf-site; cd C:\projects\khf-site
git clone https://github.com/aminjon1065/khf-site-cms.git
git clone https://github.com/aminjon1065/khf-site-front.git
```

### E0-3. CMS

```powershell
cd C:\projects\khf-site\khf-site-cms
composer install
copy .env.example .env
php artisan key:generate
```

Правки в `.env` (минимум):

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8848
APP_TIMEZONE=Asia/Dushanbe
APP_LOCALE=ru
APP_FALLBACK_LOCALE=ru

DB_CONNECTION=sqlite          # быстрый старт; переход на MySQL — задача D-4

QUEUE_CONNECTION=sync         # локально без воркера
CACHE_STORE=database
SESSION_DRIVER=database

CORS_ALLOWED_ORIGINS=http://localhost:3000

# Ревалидация фронта. ПУСТО ⇒ вебхук выключен (так и должно быть, пока фронт не поднят).
FRONTEND_REVALIDATION_URL=
FRONTEND_REVALIDATION_SECRET=
```

```powershell
New-Item -ItemType File database\database.sqlite -Force
php artisan migrate --seed        # демо-контент: новости, алерты, документы, проекты
php artisan storage:link
npm ci
npm run build
php artisan serve --port=8848
```

Демо-админ: `admin@khf.tj` / `password` (только локально).

### E0-4. Фронт

```powershell
cd C:\projects\khf-site\khf-site-front
npm ci
copy .env.example .env.local
```

`.env.local`:

```dotenv
API_URL=http://127.0.0.1:8848/api/v1
NEXT_PUBLIC_API_URL=http://127.0.0.1:8848/api/v1
REVALIDATION_SECRET=
NEXT_PUBLIC_SITE_URL=http://localhost:3000
```

> Скрипт `dev` в `package.json` содержит `NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/rootCA.pem"` — это mkcert из macOS-среды, **в PowerShell он не выполнится**. На Windows запускать `npx next dev` напрямую либо сделать скрипт кроссплатформенным (задача A-5).

### E0-5. Smoke-проверка (критерий готовности этапа 0)

```bash
curl "http://127.0.0.1:8848/api/v1/health"                    # {"status":"ok"}
curl "http://127.0.0.1:8848/api/v1/news?locale=ru&per_page=2"
curl "http://127.0.0.1:8848/api/v1/news?locale=tg&per_page=2" # именно tg, не tj
curl "http://127.0.0.1:8848/api/v1/settings?locale=ru"
```

В браузере: `http://localhost:3000/ru`, `/tj`, `/en` — шапка, подвал, меню и новости приходят из CMS; в консоли нет ошибок гидратации. Панель: `http://127.0.0.1:8848/login`.

### E0-6. Включить ревалидацию (после того, как оба сервиса подняты)

```bash
openssl rand -hex 32     # или: php -r "echo bin2hex(random_bytes(32));"
```
Одно и то же значение положить в CMS `.env` → `FRONTEND_REVALIDATION_SECRET` и во фронтовый `.env.local` → `REVALIDATION_SECRET`; в CMS `FRONTEND_REVALIDATION_URL=http://localhost:3000/api/revalidate`. Затем `php artisan config:clear`.
Проверка: опубликовать новость в панели → она появляется на `/ru/news` без перезапуска фронта.

**Статус O-009 (27.07.2026): выполнено.** Webhook передаёт и фронт строго проверяет
`type/id/slug/locales/event/tags`; быстрые одинаковые изменения объединяются
`ShouldBeUnique`, dispatch выполняется `afterCommit`, а сбои 401/5xx/timeout
обрабатываются очередью и не ломают синхронную публикацию. Настройки и меню
инвалидируют только `cms:shell:{locale}`; редакционный контент — только свои
list/detail/home/sitemap tags.

**Статус O-010 (27.07.2026): выполнено.** `HomePageReadModel` формирует home DTO
предсказуемым набором SQL-запросов: active alerts загружаются один раз для
snapshot и карточек, лимиты выполняются в SQL, тяжёлые detail-поля не выбираются.
Query-budget на заполненном наборе — 15 запросов и один `SELECT alerts`;
фактический uncached endpoint на текущей БД улучшен с 15 до 14 запросов.

---

## 5. Дорожная карта

Порядок обязателен: A → B → C → D → E → F. Внутри этапа задачи можно делать в любом порядке, но `A-1` и `A-2` — первыми в проекте.

### Этап A. Вернуть зелёный CI и защититься от регрессий

---

**A-1. Изолировать тесты CMS от внешней сети** *(P0-1, ~1 ч)*

- **Файлы:** `tests/TestCase.php` или `tests/Pest.php`, `phpunit.xml`, `app/Jobs/RevalidateFrontend.php`, новый `tests/Feature/Cms/RevalidationTest.php`
- **Шаги:**
  1. В `phpunit.xml` добавить `<env name="FRONTEND_REVALIDATION_URL" value=""/>` и `<env name="FRONTEND_REVALIDATION_SECRET" value=""/>` — тесты перестают зависеть от локального `.env`.
  2. В базовом `TestCase`/`Pest.php` в `beforeEach` вызвать `Http::preventStrayRequests()` и `Http::fake()`, чтобы ни один тест не мог уйти в сеть незаметно.
  3. Написать `RevalidationTest`: при заданных url+secret публикация новости отправляет ровно один POST с заголовком `Authorization: Bearer <secret>` и телом `{"tag":"cms"}` (`Http::assertSent`); при пустом url — не отправляет ничего.
  4. Рассмотреть `rescue()`/`failed()` вокруг `->throw()` в джобе, чтобы недоступность фронта не превращалась в 500 при `QUEUE_CONNECTION=sync`. Ретраи (`tries=3`, `backoff`) сохранить.
- **Критерий приёмки:** `php artisan test` не содержит ни одной `cURL error`; 10 ранее падавших тестов зелёные; новый тест покрывает обе ветки.
- **Проверка:** `php artisan test --compact`

---

**A-2. Починить сравнение дат в сортировке объявлений** *(P0-2, ~1 ч)*

- **Файлы:** `app/Models/Announcement.php` (`scopeOrdered`), `tests/Feature/Api/AnnouncementApiTest.php`
- **Шаги:**
  1. Заменить `CURRENT_DATE` на биндинг из PHP: `orderByRaw('(deadline IS NULL OR deadline >= ?) DESC', [now()->startOfDay()])` — одна таймзона (`Asia/Dushanbe`) и на SQLite, и на MySQL.
  2. Проверить, нет ли `CURRENT_DATE`/`NOW()`/`CURDATE()` в других scope: `grep -rn "CURRENT_DATE\|CURDATE\|NOW()" app/`.
  3. Добавить регрессионный тест с `Carbon::setTestNow()` на 21:00 по Душанбе (окно, где баг воспроизводится): просроченное объявление обязано идти после открытого и иметь `open=false`.
- **Критерий приёмки:** `AnnouncementApiTest` зелёный в любое время суток; `open` в ресурсе и порядок в SQL согласованы.
- **Проверка:** `php artisan test --compact --filter=Announcement`

---

**A-3. Тест-раннер и первые тесты фронта** *(P0-3, ~4 ч)*

- **Файлы:** `package.json`, `vitest.config.ts`, `playwright.config.ts`, `tests/unit/*`, `tests/e2e/*`
- **Шаги:**
  1. Vitest + `@testing-library/react` для юнитов; Playwright — для e2e (каталоги уже созданы).
  2. Юнит-тесты (чистые функции — быстрая и ценная страховка):
     - `lib/i18n/config.ts`: `toApiLocale('tj') === 'tg'`, `htmlLang`, `toLocale` на мусоре, `stripLocale`/`withLocale`.
     - `lib/seo.ts`: `buildAlternates` даёт ключи `ru`/`tg`/`en`/`x-default` и правильный canonical.
     - `lib/api.ts`: `buildUrl` конвертит локаль и выбрасывает пустые параметры; `fetchNews` при 500 отдаёт пустой результат, `fetchNewsItem` при 404 → `null`, а при 500 — **пробрасывает** ошибку (это осознанное поведение, его легко сломать).
     - структурная эквивалентность словарей `ru`/`tj`/`en` (одинаковый набор ключей).
  3. E2E-smoke: `/ru`, `/tj`, `/en` отдают 200 и правильный `<html lang>` (`ru`/`tg`/`en`); редирект `/news` → `/{locale}/news`; переключатель языка сохраняет путь; поиск ведёт на `/{locale}/search?q=`; 404 на несуществующем slug.
  4. Скрипты: `"test": "vitest run"`, `"test:e2e": "playwright test"`.
- **Критерий приёмки:** `npm test` и `npm run test:e2e` зелёные локально; e2e поднимает dev-сервер сам (`webServer` в конфиге).
- **Проверка:** `npm test && npm run test:e2e`

---

**A-4. CI для фронта** *(P0-3, ~1 ч)*

- **Файлы:** `.github/workflows/ci.yml` (новый, в `khf-site-front`)
- **Шаги:** Node 22, `npm ci`, `npx tsc --noEmit`, `npx eslint`, `npm test`, `npm run build`, затем `npm run test:e2e` (с моком API или поднятой CMS — на первом шаге допустимо гонять e2e только на смоуке главной). Pin actions по SHA — как сделано в CI CMS.
- **Критерий приёмки:** workflow зелёный на push в `main` и на PR.

---

**A-5. Кроссплатформенный `npm run dev`** *(P3-2, ~15 мин)*

- **Файлы:** `khf-site-front/package.json`
- **Шаги:** убрать из скрипта `dev` шелл-подстановку `$(mkcert -CAROOT)`. Оставить `"dev": "next dev"`, а mkcert-вариант вынести в отдельный `"dev:mkcert"` (нужен только для macOS/lerd с https-доменом CMS).
- **Критерий приёмки:** `npm run dev` работает в PowerShell и в zsh.

---

**A-6. Один менеджер пакетов** *(P3-2, ~30 мин)*

- **Шаги:** выбрать npm (его использует CI обоих репозиториев). Во фронте удалить `pnpm-lock.yaml` и `pnpm-workspace.yaml`, оставить `package-lock.json`. В CMS удалить `yarn.lock` и `pnpm-lock.yaml`, оставить `package-lock.json` (сгенерировать `npm install --package-lock-only`, если его нет). Зафиксировать выбор в README.
- **Критерий приёмки:** в каждом репозитории ровно один lock-файл; `npm ci` проходит с нуля.

---

**A-7. README и docs фронта** *(P3-1, ~1 ч)*

- **Файлы:** `khf-site-front/README.md`, `khf-site-front/docs/`
- **Содержание:** запуск (Windows/Laragon и macOS/lerd), переменные окружения, контракт локалей `tj↔tg` с явным предупреждением про `?locale=tg` при отладке, схема данных (какая страница из какого эндпоинта), как работает ревалидация, где что лежит.
- **Критерий приёмки:** новый разработчик поднимает фронт по README без вопросов.

---

### Этап B. Функциональные пробелы фронта

---

**B-1. Пагинация списков** *(P1-1, ~5 ч)*

- **Файлы:** `app/[locale]/news/page.tsx` + `NewsList.tsx`, `documents/`, `projects/`, `announcements/`, `guides/`, `search/`, `lib/api.ts`, новый `components/public/Pagination.tsx`
- **Шаги:**
  1. Общий серверный компонент пагинации: читает `?page=` из `searchParams`, рисует «Назад/Вперёд» + номера, использует `meta.last_page` из API. Ссылки — обычные `<Link>` (работает без JS, важно для доступности и SEO).
  2. Списочные `fetch*` в `lib/api.ts` должны возвращать `Paginated<T>` целиком (сейчас часть функций отбрасывает `meta` и возвращает голый массив — `fetchInstructions`, `fetchDocuments`, `fetchProjects`, `fetchAnnouncements`). Менять аккуратно, типы проверит `tsc`.
  3. Разумный `per_page` (12–20) вместо 50 везде, где включена пагинация.
  4. `generateMetadata` для страниц > 1: canonical на текущую страницу, плюс `rel=prev/next`, чтобы не плодить дубли в индексе.
- **Критерий приёмки:** при 60 новостях в БД доступны все; страница 999 отдаёт пустое состояние, а не 500; e2e-тест на переход на вторую страницу.

---

**B-2. Серверный фильтр новостей по категории** *(P1-2, ~2 ч)*

- **Шаги:** `fetchCategories()` в `lib/api.ts` (`GET /categories`); список категорий рендерится на сервере из справочника, а не из выборки; выбор категории меняет URL (`?category=slug`), страница перезапрашивает данные с `?category=`; сбрасывать `page` при смене фильтра. Совместить с B-1.
- **Критерий приёмки:** фильтр находит материалы за пределами первой страницы; выбранная категория переживает перезагрузку и шарится ссылкой.

---

**B-3. Страница объявления `/announcements/[slug]`** *(P1-3, ~3 ч)*

- **Шаги:** `fetchAnnouncement(slug, locale)` (эндпоинт уже есть); страница по образцу `projects/[slug]`: заголовок, организация, тип (вакансия/тендер), срок и его состояние, тело, кнопка «Подать заявку» по `application_url` (только `http(s)`, `rel="noopener noreferrer"`, внешние ссылки помечать); `generateStaticParams` + `generateMetadata` через `buildMetadata`; `notFound()` на 404; карточки в списке ведут на деталь.
- **Критерий приёмки:** маршрут работает на трёх локалях; объявление без `slug` не роняет `generateStaticParams` (фильтровать, как сделано для алертов).

---

**B-4. `next/image` для медиа CMS** *(P1-4, ~3 ч)*

- **Шаги:** перевести обложки (главная, `news/[slug]`, `projects/[slug]`, карточки списков) на `next/image` с `sizes` и `priority` для LCP-изображения главной. Локальные ассеты (`/assets/logo-*.webp`) — тоже. **Не трогать HTML из `article-prose`**: картинки внутри rich-text приходят готовым HTML со своим `srcset`, там `next/image` неприменим. Проверить `remotePatterns` под адрес CMS рабочего ПК (`127.0.0.1` уже разрешён).
- **Критерий приёмки:** нет предупреждений `next/image` в консоли; LCP главной на «Fast 3G» не хуже, чем было; битая картинка не ломает вёрстку.

---

**B-5. Локализованная страница 404** *(P1-5, ~2 ч)*

- **Шаги:** реализовать через `app/global-not-found.tsx` (Next 16 добавил его именно под этот кейс — см. `node_modules/next/dist/docs/`). **Не возвращаться** к инлайновому `NotFoundView` с `headers()`: он уже давал прод-500 в `projects/[slug]` (Dynamic server usage при статик-пре-рендере). Обязательно проверить сборкой (`next build`), а не только dev-режимом.
- **Критерий приёмки:** `/tj/nonexistent` и `/en/nonexistent` показывают текст на своём языке, отдают 404 и `noindex`; `next build` проходит; `projects/[slug]` с несуществующим slug не даёт 500.

---

**B-6. Ревизия мягкой деградации** *(~2 ч)*

- **Шаги:** погасить CMS и пройти все 20 маршрутов × 3 локали. Списки — пустое состояние с человеческим текстом, детальные — понятная ошибка (а не белый экран), шапка/подвал — статический фолбэк (уже реализован). Зафиксировать поведение в e2e-тесте с замоканным падающим API.
- **Критерий приёмки:** ни одного необработанного исключения; ни одной страницы с пустым `<main>` без объяснения.

---

### Этап C. Контент: убрать хардкод

---

**C-1. Решение по пяти статическим страницам** *(P2-1, ~1 ч на решение + 4–8 ч на реализацию)*

- **Шаги:** для `leadership`, `structure`, `symbols`, `sos`, `sitemap` принять и **записать в `DECISIONS`** одно из:
  - **(а)** перенести в CMS как обычные `Page` (тело — rich HTML, уже поддержано `pages/[slug]`) — дёшево, но теряется специфичная вёрстка карточек руководства;
  - **(б)** завести отдельные сущности (`Leader`, `StructureUnit`) — дороже, зато редактор меняет состав руководства без релиза;
  - **(в)** оставить статикой — допустимо для `symbols` (государственные символы неизменны) и `sitemap` (генерируется из маршрутов).
- **Рекомендация:** `leadership` и `structure` → CMS (меняются при кадровых перестановках); `symbols`, `sos`, `sitemap` → оставить в коде.
- **Критерий приёмки:** решение записано с обоснованием; для выбранных страниц заведены задачи с оценкой.

---

**C-2. Удалить мёртвые демо-фолбэки** *(P2-2, ~2 ч)*

- **Шаги:** убедиться через `grep`, что символ не импортируется, и удалить: `getGuide`/`getArticle`/`getProjectContent` и их массивы, `app/[locale]/alerts/content.ts`, `priority`/`catalog` в guides, `posts`/`items`. Презентационные мапы (`projectStatusColors`, tone/icon) **оставить** — они используются.
- **Критерий приёмки:** `tsc`, `eslint`, `next build` и тесты зелёные; в `git diff` только удаления.

---

**C-3. Аудит переводов перед запуском** *(P2-3, ~2 ч + редакторская работа)*

- **Шаги:** артисан-команда (или тест), которая отчитывается о пробелах: сколько опубликованных материалов каждого типа не имеют `tg`/`en`-заголовка; какие ключи `Setting` не переведены. Плюс список того, что **обязано** быть переведено к запуску (меню, настройки организации, инструкции населению, экстренные контакты). Результат — редактору, не разработчику.
- **Критерий приёмки:** команда `php artisan content:translation-report` печатает таблицу пробелов по локалям.
- **Помнить:** после правки настроек фронту нужен пересбор (`next build`), иначе ISR отдаёт старый снапшот.

---

### Этап D. Бэкенд: готовность к production

---

**D-1. Общий rate limit публичного API** *(P1-6, ~1 ч)*

- **Шаги:** именованный лимитер в `AppServiceProvider` (напр. 120 запросов/мин на IP для GET), навесить на группу `api`; для `search` и `submissions` оставить более строгие. Проверить, что ISR-реген фронта (один серверный IP, всплески при инвалидации) в лимит укладывается — иначе выделить allowlist по IP или поднять порог.
- **Критерий приёмки:** тест на 429 при превышении; заголовки `X-RateLimit-*` отдаются; фронт не ловит 429 при массовой ревалидации.

---

**D-2. Кэш публичных справочников** *(P3-4, ~3 ч)*

- **Шаги:** `Cache::remember` для `settings`, `menu`, `home`, `regions`, `categories` с ключом, включающим локаль; сброс при сохранении соответствующих моделей (там же, где диспатчится `RevalidateFrontend`). ETag уже есть — это дополняющий слой, не замена.
- **Критерий приёмки:** тест «после изменения настройки ответ `/settings` меняется на следующем запросе»; в логе видно снижение числа запросов к БД.

---

**D-3. OpenAPI + contract-тесты — выполнено 28.07.2026** *(P1-7, ~5 ч)*

- **Шаги:** описать `api/v1` (OpenAPI 3.1) — руками или генератором; тест, проверяющий, что ответы контроллеров соответствуют схеме; во фронте — генерация типов из спеки, чтобы `lib/api.ts` перестал быть рукописным дублем.
- **Критерий приёмки:** спека в репозитории, contract-тест в `composer ci:check`, типы фронта генерируются командой.

---

**D-4. MySQL локально (паритет с production)** *(P3-3, ~2 ч)*

- **Шаги:** поднять MySQL 8 из Laragon, создать `khf_site_cms`, переключить `.env`, `php artisan migrate:fresh --seed`. Прогнать полный тест-сьют на MySQL (добавить в CI матрицу sqlite + mysql). Именно так ловятся баги класса P0-2.
- **Критерий приёмки:** тесты зелёные на обоих драйверах; в CI есть job с MySQL.

---

**D-5. Очередь и планировщик локально** *(~1 ч)*

- **Шаги:** `QUEUE_CONNECTION=database`, запустить `php artisan queue:work` и `php artisan schedule:work` в отдельных окнах. Проверить: `content:process-scheduled` публикует отложенную новость; истёкший алерт автозавершается; `GET /api/v1/ready` отдаёт `ready` (heartbeat планировщика); вебхук ревалидации доходит.
- **Критерий приёмки:** пункты из §5 `DEPLOYMENT.md` проходят локально.

---

**D-6. Остаточный P2 из `CMS_AUDIT.md`** *(по мере сил)*

Перевести оставшиеся хардкод-URL на Wayfinder; расширить dashboard на все workflow-типы; DB-constraints на `parent_id` (`Page`, `MenuItem`); разбить `alerts/wizard.tsx`, `RichEditor.tsx`, `projects/form.tsx` и подгружать `RichEditor` динамически; полнотекстовый поиск при росте объёма данных.

---

### Этап E. Качество: производительность, доступность, SEO

**E-1.** Бюджеты Lighthouse (performance ≥ 90, a11y ≥ 95 на мобильном) для `/ru`, `/ru/news`, `/ru/news/[slug]`, `/ru/map`; прогон `axe` в e2e; фиксация результатов в `docs/`.
**E-2.** Профиль сборки: размеры чанков, `d3-geo`/`topojson` только на карте (динамический импорт), `next/font` уже настроен.
**E-3.** JSON-LD: `Organization` (глобально), `NewsArticle` (новость), `BreadcrumbList` (все внутренние). Проверить `sitemap.xml`/`robots.txt`/hreflang валидаторами.
**E-4.** Гранулярный контракт и invalidation покрыты Pest/Vitest (O-009); на staging остаётся измерить publish → fresh page < 5 s при реальном queue worker.

### Этап F. Развёртывание и приёмка

**F-1.** Staging строго по `DEPLOYMENT.md` (MySQL, nginx, systemd/supervisor для `queue:work`, cron `schedule:run`, `storage:link`, HTTPS).
**F-2.** Чек-лист безопасности из `DEPLOYMENT.md` §6 + `composer audit` и `npm audit` в доверенном CI.
**F-3.** Ручная приёмка: матрица ролей, 2FA + recovery-коды, публикация критического предупреждения от и до, desktop/mobile, три локали.
**F-4.** Backup/restore с контрольным восстановлением; мониторинг `/api/v1/health` и `/ready`; алертинг дежурной смены.
**F-5.** Закрыть чек-лист «Критерии production release» в `CMS_AUDIT.md`.

---

## 6. Definition of Done

Задача считается сделанной, только когда:

1. **CMS:** `vendor/bin/pint --dirty` → `php artisan test --compact` (весь сьют, не только фильтр) → `composer types:check`. Каждое изменение поведения покрыто тестом (это требование `CLAUDE.md` проекта).
2. **Фронт:** `npx tsc --noEmit` → `npx eslint` → `npm test` → `npm run build`. Правки маршрутов/метаданных обязаны проверяться именно **сборкой**: часть ошибок Next (динамика при пре-рендере) в dev-режиме не проявляется.
3. Ручная проверка на трёх локалях, если менялся публичный UI.
4. Отдельный коммит на задачу, в сообщении — ID задачи (`A-2: fix announcement ordering timezone`).
5. Запись в журнале прогресса: что сделано, что проверено, какие решения приняты.

---

## 7. Подводные камни (проверено на практике)

| Гоча | Что делать |
|---|---|
| `?locale=tj` в API молча превращается в `ru` | `ResolveApiLocale::SUPPORTED = ['tg','ru','en']`. При отладке `curl` — только `locale=tg`. Не «чинить» это в CMS: решение о split принято осознанно. |
| Правка `Setting` не видна на фронте | ISR отдаёт старый снапшот. Нужен `next build` либо рабочий вебхук ревалидации. |
| Правка `config/purifier.php` не применяется | Бампнуть `custom_definition.rev`, иначе берётся кэш схемы. |
| Алерт с `slug = null` роняет весь маршрут | `generateStaticParams` фильтрует; при пересеве — бэкофилл `Alert::withTrashed()->get()->each->save()`. |
| Меню из CMS на `/en` пустое, на `/tj` русское | Подписи известных маршрутов берутся из словаря фронта (`navLabelByUrl`/`footerLabelByUrl`), из CMS — только состав и порядок. Гибрид намеренный, не «упрощать». |
| CMS-команды на macOS | Только через `lerd console <artisan-cmd>` (рантайм в контейнере). На Windows — обычный `php artisan`. |
| `.env`/`.env.local`/`database.sqlite`/`storage/app/public` не в git | На новой машине создаются заново; медиа и БД не переносятся. |

---

## 8. Оценка объёма

| Этап | Задач | Ориентир |
|---|---|---|
| 0 — окружение | 6 | 2–4 ч |
| A — CI и тесты | 7 | 8–10 ч |
| B — фронт-функционал | 6 | 15–18 ч |
| C — контент | 3 | 8–14 ч |
| D — бэкенд | 6 | 12–15 ч |
| E — качество | 4 | 8–10 ч |
| F — деплой | 5 | зависит от инфраструктуры |

Этапы 0 + A + B дают публично годный сайт без известных функциональных дыр. C + D + E — готовность к сдаче. F — внешняя инфраструктура, в одиночку в репозитории не закрывается.

**Статус O-011 (27.07.2026): выполнено.** Тяжёлые Inertia shared props вынесены
из каждого перехода: пользователь/permissions и sidebar badges передаются как
`once` props, список уведомлений загружается только при открытии drawer через
partial reload, а approval counts выполняются агрегатами в SQL. Полный
`composer ci:check`: 368 тестов / 1395 assertions; TypeScript, ESLint, Prettier,
Pint и PHPStan зелёные.

**Статус O-012 (28.07.2026): выполнено.** Девять основных query shapes
проверены через `EXPLAIN ANALYZE` на MySQL 8.4 и 345 000 production-like строках.
Добавлены только доказанные composite indexes для публичного меню (−73%) и
очереди обращений (−99%); четыре неиспользуемых кандидата отклонены. GitHub
Actions теперь прогоняет полный Pest suite также на MySQL 8. Локально оба
драйвера проходят 369 тестов / 1399 assertions.

**Статус O-013 (28.07.2026): выполнено.** Кэш и очередь используют Redis с
durable database fallback; latency-sensitive, notification, revalidation,
default и media jobs разделены. Scheduler контролирует backlog и ставит worker
heartbeat, `/ready` выявляет остановленный worker и показывает failed jobs.
Тест отказа Redis доказывает fallback без потери cache/job. Полный
`composer ci:check`: 374 теста / 1426 assertions.

**Статус O-014 (28.07.2026): выполнено.** Шесть редакционных форм переведены
на общий `EditorialFormShell`: единые header, локали, error summary, sticky
actions, Ctrl/Cmd+S и защита несохранённых данных; навигация и submit используют
Wayfinder. Браузерная проверка подтвердила dirty-state confirm, мобильные actions
высотой 44 px и отсутствие console errors. Полный `composer ci:check`: 381 тест /
1511 assertions; TypeScript, ESLint, Prettier, Pint и PHPStan зелёные.

**Статус O-015 (28.07.2026): выполнено.** Все шесть редакционных форм получили
debounced autosave, offline/local recovery, понятный save-state, защиту от двух
вкладок и stale normal save. Immutable revision snapshots доступны в общей
истории и восстанавливают редакционные поля без изменения workflow-статуса или
бинарных media. 13 новых Pest-тестов проверяют autosave/conflict/force/policy/
restore; полный `composer ci:check`: 394 теста / 1563 assertions, Vite build,
TypeScript, ESLint, Prettier, Pint и PHPStan зелёные.

**Статус O-016 (28.07.2026): выполнено.** Общий preview во всех шести
редакционных формах показывает несохранённые данные в трёх локалях, desktop,
mobile и share/OG режимах и явно обозначает fallback. Отдельный private signed
URL ограничен авторизацией, policy и TTL, не кэшируется и не индексируется.
Серверный publication checklist блокирует публикацию при неполных обязательных
переводах, пустом alt обложки, unsafe-ссылках и незавершённых media conversions;
SEO остаётся понятным warning. Полный `composer ci:check`: 405 тестов /
1637 assertions; Vite build, TypeScript, ESLint, Prettier, Pint и PHPStan зелёные.

**Статус O-017 (28.07.2026): выполнено.** Media UX поддерживает focal point,
alt либо явный decorative-флаг, поиск по metadata и «Где используется» для
структурных копий и прямых rich-text URL. Focal point переносится из библиотеки
в News/Project/Instruction и применяется публичным frontend. Используемые файлы
защищены от удаления; свободные assets уходят в фильтруемую корзину и
восстанавливаются без потери оригинала или derivatives. Полный
`composer ci:check`: 411 тестов / 1706 assertions; Vite build, TypeScript,
ESLint, Prettier, Pint и PHPStan зелёные.

**Статус O-018 (28.07.2026): выполнено.** OpenAPI 3.1 фиксирует все 25
публичные операции `api/v1`; контрактный Pest-тест сверяет schema с реальным
route registry и рекурсивно валидирует каждый успешный ответ. Frontend
генерирует 54 TypeScript-типа из синхронизируемого schema snapshot, а
`api:types:check` включён в CI и не допускает drift. Ручные DTO удалены из
`lib/api.ts`; выявленные контрактом nullable category и обязательные
`emergency_contacts` обработаны явно. Полный `composer ci:check`: 413 тестов /
2016 assertions; frontend TypeScript, ESLint, 36 Vitest-тестов и production
build на 54 страницы зелёные.

**Статус O-019 (28.07.2026): выполнено.** Next.js 16 собирает реальные
LCP/INP/CLS отдельным `useReportWebVitals` client island и через same-origin
proxy передаёт в CMS только metric id/value, нормализуемый pathname, locale,
device class и navigation type. IP, user-agent и session ID не сохраняются.
CMS защищает ingestion server-only secret и rate limit, дедуплицирует retry,
автоматически удаляет samples старше 35 дней и оконными SQL-функциями считает
точный p75 за 28 дней по метрике, маршруту и устройству. Доступный dashboard
добавлен в «Центр контроля». Измеримость выросла с 0 RUM-метрик до трёх CWV;
добавленный client chunk — 9505 bytes / 3667 bytes gzip. Полный
`composer ci:check`: 420 тестов / 2076 assertions; frontend: 41 Vitest,
19 Chromium/axe, TypeScript, ESLint и production build на 55 страниц.

**Статус O-020 (28.07.2026): инженерный контур готов, полевая приёмка
ожидается.** В CMS добавлен доступный защищённый раздел для анонимной записи
usability-сессий: восемь заданий (семь обязательных и повторная новость), время,
помощь, завершение и необратимые ошибки, десять ответов SUS. Отчёт считает
стандартный SUS, nearest-rank p75 и единый gate по всем целям UX-10; схема не
содержит ФИО, e-mail, телефона, IP или user-agent. 8 целевых Pest-тестов /
59 assertions и полный `composer ci:check` — 428 тестов / 2135 assertions;
Vite production build, TypeScript, ESLint, Prettier, Pint и PHPStan зелёные.
Изолированный browser flow подтвердил вход, сохранение синтетической сессии,
пересчёт отчёта, отсутствие console errors и 49/49 touch targets высотой не
менее 44 px на desktop и viewport 390×844.
Полевых результатов пока 0/5, поэтому O-020 и Definition of Done намеренно не
отмечены выполненными до сессий с реальными будущими редакторами.
