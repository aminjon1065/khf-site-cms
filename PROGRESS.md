# Журнал прогресса — план работ КЧС (khf.tj)

Формат: одна секция на задачу, в порядке выполнения. Не редактируется задним числом — только дописывается.

---

## Этап 0 · Развёртывание окружения и smoke-test — ГОТОВО

- **Сделано:**
  - Созданы ветки `work/plan` от `main` в обоих репозиториях (`khf-site-cms`, `khf-site-front`). `main` не трогали.
  - CMS `.env`: `APP_URL=http://127.0.0.1:8848`, `APP_TIMEZONE=Asia/Dushanbe` (явно, хотя `config/app.php` и так фолбэчит на него), `APP_LOCALE=ru`/`APP_FALLBACK_LOCALE=ru`, `QUEUE_CONNECTION=sync` (для Stage 0 без воркера; на `database` + `queue:work` переключим в D-5), добавлены пустые `FRONTEND_REVALIDATION_URL`/`SECRET` (вебхук выключен до отдельной проверки), `MEDIA_DISK=public`, `MEDIA_PRIVATE_DISK=content_private`.
  - MySQL 8.4 (Laragon, `C:\laragon\bin\mysql\mysql-8.4.3-winx64`) поднят вручную (`mysqld.exe --defaults-file=...\my.ini`) — Laragon как GUI не был запущен, сервис Windows не зарегистрирован, поэтому стартовал бинарь напрямую с существующим datadir `C:\laragon\data\mysql-8.4`. База `khf_site_cms` уже существовала из более раннего запуска (почти все миграции уже были применены) — выполнил `php artisan migrate --seed --force`, накатились только 5 новых миграций + сидеры прошли поверх существующих данных без ошибок.
  - `php artisan storage:link` — уже существовал, ок.
  - `npm run build` в CMS падал: `node_modules` был в состоянии pnpm (`.pnpm` store), не хватало `@tiptap/extension-text-align` и транзитивной `@tiptap/core` (импортируется напрямую в `resources/js/ui/rich-image.ts`, но не объявлена в `package.json` — под pnpm strict linking транзитивные пакеты не резолвятся). Решение: `rm -rf node_modules && npm install` (npm — целевой менеджер по A-6, hoisted-структура node_modules скрывает эту проблему). Сборка прошла (только известное предупреждение о размере чанка `RichEditor`, см. P3-5).
  - Front: `node_modules` уже стоял, `.env.local` уже был настроен под Laragon-блок (`API_URL=http://127.0.0.1:8848/api/v1`). Поднял `npx next dev -p 3000` напрямую (не `npm run dev` — тот скрипт использует `$(mkcert -CAROOT)`, чинится в A-5).
  - Оставил оба сервера (`php artisan serve --port=8848`, `next dev -p 3000`) и MySQL работать в фоне на всю сессию.
- **Проверено (smoke, §4.5 плана):**
  - `curl /api/v1/health` → `{"status":"ok",...,"checks":{"database":true}}`.
  - `curl /api/v1/news?locale=ru&per_page=2` → реальные новости из БД.
  - `curl /api/v1/news?locale=tg&per_page=2` → корректная таджикская локализация (не откат на ru).
  - `curl /api/v1/settings?locale=ru` → данные организации.
  - Браузер: `/`(→ru), `/tj`, `/en` — контент из CMS, заголовки на нужном языке (`document.documentElement.lang` проверен для `en`), в консоли только HMR-логи, ошибок нет.
  - `http://127.0.0.1:8848/login` — форма входа рендерится, переключатель локали (Тоҷикӣ/Русский/English) на месте, ошибок в консоли нет.
- **Решения:**
  - **MySQL вместо SQLite для дев-БД.** `.env` уже был так настроен (`DB_CONNECTION=mysql`, `DB_DATABASE=khf_site_cms`, root без пароля — стандартный Laragon-дефолт), и это лучше ловит класс багов P0-2 (SQL date-функции), который SQLite маскирует (см. P3-3). По факту это заранее закрывает часть D-4 — там просто останется прогнать полный сьют и добавить CI-матрицу.
  - **npm вместо pnpm/yarn в CMS уже сейчас** (частично, по факту) — узел `node_modules` пересобран через npm, чтобы получить рабочий `npm run build`. Формальное удаление `yarn.lock`/`pnpm-lock.yaml`/`pnpm-workspace.yaml` и коммит `package-lock.json` — отдельно, в A-6 (сейчас `package-lock.json` в untracked, `yarn.lock` неожиданно помечен git как modified — разберётся сам собой при удалении файла в A-6, не стал разбираться почему).
  - **QUEUE_CONNECTION=sync на время Stage 0/A**, переключение на `database` + постоянный `queue:work` — задача D-5 по плану.
  - **E0-6 (включение вебхука ревалидации) отложено**: секрет не сгенерирован, `FRONTEND_REVALIDATION_URL` пуст. Причина: A-1 (следующая задача) прямо переписывает обработку ошибок в `RevalidateFrontend` и настройку тестового окружения для этого URL — логичнее включать вебхук после этого, чтобы не проверять дважды. Вернусь к ручной проверке «публикация → обновление на фронте» после A-1.
- **Блокеров нет.** MySQL, оба dev-сервера — в фоне, не останавливать между задачами.

---

## A-1 · Изолировать тесты CMS от внешней сети — ГОТОВО

- **Сделано:**
  - `composer install` — оказалось, что `mews/purifier` + `ezyang/htmlpurifier` были в `composer.lock`, но НЕ в `vendor/` (не установлены). Это роняло `NewsTest`/`InstructionTest` санитайзинг-тесты фаталом `Class "Mews\Purifier\Facades\Purifier" not found`, маскируя реальную картину до фикса P0-1.
  - `phpunit.xml`: добавлены `<env name="FRONTEND_REVALIDATION_URL" value=""/>` и `SECRET=""` — тесты больше не зависят от `.env` разработчика.
  - `tests/Pest.php`: `Http::preventStrayRequests()` + `Http::fake()` глобально для `Feature` (гоча: bare `beforeEach(fn () => ...)` без `->in()` **молча не регистрируется** в Pest 4 — нужен именно `pest()->beforeEach(fn () => ...)->in('Feature')`; проверено эмпирически — с bare-версией джоба реально била по `localhost:3000`, `Http::fake()` не перехватывал ничего). Не глобально на весь сьют: `tests/Unit` — чистый PHPUnit без Laravel-бутстрапа, там нет фасада `Http`.
  - `app/Jobs/RevalidateFrontend.php`: `try/catch` вокруг `Http::...->throw()` (`ConnectionException|RequestException`) — под `sync`-соединением (инлайн-выполнение в HTTP-запросе редактора) ошибка логируется через `report()` и проглатывается; на реальной очереди (`getConnectionName() !== 'sync'`) исключение пробрасывается дальше — `tries=3`/`backoff` для воркера сохранены как просили.
  - Новый `tests/Feature/Cms/RevalidationTest.php`: 3 теста — ровно один POST с `Authorization: Bearer <secret>` и телом `{"tag":"cms"}` при настроенном вебхуке; ничего не уходит при пустом URL/secret; недоступность фронта (симулированный `ConnectionException`) не превращает публикацию в 500.
  - **Попутно найдены и исправлены 2 класса скрытых багов** (проявились только после того, как P0-1 перестал ронять тесты 500-й раньше времени — раньше тест-сьют никогда не доходил до этих строк):
    1. **Дефолтная локаль Symfony-тест-клиента.** `Symfony\Component\HttpFoundation\Request::create()` всегда подставляет `Accept-Language: en-us,en;q=0.5`, если тест явно не переопределил заголовок. `ResolveApiLocale` резолвит это в `en`, а `PublicLocale::available()` фильтрует материал, у которого `en`-перевод пустой — публичный список приходил пустым. Раньше это скрывалось за P0-1's 500. Пофикшено точечно (не глобально — не хотел расширять blast radius): `AnnouncementTest`, `DocumentTest`, `ProjectTest`, `InstructionTest` — их проверочные `getJson(...)` теперь используют явный `?locale=ru`, как и рекомендует сама карта гоч в плане (§7).
    2. **Неполные фикстуры ломают `guardRequiredTranslations()`.** `ProjectFactory` дефолтно оставлял `body.tg` пустым, `InstructionFactory` вообще не заполнял `body` — оба поля входят в `getTranslatableAttributes()`, значит tg/ru-полнота никогда не была 100%, и `WorkflowService::transition()` молча блокировал публикацию (редиректило назад с ValidationException, отсюда `assertRedirect()` проходил, а статус оставался `Draft`). Починил дефолты фабрик (заполнил `tg` везде, где сосед-поле уже заполнено) и убрал ручной `'tg' => ''` в `ProjectTest`/`InstructionTest`, которые специально проверяют публикацию.
  - **`composer types:check` тоже был красным** (не по моей вине — `SearchController.php`/`AlertMapService.php` я не трогал до этого): `AlertMapService.php:32` — `@var Collection<int, Alert>` был приклеен не к той строке (к `$alertsQuery`, Builder, а не к `$alerts` после `->get()`) — просто сдвинул докблок. `SearchController.php` — 4 из 9 ошибок были «generic Builder без TModel» у приватных хелперов (`whereMatches`/`shape`/`localizedExpression`/`plainExpression`) — добавил `@template TModel of Model` + `@param Builder<TModel>` по образцу уже существующего `app/Support/PublicLocale.php`. Прогнал `SearchApiTest` — зелёный, поведение не менялось (чисто докблоки).
- **Проверено:** `php artisan test --compact` → 341 тестов (338 + 3 новых), 340 passed, **1 красный** — `AnnouncementApiTest::it returns only publicly visible announcements, open ones first` (это и есть P0-2, следующая задача A-2, не относится к A-1). Ни одной `cURL error`. `vendor/bin/pint --dirty` чисто.
- **Решения:**
  - `RevalidateFrontend` резолвит "sync vs real queue" через `$this->job?->getConnectionName()`, а не через глобальный `config('queue.default')` — так уважает `->onConnection(...)` на конкретном диспатче, если он когда-нибудь появится.
  - **Оставшиеся 5 ошибок `types:check` в `SearchController.php` НЕ фиксил** — это `selectRaw()`/`Expression`/`DB::raw()` теперь везде типизированы как `@param literal-string` (свежее ужесточение стабов Laravel 13.20 против SQL-инъекций через сырые выражения), а `SearchController::localizedExpression()`/`plainExpression()` строят колонку через `$grammar->wrap()` (легитимно, но `wrap()` возвращает обычный `string`, никогда не `literal-string` — фреймворк делает ровно то же самое в своём `Builder::selectExpression()`, но vendor/ не анализируется). Чистого решения без `@phpstan-ignore`/приведения типов не нашёл — сами инструкции `composer types:check` явно это запрещают, а расширять API `Expression`/переписывать поиск ради типов на чужой, непрофильной для A-1 задаче — риск регресса в проде без отдельного плотного тестового прохода. Это `composer.json`-версийный дрейф (13.17→13.20 за неделю с аудита), не моя правка и не P0/P1 из реестра — вынес в техдолг, добавил как отдельную задачу в бэклог P3/D-6.
- **Коммит:** `a5a0f98`

---

## A-2 · Починить сортировку объявлений (таймзона) — ГОТОВО

- **Сделано:**
  - `app/Models/Announcement.php::scopeOrdered()`: `CURRENT_DATE` → биндинг `now()->startOfDay()->toDateString()`. Важно: `deadline` — чистая SQL `DATE`-колонка (`$table->date('deadline')` в миграции, не datetime, вопреки формулировке в плане). Поэтому биндинг явно приведён к `'Y-m-d'` (`->toDateString()`), а не оставлен как `Carbon`-объект — тот стрингифицируется в `'Y-m-d H:i:s'` (`__toString()`), и на SQLite сравнение `'2026-07-27' >= '2026-07-27 00:00:00'` дало бы **строковое** `false` (более короткая строка — префикс более длинной, лексикографически «меньше»), т.е. наивный биндинг тихо сломал бы «сегодняшний» дедлайн. На MySQL с нативным `DATE` так же безопаснее и явнее.
  - `grep -rn "CURRENT_DATE\|CURDATE\|NOW()" app/` — единственное вхождение было в этом же scope, других мест того же класса багов нет.
  - Тест `AnnouncementApiTest::it returns only publicly visible announcements, open ones first` (был красным) — теперь зелёный без изменений в самом тесте.
  - Новый регрессионный тест `it orders by deadline using the app timezone, not the database engine clock` с `Carbon::setTestNow(Carbon::create(2026,7,27,21,0,0,'Asia/Dushanbe'))`: объявление с дедлайном «сегодня» — первым и `open=true`, «вчера» — вторым и `open=false`. `afterEach` сбрасывает `Carbon::setTestNow()` (без него упавшая посреди теста фейковая дата протекла бы в следующие тесты).
- **Проверено:** `php artisan test --compact` → **342/342 passed**, весь сьют зелёный. `vendor/bin/pint --dirty` чисто. `composer types:check` — те же 5 (заранее известных, см. запись A-1) ошибок в `SearchController.php`, ни одной новой.
- **Решения:**
  - `Carbon::setTestNow()` **не** подделывает то, что вернёт сырой SQL `CURRENT_DATE` в тесте (это дергает реальные системные часы движка БД, а не PHP) — так что новый тест не воспроизводит именно старый SQL-баг «как было», а фиксирует контракт «сравнение идёт по `now()` приложения» и защищает от повторного скатывания на `CURRENT_DATE`/`NOW()`/`CURDATE()`. Существующий тест (относительные `now()->subDay()`/`addWeek()`) остаётся основной регрессией самого P0-2 — он и был красным до фикса, стал зелёным после.
- **Коммит:** `7466a37`

---

## A-5 · Кроссплатформенный `npm run dev` (khf-site-front) — ГОТОВО

- **Сделано:** `khf-site-front/package.json`: `"dev"` теперь просто `next dev`; macOS/lerd-вариант с `NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/..."` вынесен в `"dev:mkcert"`.
- **Проверено:** `npm run dev` не использует `$(...)`-подстановку — валиден и в PowerShell, и в zsh/bash. Уже запущенный фоновый dev-сервер сессии (`next dev -p 3000`, поднят в Stage 0 напрямую через `npx`) не трогал.
- **Решения:** нет отдельных — прямое исполнение пункта плана A-5.
- **Коммит:** `khf-site-front@d1acfc5`

---

## A-3 · Тест-раннер и первые тесты фронта (khf-site-front) — ГОТОВО

- **Сделано:**
  - Установлены dev-зависимости (по офиц. `node_modules/next/dist/docs/.../testing/vitest.md`): `vitest @vitejs/plugin-react jsdom @testing-library/react @testing-library/dom vite-tsconfig-paths` + `@playwright/test`. `npx playwright install chromium --with-deps`.
  - `vitest.config.mts`: `environment: 'jsdom'`, `tsconfigPaths()`-плагин для алиаса `@/*`.
  - `tests/unit/`: `i18n-config.test.ts` (`toApiLocale`, `htmlLang`, `isLocale`/`toLocale` на мусоре, `localeFromPathname`, `withLocale`/`stripLocale`), `seo.test.ts` (`buildAlternates` — ключи ru/tg/en+x-default и корректный canonical; `buildMetadata` — OG/Twitter, `publishedTime` только для `type=article`), `api.test.ts` (мок `global.fetch`: `fetchNews` конвертит `tj→tg` в query, выбрасывает пустые/undefined параметры, деградирует к пустому результату на 500/сетевой ошибке; `fetchNewsItem` — `null` на 404, **пробрасывает** ошибку на 500 и на сетевой сбой), `dictionaries.test.ts` (структурная эквивалентность ключей `ru`/`tj`/`en` — импортирует модули словарей напрямую в обход `dictionaries.ts`, который помечен `server-only` и падает при обычном импорте в Node/Vitest).
  - `playwright.config.ts` + `tests/e2e/smoke.spec.ts`: `/ru`/`/tj`/`/en` → 200 + `<html lang>` (`ru`/`tg`/`en`); `/news` без локали → редирект на `/ru/news` (форсировал `locale: 'fr-FR'` в контексте браузера, иначе тест зависел бы от Accept-Language хоста); переключатель языка сохраняет путь; поиск ведёт на `/{locale}/search?q=`; несуществующий **маршрут** (не slug) отдаёт 404. `webServer` в конфиге сам поднимает `npm run dev` (`reuseExistingServer` вне CI).
  - `package.json`: `"test": "vitest run"`, `"test:e2e": "playwright test"`.
  - `.gitignore`: `/test-results/`, `/playwright-report/`, `/blob-report/`, `/playwright/.cache/`.
- **Проверено:** `npm test` → **35/35 passed**. `npm run test:e2e` → **7/7 passed** (сначала прогнал на живом dev-сервере из Stage 0). `npx tsc --noEmit` и `npx eslint` — чисто (тестовые файлы тоже под линтом/тайпчеком, отдельно не исключал). `npm run build` — 108 страниц, без ошибок.
- **Решения:**
  - **Slug-level 404 не тестировал.** Эмпирически проверил через curl: `/ru/news/<random>`, `/ru/alerts/<random>`, `/ru/projects/<random>`, `/ru/guides/<random>`, `/ru/pages/<random>` — все отдают **200**, не 404 (это и есть P1-5 из реестра, чинится в B-5). Маршрутный (не slug) 404 — `/ru/<неизвестный-путь>` — уже сейчас корректно отдаёт 404, его и покрыл. Когда доберусь до B-5 — добавлю рядом e2e для slug-варианта.
  - **`fullyParallel: false` / `workers: 1` в Playwright — не опция производительности, это фикс реальной гонки.** С параллельными воркерами `next dev` (Turbopack) иногда 500-ил на `/ru/news` с `SyntaxError: Unexpected end of JSON input` (обрыв на чтении ещё компилируемого чанка при одновременных запросах к разным «холодным» маршрутам) — переключение на `workers=1` убрало ошибку полностью, воспроизвёл дважды. Стек — целиком внутри Next/Turbopack (`JSON.parse` без прикладных фреймов), в коде проекта `JSON.parse` не встречается вовсе. Для CI (A-4, скорее всего `next build && next start`) гонки не будет в принципе (нет инкрементальной компиляции), но конфиг общий для обоих режимов — оставил `workers: 1` и там, сьют маленький (7 тестов), сериализация стоит ~2 секунды.
  - **Радио-кнопки переключателя языка визуально скрыты** (кастомная стилизация `.seg-opt`) — `getByRole('radio').click()` зависает («element is not visible»). Кликаю по видимому `<label>`-тексту (`ТҶ`), как это делает реальный пользователь.
  - **`npm audit`: 12 high, 0 critical** — не связано с новыми зависимостями теста (vitest/playwright/testing-library в отчёте не фигурируют). Основные: транзитивный `minimatch`/`brace-expansion` через `eslint-config-next`, и сам **`next` закреплён на `16.2.10`, а `16.2.12` закрывает несколько high-советов, включая «Middleware/Proxy bypass... using Turbopack and single locale»** — потенциально релевантно именно нашему `proxy.ts`. Апгрейд не делал: это патч-версия фреймворка, а не тестовая инфраструктура, вне периметра A-3, и по инструкции сессии `npm audit` — явно в этапе F («не выполнять, только подготовить чек-лист»). Зафиксировал здесь, чтобы не потерялось; кандидат на приоритетный пункт F-2/D-6.
- **Коммит:** `khf-site-front@49d0284`

---

## A-4 · CI для фронта (khf-site-front) — ГОТОВО

- **Сделано:**
  - `.github/workflows/ci.yml`: Node 22 (`cache: npm`), `npm ci` → `tsc --noEmit` → `eslint` → `npm test` → `npm run build` → `npx playwright install --with-deps chromium` → `npm run test:e2e` → загрузка `playwright-report/` артефактом при падении.
  - Actions запинены по SHA: `actions/checkout`/`actions/setup-node` — те же SHA, что уже в `khf-site-cms/.github/workflows/tests.yml` (переиспользовал, не гадал); `actions/upload-artifact@v4` — SHA не помнил наизусть, **проверил через GitHub API** (`git/refs/tags/v4` и `commits/v4`, оба ответа сошлись) вместо того, чтобы угадывать — на первый заход ошибся на 1 символ (`...fa9` вместо `...fa02`), что дало бы невалидную (39-символьную) SHA-1 и сломало бы workflow тихо до первого реального запуска.
  - `playwright.config.ts`: `webServer.command` теперь `process.env.CI ? "npm run start" : "npm run dev"` — в CI и так есть отдельный шаг `npm run build` перед e2e, незачем ещё раз гонять Turbopack dev-компиляцию.
- **Проверено:** локально не поднять реальный GitHub Actions раннер, поэтому дважды прогнал сам workflow «руками»: (1) `js-yaml` парсит `ci.yml` без ошибок, 10 шагов на месте; (2) `npm run build` → `CI=true npx playwright test` — тот же CI-путь (`reuseExistingServer:false`, команда `npm run start`) — **7/7 e2e passed** за 2.8с (быстрее дев-режима, ожидаемо: нет компиляции на лету). Дев-сервер сессии перезапущен после проверки.
- **Решения:** нет отдельных — прямое исполнение пункта плана A-4. Критерий приёмки («workflow зелёный на push/PR») формально не проверить без реального пуша в GitHub (план это и не требует — push запрещён границами сессии); эквивалент проверил локальным прогоном идентичных команд.
- **Коммит:** `khf-site-front@f72b4cf`

---

## A-6 · Один менеджер пакетов в каждом репо — ГОТОВО

- **Сделано:**
  - CMS: удалил `yarn.lock` (он же с необъяснённой правкой из Stage 0 — вопрос снят вместе с файлом) и `pnpm-lock.yaml`. `package-lock.json` уже был сгенерирован в Stage 0 (`npm install` при починке сборки).
  - Front: удалил `pnpm-lock.yaml` и `pnpm-workspace.yaml`. `package-lock.json` уже существовал.
  - Оба репо: `rm -rf node_modules && npm ci` с нуля — чисто, полёт нормальный.
  - **Попутно (CMS):** `npm run format:check` (Prettier) неожиданно показал 2 файла с расхождениями — `resources/js/pages/announcements/form.tsx`, `resources/js/pages/pages/form.tsx`. Я их не трогал; `CMS_AUDIT.md` фиксировал «Prettier: PASS» на момент аудита — очередной дрейф за неделю (тот же класс, что и с missing `mews/purifier` и PHPStan/`literal-string` из записей A-1). В отличие от `SearchController.php`, тут фикс безопасный и механический (только перенос строк, `git diff` подтвердил — без логики) — прогнал `npm run format`, оба файла причёсаны, пересобрал `npm run build`.
- **Проверено:** CMS — `npm run build`/`lint:check`/`format:check` чисто с нуля. Front — `npx tsc --noEmit`, `npx eslint`, `npx vitest run` (35/35), `npm run build` (108 страниц) чисто с нуля. Дев-серверы обоих репо перезапущены после чистки `node_modules`.
- **Решения:**
  - Выбор `npm` фиксирую в README фронта — это уже задача A-7 (следующая), делаю её сразу следом, чтобы не плодить отдельный проходной коммит только за одну строчку.
  - В CMS README нет вообще (не только про пакетный менеджер — никакого), а `CLAUDE.md` прямо запрещает заводить документацию не по явному запросу пользователя. Явного запроса на **README для CMS** в плане нет (P3-1/A-7 — только про фронт), поэтому не создаю. Выбор пакетного менеджера для CMS фиксируется тем, что в репозитории теперь ровно один lock-файл, плюс этой записью в журнале.
- **Коммит:** (в записи следующей задачи)

---
