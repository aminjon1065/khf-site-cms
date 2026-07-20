# План реализации CMS — официальный сайт КЧС Республики Таджикистан

> Документ подготовлен по итогам полного аудита двух репозиториев проекта.
> Backend / CMS: `C:/laragon/www/khf-site-cms` — Laravel 13 · Inertia v3 · React 19 · PHP 8.5.
> Публичный сайт: `C:/codes/khf-site-front` — Next.js 16 (App Router) · React 19 · Tailwind v4.
> Языки контента: **tg / ru / en** (канонический — `ru`).

---

## 1. Текущее состояние проекта

Проект — **не пустой макет**, а серьёзная незавершённая реализация. «Хребет» предметной области и редакционного процесса выполнен на production-уровне; отсутствует «поверхность доставки» (публичный API) и большинство административных экранов CRUD.

### 1.1. Backend (CMS) — что готово и надёжно
- **Слой данных практически завершён.** 17 моделей Eloquent с JSON-переводами (`spatie/laravel-translatable`), 18 миграций, все pivot/morph-связи, soft delete на 4 workflow-моделях (`alerts`, `news`, `instructions`, `documents`).
- **Исчерпывающие enum'ы**, общие с UI: `ContentStatus` (11 статусов), `RoleName` (9 ролей), `Severity`, `HazardType`, `Channel`, `DocType`, `RegionType`, `Module`, `PermissionAction`.
- **Движок редакционного workflow** — `App\Services\WorkflowService`: конечный автомат из 11 статусов, запись каждого перехода в `workflow_transitions`, журнал действий с флагом `is_critical`, уведомления `WorkflowNotification`, защита критической публикации `guardCriticalPublish`.
- **Планировщик** — `Console\Commands\ProcessScheduledContent` каждые 5 минут публикует запланированное, завершает истёкшие предупреждения, рассылает уведомления об истечении.
- **RBAC** — `app/Support/PermissionMatrix.php` как единый источник правды (9 ролей × 8 модулей × 6 действий), потребляется сидером, `ModulePolicy` и (ещё не построенным) экраном ролей. Superadmin — через `Gate::before`.
- **Модуль «Предупреждения» (Alerts) полностью реализован** (контроллер + маршруты + `pages/alerts/index.tsx` + `pages/alerts/wizard.tsx`) — эталонная реализация. Также готовы: **Согласование, Журнал действий, Дашборд, Аутентификация (Fortify), Настройки профиля/безопасности**.
- **Кросс-функциональная инфраструктура:** медиатека (`spatie/laravel-medialibrary`), журнал действий (расширен IP/UA/критичностью), уведомления в БД, сидеры на все таблицы, health-endpoint `/up`.

### 1.2. Backend — что заглушено / сломано
- **`routes/settings.php` — мёртвый код**: файл не подключён ни в `bootstrap/app.php`, ни в `web.php`. Маршруты профиля/безопасности/пароля не работают; `/settings` и `/profile` рендерят общий stub `section`.
- **10 маршрутов сайдбара рендерят только `pages/section.tsx`** (news, instructions, documents, media, home-blocks, users, roles, settings, control) — без контроллера и **без авторизации**.
- **Двухфакторная аутентификация отключена** в `config/fortify.php`; регистрация и подтверждение e-mail выключены.
- **Региональное ограничение (RegionalEditor) — заглушка**: `ModulePolicy::belongsToUserRegion()` всегда возвращает `true`; переопределено только в `AlertPolicy`.

### 1.3. Backend — чего нет
- **Весь публичный API** — нет `routes/api.php`, нет `api:` в `bootstrap/app.php`, нет `config/cors.php`, нет публичных ресурсов. `AlertResource` — только для админки, жёстко привязан к `ru`, утекают внутренние поля.
- **Контент-контроллеры** News, Instructions, Documents, Projects, Announcements, Pages, Regions, Taxonomy, Media, Home blocks, Menu, Settings, Users, Roles — отсутствуют.
- **Обращения граждан** (форма э-приёмной), **подписки**, **поиск по сайту** — ни модели, ни таблицы, ни endpoint'а.
- **CAP-экспорт предупреждений** — поле `channels` есть, сериализации CAP нет.

### 1.4. Frontend (публичный сайт)
- **Слой представления полностью готов** — все маршруты из `lib/routes.ts` построены; дизайн-система «Industry» в `app/globals.css` со строгой семантической палитрой опасностей.
- **Канонический контракт данных** — `lib/types.ts` (`NewsItem`, `AlertItem`, `ProjectItem`, `DocItem`, `Announcement`, `GuideItem`, `Person`, `RegionStatus`) — это формы DTO, которые обязан отдавать API.
- **Заглушки:** i18n одноязычный (`<html lang="ru">` захардкожен); форма обращений `preventDefault`-only с фиктивным № `КЧС-2026-08412`; подписка и поиск — без обработчиков.
- **Нулевая интеграция с backend** — ни `NEXT_PUBLIC_*`, ни fetch-клиента, ни `.env`. Каждая страница читает свой `content.ts`.

---

## 2. Обнаруженный стек

| Слой | Технология |
|---|---|
| CMS backend | Laravel 13, PHP 8.5, Inertia v3, Fortify, Wayfinder |
| CMS RBAC / медиа / журнал / переводы | spatie: permission, medialibrary, activitylog, translatable |
| CMS UI | React 19 + Inertia, собственный дизайн-кит `resources/js/ui/*`, TipTap, TanStack Table, dnd-kit, Tailwind v4 |
| CMS тесты / качество | Pest v4, Larastan/PHPStan, Pint, ESLint 9, Prettier |
| Публичный сайт | Next.js 16 (App Router), React 19, Tailwind v4, d3-geo/topojson (карта регионов) |
| БД (dev) | SQLite; production — PostgreSQL/MySQL |

---

## 3. Матрица готовности модулей

Легенда: ✅ готово · ⚠️ частично/заглушка · ❌ нет. «Frontend подключён» = страница читает живые данные CMS (сейчас у всех ❌).

| Модуль | Модель | Миграция | Контроллер/маршруты (админ) | Админ-UI | Публичный API | Frontend подключён | Статус |
|---|---|---|---|---|---|---|---|
| **Alerts** | ✅ | ✅ | ✅ CRUD+publish/unpublish/duplicate | ✅ index+wizard | ✅ | ✅ `/alerts`, `/alerts/[slug]`, `/map` | **готово** |
| **News** | ✅ | ✅ | ✅ CRUD+workflow | ✅ index+form | ✅ | ✅ `/news`, `/news/[slug]` | **готово** |
| **Instructions** | ✅ | ✅ | ✅ CRUD+workflow | ✅ index+form (sections) | ✅ | ✅ `/guides`, `/guides/[slug]` | **готово** |
| **Documents** | ✅ | ✅ | ✅ CRUD+workflow+файлы | ✅ index+form (3 языка) | ✅ | ✅ `/documents` | **готово** |
| **Projects** | ✅ (Workflowable) | ✅ | ✅ CRUD+workflow | ✅ index+form (цели/этапы) | ✅ | ✅ `/projects`, `/projects/[slug]` | **готово** |
| **Announcements** | ✅ (Workflowable) | ✅ | ✅ CRUD+workflow | ✅ index+form | ✅ | ✅ `/announcements` | **готово** |
| **Pages** | ✅ (дерево + workflow) | ✅ | ✅ CRUD + workflow | ✅ index + form | ✅ `/pages`, `/pages/{slug}` | ✅ `/pages/[slug]` + подвал | **готово** |
| **Regions/Districts** | ✅ | ✅ (5 рег. + районы) | ✅ CRUD + справочник районов | ✅ index (live-статус) + form | ✅ статус карты + `/regions/directory` | ✅ `/map`, `/alerts`, `/contacts` | **готово** |
| **Taxonomy** | ✅ | ✅ | ✅ менеджер (sync) | ✅ категории + теги | ✅ `/categories` | ✅ фильтр новостей | **готово** |
| **Media** | ✅ (spatie) | ✅ (+ media_assets) | ✅ библиотека (upload/delete) | ✅ галерея | ✅ URL в контент-API | ✅ в материалах | **готово** |
| **Home blocks** | ✅ | ✅ (8 seed) | ✅ менеджер блоков | ✅ home-blocks | ✅ `/api/v1/home` | ✅ главная `/` | **готово** |
| **Menu** | ✅ | ✅ | ✅ sync (main/footer) | ✅ менеджер меню | ✅ `/api/v1/menu` | ✅ подвал сайта | **готово** |
| **Settings** | ✅ | ✅ (seed) | ✅ index+update (whitelist) | ✅ форма настроек | ✅ `/api/v1/settings` | ✅ шапка+подвал | **готово** |
| **Users** | ✅ | ✅ (10 seed) | ✅ CRUD + guards + is_active login | ✅ список + форма | n/a | n/a | **готово** |
| **Roles & permissions** | ✅ | ✅ | n/a | ✅ матрица прав (read-only) | n/a | n/a | **готово** |
| **Workflow/Approvals** | ✅ | ✅ | ✅ | ✅ | n/a | n/a | **готово** |
| **Activity log** | ✅ | ✅ | ✅ + CSV | ✅ | n/a | n/a | **готово** |
| **Dashboard** | — | — | ✅ | ✅ | n/a | n/a | **готово** |
| **Auth (Fortify)** | ✅ | ✅ | ✅ | ✅ | n/a | n/a | частично (2FA off) |
| **Submissions** | ✅ (+comments) | ✅ | ✅ приём+обработка | ✅ index+show | ✅ POST (throttle+honeypot) | ✅ форма контактов | **готово** |
| **Subscriptions** | ❌ | ❌ | ❌ | ❌ | ❌ | ⚠️ | нет (net-new) |
| **Leadership/Structure** | ❌ | ❌ | ❌ | ❌ | ❌ | ⚠️ статич. | нет |
| **Search (публичный)** | ❌ | ❌ | ⚠️ палитра CMS | — | ❌ | ❌ | нет |

**Вывод:** данные и workflow готовы; отсутствуют публичный API и большинство CRUD-экранов. Ключевая блокирующая зависимость проекта — раздел 6 (публичный API).

---

## 4. Предлагаемая архитектура CMS

Сохраняем и продолжаем уже выбранную командой архитектуру (не переписываем):

```
Публичный сайт (Next.js)  ──HTTP/JSON──▶  Публичный API (Laravel, api/v1, read-only)
                                               │
        Админ-панель (Inertia/React)  ─────────┤  общий домен: Models · Enums
                                               │
   presentation  →  application (Controllers/FormRequests)
                 →  domain (Models, Enums, WorkflowService)
                 →  data access (Eloquent + scopes)
                 →  authorization (Policies + PermissionMatrix)
                 →  storage (medialibrary) · logging (activitylog)
```

- **Админка** — Inertia (server-driven), контроллеры `App\Http\Controllers\Cms\*`, авторизация через политики, UI из `resources/js/ui/*`.
- **Публичный API** — `App\Http\Controllers\Api\*` + `App\Http\Resources\Api\*`, префикс `api/v1`, без сессий/CSRF, только опубликованный контент, locale-aware, кэш с инвалидацией по публикации.
- **Бизнес-логика** — только в сервисах/моделях, не в React-компонентах.

---

## 5. Структура базы данных (существующая)

Основные таблицы (все с `timestamps`; workflow-модели с `soft deletes`):

`users`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions`, `media`, `activity_log`,
`regions`, `districts`, `categories`, `tags`, `taggables`,
`alerts` (+ `alert_region`, `alert_district`, `alert_instruction`), `news`, `instructions`, `documents`,
`pages`, `projects`, `announcements`, `home_blocks`, `menu_items`, `settings`,
`workflow_transitions`, `notifications`.

**Переводы** — JSON-колонки на самой модели (`title`, `summary`, `body` …), без отдельных `*_translations`.
**Планирование** — `published_at`, `scheduled_at` (+ `starts_at`/`ends_at`/`expiry_notified_at` у alerts).

Изменения этого спринта:
- **`news.slug`** — добавить уникальный индекс + авто-генерацию slug с суффиксом-коллизией.

Планируемые net-new таблицы (следующие спринты): `submissions`, `submission_comments`, `subscriptions`, `redirects`, (опц.) `people`/`org_units`.

---

## 6. Публичный API — ключевой архитектурный элемент (проектное решение)

### 6.1. Подключение и версионирование
`routes/api.php`, зарегистрировать в `bootstrap/app.php` (`api:` + `apiPrefix: 'api/v1'`). Все read-endpoint'ы **без аутентификации, stateless, только чтение**. Записи (обращения/подписки) — отдельно, с rate-limit и валидацией.

### 6.2. Endpoint'ы (соответствуют `lib/types.ts`)
- `GET /api/v1/health`
- `GET /api/v1/news` (`?category=&q=&page=&per_page=`) · `GET /api/v1/news/{slug}`
- (далее) `instructions`, `documents`, `projects`, `announcements`, `alerts`, `alerts/active`, `regions`, `home`, `settings`, `menu`, `search`
- (write) `POST /api/v1/submissions`, `POST /api/v1/subscriptions`
- (preview) `GET /api/v1/preview/{type}/{id}?signature=…` — временная подписанная ссылка (`URL::temporarySignedRoute`, TTL ≤ 30 мин).

### 6.3. DTO / Resources
Новый namespace `App\Http\Resources\Api\` (**не переиспользуем** админские ресурсы). По одному публичному ресурсу на тип. Ресурс отдаёт **ровно** ключи из `lib/types.ts`, **предварительно отформатированные строки дат** («16 июля 2026») и **человекочитаемые размеры файлов** — frontend не форматирует.

### 6.4. Локализация
Middleware `ResolveApiLocale`: `?locale=` → `Accept-Language`, whitelist `tg|ru|en`, default `ru`. Ресурсы: `getTranslation($field, $locale, useFallback: true)` с фолбэком на `ru`.

### 6.5. Только опубликованное
Используем существующие scope: `ContentStatus::isPublic()`, `News::scopePublished()`, `Alert::scopeActive()`. Черновики/на согласовании/запланированное **никогда не утекают**.

### 6.6. CORS и кэш
`config/cors.php`: `paths=['api/*']`, origins из `CORS_ALLOWED_ORIGINS`, `supports_credentials=false`. Кэш `Cache::remember` по ключу `type+locale+filters`, инвалидация при переходе в публичный статус (hook в `WorkflowService`). Next.js — ISR (`revalidate`).

### 6.7. Frontend
`NEXT_PUBLIC_API_URL` + `lib/api.ts` (типизированные фетчеры) как единственный шов, заменяющий `content.ts`. `next.config.ts` → `images.remotePatterns` для media-хоста CMS.

---

## 7. Роли и права (RBAC — реализовано)

9 ролей (`RoleName`): `superadmin`, `admin`, `chief_editor`, `editor`, `alert_operator`, `translator`, `regional_editor`, `approver`, `viewer`.
Права в форме `module.action` (`news.view`, `news.publish`, …), единый источник — `PermissionMatrix`. Проверка **на сервере** через политики (`ModulePolicy` → `{module}.{action}` + региональный scope). Superadmin — `Gate::before`.

Долг безопасности к закрытию до production: реальное региональное ограничение (`belongsToUserRegion`), авторизация на stub-маршрутах, включение 2FA.

---

## 8. План интеграции с публичным frontend

1. Все публичные страницы получают **только опубликованные** данные (scope на каждом endpoint).
2. Черновики — только по подписанному preview-токену.
3. Учитывается язык (`?locale`), scheduled/expiry (через `ProcessScheduledContent`), региональная доступность.
4. Внутренние поля CMS не отдаются (отдельные публичные ресурсы/DTO).
5. Стабильные контракты API = `lib/types.ts`. Инвалидация кэша после публикации. Сохранение SSR/SEO. Состояния loading/empty/404/500.

---

## 9. Этапы реализации (вертикальными срезами)

**Принцип: довести один тип контента насквозь, затем тиражировать.** Канонический — **News** (уже Workflowable, с тегами и трекингом переводов, засеян опубликованными строками, питает больше всего страниц).

- **Фаза 0 — фундамент API** (этот спринт): `routes/api.php`+регистрация, `config/cors.php`, `ResolveApiLocale`, базовый `Api\`-ресурс, `/api/v1/health`, `.env.example` на обеих сторонах, `next.config.ts` media.
- **Фаза 1 — News public API** (этот спринт): `PublicNewsResource`, `Api\NewsController@index/show`, published-scope + фильтры + пагинация + кэш.
- **Фаза 2 — News admin CRUD** (этот спринт): `Cms\NewsController` (по образцу Alerts) + `NewsRequest`, замена stub-маршрута, `pages/news/index.tsx` + `pages/news/form.tsx`, i18n-ключи, Pest-тесты.
- **Фаза 3 — подключение frontend** (этот спринт): `lib/api.ts`, перевод `app/news` на серверный fetch (ISR) с состояниями loading/empty/error, сохранение дизайна.
- **Фаза 4** — тиражирование на Instructions, Documents, Projects, Announcements.
- **Фаза 5** — Alerts public + Regions/Map + Home + Settings + Menu.
- **Фаза 6** — записи и net-new модели (Submissions, Subscriptions, Leadership/Structure).
- **Фаза 7** — hardening: региональный scope, авторизация stub-маршрутов, 2FA, CAP-экспорт, media→S3, redirects/SEO.

После каждой фазы — `pint`, `phpstan`, `pest`, `tsc`, production build.

---

## 10. Риски и технический долг

1. **Региональный scope — заглушка** (`belongsToUserRegion` → `true`). Реальная дыра доступа для госсистемы; закрыть до go-live.
2. **`force: true` на переходах** в контроллерах обходит автомат `WorkflowService::ALLOWED`; `authorizeAndPublish` молча понижает до Review вместо 403. Решить: оставить для UX оператора или ужесточить.
3. **`AlertResource` нельзя отдавать публично** — привязан к `ru`, утекают внутренние поля. Публичные ресурсы — net-new.
4. **Контракт FE — предформатированные даты и размеры**, не ISO/байты. Форматировать на сервере.
5. **Целостность slug** — `news.slug` неуникален; у `Alert` slug нет. Добавить уникальный slug + авто-генерацию.
6. **Media на локальном диске** не отдаётся кросс-хостом — перенести на S3/CDN + `images.remotePatterns`.
7. **Locale env** — `APP_LOCALE=en`; API держит собственный резолвер локали.
8. **Page/Announcement/Project** ставят `status` вне движка workflow — решить по типу.
9. **Две реализации трекинга переводов** — консолидировать.
10. **Безопасность** — слабый seed-пароль, косметический 2FA, отсутствие авторизации на stub-маршрутах — закрыть до production.

---

## 11. Критерии готовности модуля

Модуль готов, только если: есть таблица, миграция, серверная валидация, авторизация, UI, работают create/edit/view/delete (+restore при необходимости), поиск, фильтры, пагинация, состояния loading/empty/error, действия пишутся в журнал, есть тесты критических сценариев, данные подключены к публичному frontend, проходит production build.

---

## 12. Definition of Done проекта

Администратор входит в защищённую панель · роли ограничивают доступ · редактор создаёт материал · переводчик добавляет языковую версию · главный редактор согласует · публикатор публикует · материал появляется на сайте · черновик недоступен публично · работают preview, ревизии, отложенная публикация, медиатека, документы, страницы, меню, главная, предупреждения, обращения · всё журналируется · права проверяются на сервере · формы валидируются · файлы загружаются безопасно · дизайн публичного сайта не потерян · нет mock в production-потоке · нет критических ошибок TS/lint/tests/build · миграции проходят на чистой БД · `.env.example` заполнен · есть инструкция развёртывания.

---

## 13. Объём текущего спринта (этой сессии)

Реализуется вертикальный срез **News** как эталон паттерна (Фазы 0–3) с тестами и полной проверкой:
1. Фундамент публичного API (`api/v1`, CORS, локаль).
2. News public API (list + detail).
3. News admin CRUD + UI.
4. Подключение `app/news` (list + detail) публичного сайта к API.
5. Pest-тесты (CRUD, авторизация, workflow, published-only) + `pint`/`phpstan`/`tsc`/build.

Остальные модули реализуются тем же паттерном в последующих фазах (раздел 9).
