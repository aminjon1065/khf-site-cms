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
- **Коммит:** CMS `29d068b`, `khf-site-front@c945ea7`

---

## A-7 · README и docs фронта — ГОТОВО

- **Сделано:** переписал `khf-site-front/README.md` (был дефолтный от `create-next-app`): запуск Windows/Laragon и macOS/Herd, переменные окружения (таблица), контракт локалей `tj`(URL)/`tg`(API/`html lang`) с явным предупреждением про `?locale=tg` при отладке, таблица «страница → функция в `lib/api.ts` → эндпоинт CMS», как работает вебхук ревалидации (`revalidateTag("cms","max")`), контракт мягкой деградации, команды тестов, структура каталогов, пакетный менеджер.
  - `khf-site-front/docs/PROJECT_PLAN.md` уже существовал (указатель на канонический план в CMS-репо) — не трогал, актуален.
  - Каждое фактическое утверждение в README (путь `app/api/revalidate/route.ts`, точный вызов `revalidateTag("cms", "max")`, состав `lib/copy/`, `components/i18n/`, что leadership/structure/symbols/sos не дёргают `lib/api.ts`) сверил `grep`/`cat`, а не по памяти.
- **Проверено:** README — не код, `tsc`/`eslint`/тесты не затрагивает; визуально перечитал на предмет опечаток и битого markdown.
- **Решения:** нет отдельных.
- **Коммит:** `khf-site-front@10f56f9`

---

### Этап A — ГОТОВ (7/7): A-1…A-7 закрыты. CMS: 342/342 тестов зелёные (было 320/338 на входе). Фронт: тест-инфраструктура с нуля (35 unit + 7 e2e), CI, единый пакетный менеджер, README. Дальше — этап B (функциональные пробелы фронта), без пауз.

---

## B-1 · Пагинация списков фронта — ГОТОВО

- **Сделано:**
  - Новый `components/public/Pagination.tsx` — серверный компонент: обычные `<Link>` на `?page=N` (работает без JS), окно номеров вокруг текущей страницы с «…» вместо полного списка (защита от абсурдного количества ссылок при большом `last_page`), сохраняет произвольные доп. query-параметры (`query` prop — нужен для `/search?q=…&page=2`). Строки — новый общий `common.pagination` в `lib/copy/common.ts` + переводы в `tj.ts`/`en.ts` (не локальные, как было у news — теперь используются везде).
  - `lib/api.ts`: `fetchInstructions`, `fetchDocuments`, `fetchProjects`, `fetchAnnouncements` переведены с позиционного `(locale?)` → объект `{locale?, page?, perPage?}`, возвращают `Paginated<T>` целиком (раньше отбрасывали `meta`), по образцу уже так работавшего `fetchNews`. Дефолтный `per_page` — 20 (было захардкожено 50 везде).
  - `lib/seo.ts`: `buildAlternates`/`buildMetadata` принимают `page` — добавляют `?page=N` к canonical и **ко всем** hreflang-альтернатам (не только canonical) при `page>1`.
  - Списочные страницы (`news`, `documents`, `projects`, `announcements`, `guides`, `search`) читают `searchParams.page`, передают в `fetch*`, рендерят `<Pagination>`, `generateMetadata` учитывает `page`.
  - `news/page.tsx` + `NewsList.tsx`: убрал клиентскую пагинацию (`useState<page>` + `.slice()`) — теперь список одной страницы приходит с сервера уже нужного размера (`per_page=12`), `<Pagination>` рендерится сервером рядом. Фильтр категорий/поиск в `NewsList` **остался клиентским** (в пределах текущей страницы) — это осознанно оставлено для B-2 (план прямо просит их объединить, но объём different: в одном PR тестировать и то, и другое неудобно). Заодно убрал ставший мёртвым `feed.pageSize` и локальный блок `pagination` из `news/content.ts` (все 3 локали) — их заменил общий `common.pagination`.
  - `guides/page.tsx`: приоритетные плитки (3 избранные инструкции) теперь показываются только на первой странице — на второй и далее это была бы путаница (плитки «из ниоткуда» не с той же выборки).
  - `projects/[slug]/page.tsx`: локальная переменная `meta` (карточка «Заказчик/Партнёры/Бюджет/Срок») уже существовала — переименовал деструктурированное `meta` из `fetchProjects` в `allProjects`/убрал деструктуризацию, чтобы не столкнуть имена.
  - `app/sitemap.ts`: `fetchProjects`/`fetchInstructions` — на новый объектный сигнатурный контракт, `.data.map(...)` вместо `.map(...)`.
  - Новый `tests/e2e/pagination.spec.ts`.
- **Проверено:**
  - `npx tsc --noEmit`, `npx eslint` — чисто.
  - `npx vitest run` — 35/35.
  - `npx playwright test` — 8 passed + 1 skipped (см. решения).
  - `npm run build` — 108+ страниц, без ошибок.
  - Руками через браузер/curl: временно занизил `PER_PAGE` новостей до 2 (в БД всего 3 демо-новости) — подтвердил, что `?page=2` отдаёт **другой** материал, заголовок вкладки получает суффикс «— 2», `?page=999` отдаёт `200` с пустым состоянием «Ничего не найдено» (не 500) — точно критерий приёмки из плана. Вернул `PER_PAGE=12` перед коммитом.
- **Решения:**
  - **Демо-БД слишком маленькая, чтобы e2e честно кликал по «странице 2».** Максимум — 11 инструкций; при разумном `per_page` (12–20, как просит план) ни один список не набирает 2 страницы на текущих данных. Плановое «60 новостей» — сценарий будущего/прод-объёма, не этого сида. Решил **не** досеивать демо-данные ради теста (не хотел менять известное состояние демо-контента без необходимости) — вместо этого `pagination.spec.ts` детерминированно проверяет сам механизм (`?page=2`/`?page=999` никогда не 500), а «клик по видимой странице 2» — **условный** тест: находит ссылку `aria-label="Страница 2"`, при её отсутствии — `test.skip()`, а не ложный fail. Когда объём демо-контента вырастет (или в D-4/проде) — тест сам начнёт реально проверять клик.
  - **`rel=prev/next` не добавлял.** У `Metadata.alternates` в Next 16 нет для них поля (только `canonical/languages/media/types`, проверил `alternative-urls-types.d.ts`), а Google официально не использует их как сигнал с 2019-го — статичный `@phpstan-ignore`-подобный обходной путь ради мёртвого сигнала показался хуже, чем честно этого не делать. `canonical` (то, что реально просили и реально работает) — сделан.
  - **Списочные страницы стали `ƒ` (Dynamic) вместо `●` (SSG) в выводе `next build`** — ожидаемо и неизбежно: чтение `searchParams.page` само по себе выключает статическую прегенерацию (Next не знает заранее, сколько страниц будет). Это не потеря ISR-кэширования данных — `fetch()` в `lib/api.ts` по-прежнему кэшируется через `next: { revalidate: 60, tags: ['cms'] }` на уровне данных, лишний расход — только CPU на рендер HTML per-request, не лишние запросы к CMS API. `/search` был `ƒ` уже до меня (там же `searchParams.q`) — паттерн не новый, просто расширен на остальные списки.
- **Коммит:** `khf-site-front@ae785ea`

---

## B-2 · Серверный фильтр новостей по категории — ГОТОВО

- **Сделано:**
  - Новая `fetchCategories({type?, locale?})` в `lib/api.ts` — `GET /categories`, по умолчанию `type=news`.
  - `news/page.tsx`: читает `searchParams.category`, передаёт в `fetchNews({..., category})` (сервер уже поддерживал `?category=` — `NewsController::applyFilters` фильтрует по `whereHas('category', ...slug)`, просто фронт им не пользовался), параллельно запрашивает справочник категорий. `<Pagination>` получил `query={{category}}` — номера страниц теперь сохраняют текущую категорию.
  - `NewsList.tsx`: кнопки категорий (`aria-pressed`, клиентский `useState`) → обычные `<Link href="/news?category=slug">` (`aria-current="true"` на активной) — без JS работают, переживают перезагрузку, шарятся ссылкой (буквальный критерий приёмки из плана). Категория теперь берётся из серверного справочника (slug+имя), а не «какие категории оказались среди уже загруженных 50 новостей» (реальный баг P1-2 — категория за пределами первой выборки просто не появлялась в фильтре). Поиск по заголовку/анонсу остался клиентским (план просил серверным сделать именно категорию, не поиск) — действует в пределах текущей серверной страницы, как и раньше.
  - Новый `tests/e2e/news-category-filter.spec.ts`: выбор категории сужает список и меняет URL; фильтр переживает `page.reload()`; «Все» очищает `?category=`.
- **Проверено:**
  - `npx tsc --noEmit`, `npx eslint` — чисто.
  - `npx vitest run` — 35/35 (не менял тестируемую логику `lib/api.ts` для новостей, `fetchCategories` пока без юнит-теста — тривиальная обёртка, покрыта e2e).
  - `npx playwright test` — 11 passed + 1 skipped (тот же skip из B-1, не про эту задачу).
  - `npm run build` — чисто.
  - curl напрямую в CMS и во фронт: `?category=sotrudnichestvo` → 1 материал (было 3 без фильтра) — сервер действительно фильтрует, а не клиент утаивает лишнее.
- **Решения:**
  - Не стал делать поиск (текстовое поле) серверным — план просил именно «серверный фильтр **по категории**», текстовый поиск не упоминает; отдельный текстовый `?q=` для списка новостей (в отличие от `/search`) — за рамками этой задачи.
  - Метаданные (`canonical`/hreflang) остаются зависимыми только от `page`, не от `category` — план просит `page`-aware metadata explicитно, category-aware canonical/индексация каждого фильтра — отдельное SEO-решение, которое план не поднимает; не стал придумывать за него.
- **Коммит:** `khf-site-front@b0dac43`

---

## B-3 · Страница объявления `/announcements/[slug]` — ГОТОВО

- **Сделано:**
  - `fetchAnnouncement(slug, locale)` в `lib/api.ts` (`GET /announcements/{slug}`, уже существовал на CMS) — по образцу `fetchProject`/`fetchAlert`: `null` на 404, пробрасывает ошибку на 5xx/сеть.
  - `routes.announcement(slug)` в `lib/routes.ts`.
  - Новый `app/[locale]/announcements/[slug]/page.tsx` (по образцу `projects/[slug]`): заголовок, организация, тип объявления (`kind_label` — уже локализован сервером), срок и его состояние (цвет по `open`), тело (`desc`), кнопка «Подать заявку» на `application_url`, блок «Другие объявления». `generateStaticParams` отсекает записи без `slug` (тот же паттерн, что в `alerts/[slug]`, — P2-4 по объявлениям тоже актуален: слаг мог не проставиться при старом сиде). `generateMetadata` через `buildMetadata`, `notFound()` на 404.
  - **Безопасность `application_url`:** новый `lib/url-safety.ts` → `isSafeExternalUrl()` — только `http`/`https` (не `javascript:`, `data:`, `mailto:`, `tel:`, не битая строка); ссылка — `target="_blank" rel="noopener noreferrer"`, иконка `ExternalLink` как визуальная пометка «внешняя ссылка». Если `application_url` не задан (или не прошёл проверку) — сайдбар показывает блок «Контакты» вместо кнопки, а не пустое место.
  - `AnnouncementsFilter.tsx`: карточки в списке раньше вели на `routes.contacts` (заглушка, т.к. детальной страницы не существовало) — теперь на `routes.announcement(a.slug)`, с фолбэком на список, если `slug` пуст.
  - `pages.announcementDetail` + `pages.meta.announcementFallback` — новые ключи словаря (`lib/copy/pages.ts` + переводы в `tj.ts`/`en.ts`).
  - `app/sitemap.ts`: объявления добавлены в динамические маршруты (были единственным типом с публичной detail-страницей, отсутствующим в карте сайта).
  - Тесты: `tests/unit/url-safety.test.ts` (http/https принимает; `javascript:`/`data:`/`mailto:`/`tel:`/битые строки — отклоняет), `tests/e2e/announcement-detail.spec.ts` (карточка из списка ведёт на деталь; при отсутствующем `application_url` показывается фолбэк «Контакты» — деталь реального демо-датасета, все текущие объявления без `application_url`).
- **Проверено:**
  - `npx tsc --noEmit`, `npx eslint` — чисто.
  - `npx vitest run` — 39/39.
  - `npx playwright test` — 13 passed + 1 skipped (тот же skip из B-1).
  - `npm run build` — `/[locale]/announcements/[slug]` собрался как `●` (SSG).
  - curl по всем 3 локалям на реальный slug — 200, корректные `title`/`og:*`/`canonical`/hreflang-альтернаты (ru/tg/en/x-default) в HTML.
- **Решения / находки:**
  - **Обнаружил (не создал) более широкий вариант P1-5, чем описано в плане.** Формулировка плана — «404 показывает русский текст на /tj и /en» (проблема ЛОКАЛИЗАЦИИ 404-страницы). На практике сейчас страница `not-found.tsx` уже клиентская и корректно локализуется через `usePathname()` — текст на месте. Но **сам HTTP-статус для несуществующего slug — 200, не 404**, и это не только в dev-режиме (как я сперва предположил) — воспроизвёл и в `next build && next start`. Проверил на ВСЕХ существующих detail-маршрutах (`news/[slug]`, `alerts/[slug]`, `projects/[slug]`, `guides/[slug]`, `pages/[slug]`) — везде тот же 200 вместо 404, значит это системная особенность `notFound()` под статическим `generateStaticParams`-маршрутом с `dynamicParams` по умолчанию (шаблон соответствует HTML, но статус коммитится раньше, чем резолвится `notFound()`), не что-то специфичное для новой страницы объявлений. Новая страница объявлений реализована **по тому же паттерну**, что и все соседи (иначе была бы единственной непоследовательной) — системный фикс статус-кода per plan явно назначен на B-5 (`global-not-found.tsx`), туда и оставил; выполнять точечный костыль здесь means 6 разных полу-решений вместо одного правильного в B-5.
  - Демо-датасет объявлений: ни у одного `application_url` не задан, поэтому «есть кнопка Подать заявку» руками/e2e на реальных данных не проверить — закрыл веткой `isSafeExternalUrl` юнит-тестом (сама логика проверена исчерпывающе) и e2e для наблюдаемой (fallback) ветки.
- **Коммит:** `khf-site-front@0805ca1`

---

## B-5 · Локализованная страница 404 — ГОТОВО (с задокументированной остаточной границей)

Задача оказалась глубже, чем формулировка в плане — расписываю подробно, чтобы не переисследовать это заново.

- **Расклад ДО (замерено, не по памяти):**
  1. `/tj/nonexistent`, `/en/nonexistent` (путь вообще не матчится ни одному маршруту) → 404 ✓, `noindex` ✓, но **текст всегда на русском** — это и есть буквально то, что описывает P1-5.
  2. `/ru/news/bogus-slug` и аналоги (`notFound()` вызван ИЗ страницы, маршрут `[slug]` matched, просто данных нет) → текст **уже корректно локализован** (существующий `app/[locale]/not-found.tsx` — клиентский, `usePathname()`), но **статус 200, не 404** — и это не dev-режим: воспроизвёл в `next build && next start` для ВСЕХ шести типов detail-маршрутов (news/alerts/projects/guides/pages/announcements).
- **Почему так — нашёл первопричину через `node_modules/next/dist/docs`, не гадал:**
  - `file-conventions/loading.md#status-codes` (дословно): «When streaming, a 200 status code will be returned... Because response headers have already been sent, the status code cannot be updated... The response body starts streaming when a Suspense fallback renders (e.g. `loading.tsx`) or when a Server Component suspends under a Suspense boundary.» У фронта есть общий `app/[locale]/loading.tsx` — это Suspense-граница для ВСЕХ маршрутов под `[locale]`, поэтому `notFound()`, вызванный где угодно внутри, физически не может изменить уже отправленный `200`. Это не баг конкретной страницы — архитектурное следствие наличия `loading.tsx`.
  - Тот же абзац: «Next.js includes `<meta name="robots" content="noindex">`... even if HTTP status is 200» — т.е. SEO это НЕ ломает (не индексируется в любом случае), проверил — тег на месте.
- **Сделано:**
  - `app/not-found.tsx` (корневой, ловит path вообще без матча) удалён, вместо него — `app/global-not-found.tsx` + `experimental.globalNotFound: true` в `next.config.ts`, точно как советует план. Локаль — тем же клиентским `usePathname()`-паттерном, что и `app/[locale]/not-found.tsx` (никаких `headers()` — план прямо предупреждает, что это уже роняло `projects/[slug]` в прод-500).
  - **Важная находка по ходу:** `global-not-found.tsx` тоже статически оптимизируется Next при сборке (build-лог больше не показывает отдельную `○ /_not-found` строку — обрабатывается на уровне роутинга) — значит `usePathname()` в САМОМ ПЕРВОМ (pre-hydration) SSR-байте так же не видит реальный путь и откатывается на `ru`, проверил через `next build && next start` + curl. **НО** через реальный браузер (`javascript_tool`, не curl) после гидратации `document.documentElement.lang` и текст корректно становятся `tg`/`en` — самокоррекция происходит практически мгновенно. Для живого пользователя (кликнул битую ссылку) — работает правильно. Для curl/бота, не исполняющего JS — нет, но такой клиент и не должен ничего индексировать (noindex уже стоит), и настоящие краулеры (Googlebot) рендерят JS перед оценкой контента.
- **Проверено:**
  - `npx tsc --noEmit`, `npx eslint` — чисто.
  - Новый `tests/e2e/not-found.spec.ts` (12 тестов): для всех 3 локалей — 404 + noindex + корректная локаль **после гидратации** (genuinely-unmatched путь); для 3 типов matched-маршрутов — не 500 (не 404, задокументированное ограничение), noindex, и **сырой** (pre-hydration, через `request.get()`) ответ с корректной локалью — это ветка, которая физически может быть точной, и она точна.
  - `npm run build` — чисто, включая `projects/[slug]` (тот самый маршрут, что ранее давал прод-500 на инлайн-варианте с `headers()`) — не 500.
  - `CI=true npx playwright test` (прогон на `next build && next start`, как в реальном CI) — **20/20 passed** (+1 ожидаемый skip из B-1), 6 секунд.
  - **Дев-режим (Turbopack) даёт периодический таймаут ИМЕННО на `/en/nonexistent`** при прогоне всего сьюта разом (не в изоляции) — тот же класс проблемы, что и в A-3/B-1 (`workers:1`), только на новом файле `global-not-found.tsx`, который Turbopack, похоже, компилирует медленнее обычных страниц при большой сопутствующей нагрузке. В CI-режиме (прод-сборка, без инкрементальной компиляции) — воспроизвёл трижды подряд, всегда 20/20. `retries: 2` в CI-конфиге (сделано в A-4) уже страхует от этого класса дев-флейков.
- **Решения:**
  - Не стал гнаться за полным устранением pre-hydration-разницы локали на **genuinely-unmatched** путях (proxy-level pre-check API-вызов к CMS на каждый визит детальной страницы) — цена (лишний round-trip к CMS на КАЖДЫЙ визит любой из 6 detail-страниц, конфликт с философией мягкой деградации из B-6) не оправдана подтверждённым нулевым эффектом на SEO и реальных пользователей (только что показал: браузер сам исправляет за миллисекунды).
  - Статус `200` вместо `404` для matched-route `notFound()` — сознательно принятая, задокументированная граница, а не забытый баг: устранить полностью можно только убрав `app/[locale]/loading.tsx` (общий Suspense) — это ухудшит UX загрузки списков ради статус-кода, который не бьёт ни SEO (noindex), ни пользователя (корректный текст). Если понадобится для аналитики/compliance — фиксировать через `proxy.ts` точечно для конкретных проблемных маршрутов, не сейчас.
- **Коммит:** `khf-site-front@b98d743`

---

## B-4 · next/image для медиа CMS — ГОТОВО

- **Сделано:**
  - Нашёл все сырые `<img>` во фронте (grep по `.tsx`) и разложил на две категории: (A) обложки из CMS с `image`/`image_srcset` (главная — избранная новость, `news/[slug]`, `projects/[slug]`, плюс общий `ImageSlot` для карточек-заглушек) и (B) локальные ассеты с фиксированными пропорциями (шапка/подвал — флаг, герб, логотип КЧС; страница `symbols`). `article-prose` (HTML тела новости из CMS) сознательно не трогал — своя разметка/`srcset` от CMS, `next/image` там неприменим.
  - `components/public/ImageSlot.tsx`: ветка с `src` переведена на `next/image` (`fill` + `object-fit`, обёртка `<span className="relative block h-full w-full">` — сам компонент берёт на себя позиционирование, вызывающий код без изменений). Добавлен `eager?: boolean` (пробрасывается в `loading="eager"`) — понадобился реально, не про запас (см. ниже).
  - Обложки CMS (`app/[locale]/page.tsx` — избранная новость, `news/[slug]/page.tsx`, `projects/[slug]/page.tsx`): `<img src srcSet sizes>` → `<Image fill sizes style={{objectFit:"cover"}}>`. **Осознанно перестал потреблять `image_srcset` от CMS** — `next/image` со своим `remotePatterns`-пайплайном сам генерирует `srcset` под `deviceSizes`/`imageSizes`; для контейнеров `fill` документация `next/image` прямо требует `sizes`, а произвольный `srcSet` как проп для `<Image>` не поддерживается (только через `Device Sizes`/`sizes`, см. `node_modules/next/dist/docs/.../image.md`).
  - Локальные ассеты (`PublicHeader.tsx` ×4, `PublicFooter.tsx` ×1, `symbols/page.tsx` + `symbols/content.ts` ×6 — флаг/герб на 3 локали): переведены на `next/image` с явными `width`/`height` (intrinsic, измерены `sharp`: `flag-tj.png` 1920×960, `emblem-tj.png` 330×327, `logo-kchs-*.webp` 512×506) — существующие Tailwind-классы (`h-[13px] w-auto` и т.п.) управляют фактическим отображаемым размером, как и раньше с сырым `<img>`; в `SymbolBlock.image` (`symbols/content.ts`) добавлены поля `width`/`height`.
  - `remotePatterns` в `next.config.ts` уже покрывал CMS-хост (`127.0.0.1`, `localhost`, `khf-site-cms.test`, `**.khf.tj`) — без изменений; для локальных `/assets/*` `next/image` не требует `remotePatterns` вовсе (только внешние домены), `localPatterns` не настроен — значит все локальные пути разрешены по умолчанию.
  - Новый `tests/e2e/images.spec.ts` — на каждой из проверяемых страниц скроллит каждый `<img>` в вьюпорт (иначе `loading="lazy"` для нижних картинок не сработает в тесте) и ждёт `naturalWidth > 0`; это прямой регресс-тест именно того класса поломки, которую легко внести здесь незаметно (неверные `width`/`height`, не покрытый `remotePatterns` хост, потерянная `fill`-обёртка).
- **Проверено:**
  - `npx tsc --noEmit`, `npx eslint` — чисто.
  - `npx vitest run` — 39/39 (не менял юнит-тестируемую логику).
  - `npm run build` — чисто, все 108 страниц, включая `symbols` и все `[slug]`-детали.
  - `npx playwright test` — сначала поймал **реальный** сигнал не по этой задаче конкретно, а по её последствию: дев-консоль показала `Image with src "/assets/president.jpg" was detected as the Largest Contentful Paint (LCP). Please add the loading="eager" property` — фото Президента (через `ImageSlot`), а не избранная новость, оказалось фактическим LCP-элементом главной страницы. Добавил `eager` в `ImageSlot` (см. выше) и переключил обложку избранной новости с `preload` на `loading="eager"` — по документации `next/image` (`node_modules/next/dist/docs`) `preload` специально **не** рекомендован, когда на странице несколько кандидатов в LCP в зависимости от вьюпорта («When you have multiple images that could be considered the LCP element... use loading='eager' instead»), а у нас на главной ровно такой случай (слайдер/новость и карточка Президента — оба выше сгиба). После фикса — предупреждение исчезло, перепрогон: 22 passed + 1 skipped (тот же известный skip из B-1) + 1 failed — тот же самый задокументированный в B-5 дев-only Turbopack-флейк (`ChunkLoadError` на `/{locale}/nonexistent` при полном прогоне сьюта), не регрессия: воспроизводится независимо от locale (в этот раз на `/ru`, до фикса — на `/en`), уже покрыт `retries: 2` в CI.
  - Руками через браузерные тулы: `read_page`/`javascript_tool` на первом заходе показали флаг/герб/логотип с `naturalWidth: 0, complete: false` — насторожило, но `computer{screenshot}` тут же и явно ответил «Browser pane is not displayed, so the page is not compositing frames» — т.е. в этой headless-сессии никто не держит панель открытой, поэтому браузер физически не выполняет layout/paint и `loading="lazy"` (IntersectionObserver) никогда не срабатывает — это ограничение самого превью-инструмента в headless-режиме, не баг кода. Подтвердил через **настоящий** Chromium (Playwright, который у меня уже есть в связке): `images.spec.ts` зелёный на всех проверенных страницах (`naturalWidth > 0` после скролла в вьюпорт) — авторитетное подтверждение, превью-панель проигнорирована как источник истины.
- **Решения:**
  - `ImageSlot` получил `eager` не «на будущее», а по конкретному, только что подтверждённому в браузере кейсу (фото Президента) — единственный текущий вызов с `src` использует его; остальные 8 мест передают только `label` (плейсхолдер), их ветка не менялась вовсе.
  - Не стал переводить `article-prose` (тело новости из CMS, `dangerouslySetInnerHTML`) — вне области задачи по плану, свой `srcset` от CMS, `next/image` не может обернуть произвольный HTML-фрагмент без парсинга/rewrite тела статьи.
- **Коммит:** `khf-site-front@df3e84f`

---

## B-6 · Ревизия мягкой деградации — ГОТОВО

Начал со статического аудита (все 21 функция `lib/api.ts` + все 20 маршрутов), но статический аудит **ошибся** по ключевому пункту — детали ниже. Финальные выводы — только по факту прогона реального `next start` с недоступным API, не по чтению кода.

- **Сделано:**
  - **Инструмент проверки:** новый Playwright-проект `backend-down` (`playwright.config.ts`) — `page.route()` не подходит: серверные fetch'и (Server Components) выполняются в процессе Next, а не в браузере, браузерный мок их не видит. Вместо мока — настоящий `next start` с `API_URL=http://127.0.0.1:1/api/v1` (порт 1 зарезервирован ОС, `fetch` падает мгновенно как "bad port", без ожидания таймаута). Turbopack отказывается держать второй `next dev`/`next start` на тот же каталог проекта (внутренний lock по каталогу, не по порту) — второй сервер собирается в изолированный `NEXT_DIST_DIR=.next-backend-down` (новая опция в `next.config.ts`), чтобы работать одновременно с обычным сервером на 3000. Добавлено в `.gitignore`/`eslint.config.mjs` (генерируемый каталог, как и `.next/`).
  - **Статический аудит `lib/api.ts`:** все 21 функция делятся на два паттерна без исключений — «безопасные» (списки/справочники: `fetchNews`, `fetchSettings`, `fetchMenu` и т.д. — ловят всё, включая 5xx/сеть, возвращают пустой дефолт) и «бросающие» (6 функций по одному элементу через slug: `fetchNewsItem`, `fetchInstruction`, `fetchProject`, `fetchAnnouncement`, `fetchAlert`, `fetchPage` — `null` на 404, `throw` на остальном).
  - **Реальный прогон `backend-down` нашёл 2 расхождения с планом:**
    1. **`/announcements` — единственный список без пустого состояния.** У news/guides/documents/projects/alerts/map — у каждого либо явная плашка «ничего не найдено», либо (map) фильтр-специфичная. У announcements `<div role="feed">{items.map(...)}</div>` рендерился пустым без всякого текста при `data=[]` — ровно случай из критерия приёмки («ни одной страницы с пустым `<main>` без объяснения»). Добавил `AnnouncementsContent.empty` (по локали) + условный рендер в `AnnouncementsFilter.tsx`. Текст сознательно нейтральный («Объявления не найдены», не «пока не опубликованы») — лента пустеет и от простоя CMS, и от клиентского фильтра по виду (Вакансии/Тендеры), который может не дать совпадений на непустых данных.
    2. **Все 6 детальных `[slug]`-страниц реально роняли неотловленный `throw` в голый "Internal Server Error" под `next start`, не в кастомный `app/[locale]/error.tsx`.** Это и был неверный вывод статического анализа — по коду `error.tsx` формально оборачивает `page.js` в React error boundary (подтверждено в `node_modules/next/dist/docs/.../error.md`: «error.js wraps a route segment... does not wrap layout.js above it»), и первая попытка чтения кода заключила, что throw дойдёт до него. **Живая проверка через `curl` на `next start` (не dev — тот же результат в обоих режимах) показала иначе:** голый `Internal Server Error`, без `<html>`, без стилей, статус 500 — сигнатура сбоя на уровне HTTP-обработчика Next, до какого-либо React-рендера, а не отрисованная кастомная граница. Причина — гонка `generateMetadata` и тела страницы: обе функции независимо дёргают тот же бросающий fetch (`fetchNewsItem` и т.п.) на раннем этапе подготовки маршрута, до того как стриминг вообще успевает закоммитить статус ответа; на этом этапе исключение — не «ошибка React-дерева», а фатальный сбой подготовки маршрута, error boundary ещё не в игре.
  - **Фикс не полагается на error.tsx вообще** — вместо этого явный try/catch:
    - В `generateMetadata` каждой из 6 страниц: `await fetchX(slug, loc).catch(() => null)` — сбой сети трактуется как «нечего сказать про метаданные», как и `null` (404); дальше решает уже тело страницы.
    - В теле каждой из 6 страниц: `try { X = await fetchX(...) } catch { return <PageShell...><FetchErrorFallback locale={locale} /></PageShell>; }` — новый `components/public/FetchErrorFallback.tsx`, тот же текст (`getUiStrings(locale).errorPage`), что и в `app/[locale]/error.tsx`, но рендерится как **обычный успешный возврат** страницы, а не через исключение — гонка с generateMetadata больше не имеет значения, бросать вообще нечему. Это прямо соответствует официальной классификации Next (`getting-started/error-handling.md`, раздел «Handling expected errors» → «Server Components»): падение запроса — ожидаемая ошибка, обрабатывается явно, не через throw/error.tsx (тот — для непредвиденных багов).
  - Новый `tests/e2e/graceful-degradation.spec.ts` (10 тестов, только против `backend-down`): все проверенные списки показывают текст вместо пустоты; главная рендерится (шапка/нав/подвал — статичный фолбэк, см. B-5-смежную находку ниже) при пустых секциях; детальная страница (`news/[slug]` с несуществующим slug) показывает `FetchErrorFallback`, не падает; полностью статичные страницы (`symbols`) не задеты вообще.
- **Проверено:**
  - `npx tsc --noEmit`, `npx eslint .` — чисто (после добавления `.next-backend-down/**` в игнор ESLint — иначе линтер разбирал собранный Turbopack-вывод как исходники и падал на `require()`/`@ts-ignore` в чужом сгенерированном коде).
  - `npx vitest run` — 39/39 (логику `lib/api.ts` не менял).
  - `npx playwright test` (оба проекта, полный прогон) — **34 passed, 1 skipped** (тот же known skip из B-1), **0 failed**. `backend-down` — 10/10.
  - `npm run build` (обычный `.next`, не изолированный) — чисто, все 108 страниц.
  - Побочно подтвердил (не специально искал) утверждение из B-4/B-5 «шапка/подвал — уже статичный фолбэк»: `PageShell` дёргает только `fetchSettings`/`fetchMenu` (оба безопасные), при `null`/пустом ответе `PublicHeader`/`PublicFooter` берут статичный массив из словаря локали (`cmsNav.length > 0 ? cmsNav : staticNav` и т.п. built-in `||`-фолбэки на каждое поле) — заявление подтвердилось, правок не потребовало.
- **Решения:**
  - Не стал чинить это через `app/[locale]/error.tsx` (например, экспериментируя с `unstable_retry` — новый проп Next 16.2, добавлен НЕ вместо `reset`, а в дополнение, `reset` по-прежнему валиден согласно `error.md`) — причина сбоя не в имени пропа, а в том, что error boundary как механизм в принципе не успевает включиться на этом этапе жизненного цикла запроса. Явный try/catch в самих страницах и корректен, и прямо рекомендован документацией Next для этого класса ошибок.
  - Ручной обход «выключить CMS и пройти 20×3 руками», предписанный шагами плана, заменил детерминированным e2e с настоящим (не смоканным вручную) недоступным бэкендом — то же самое покрытие, но воспроизводимо и не требует держать CMS выключенным во время работы над остальными задачами плана.
  - `NEXT_DIST_DIR` — новая опция `next.config.ts`, но не новая зависимость и не новый корневой каталог по существу (генерируемый build-артефакт, не исходники) — не потребовала обоснования как «настоящая» новая зависимость по правилам сессии.
- **Коммит:** `khf-site-front@48c5f43`

---

## C-1 · Решение по 5 статическим страницам — ГОТОВО

- **Сделано:** новый `DECISIONS.md` в корне CMS-репо (план прямо требует «записать в DECISIONS», файла ещё не существовало). Решение НЕ списано с рекомендации плана вслепую — прочитал фактическое содержимое всех пяти `content.ts` (`leadership`, `structure`, `symbols`, `sos`, `sitemap`) и проверил довод по каждому:
  - `leadership`/`structure` → **перенести в CMS**, отдельными сущностями (`Leader`, `StructureUnit`), не через generic `Page`: оба файла содержат структурированные повторяющиеся записи (ФИО/звание/био замов; num/name/desc подразделений) — именно тот случай, где `Page`+rich-HTML заставил бы редактора вручную поддерживать разметку карточек (риск, о котором план явно предупреждает).
  - `symbols` → статика: текст цитирует конкретные постановления (номер, дата) — меняется только законодательным актом.
  - `sos` → статика: лендинг приложения меняется вместе с релизами самого приложения, а не независимо от фронта — «редактор правит без релиза» здесь не применимо.
  - `sitemap` → статика: собственный комментарий файла фиксирует инвариант «пути — через общий `routes`, чтобы карта не расходилась с навигацией»; ручное редактирование в CMS сломало бы ровно эту гарантию.
  - Итог совпал с рекомендацией плана, но обоснован независимо, по факту, а не переписан с формулировки задачи.
  - Заведены (не выполнены — это следующий этап работы) C-1a/C-1b с оценками (4–6 ч каждая) на саму реализацию миграции `leadership`/`structure` — модели, миграции, admin CRUD, публичный API, перевод фронта на `fetch*`. План прямо разделяет C-1 (~1 ч решение) и реализацию (~4–8 ч) — решение и реализация не одна задача.
- **Проверено:** не применимо — задача не меняла код (только `DECISIONS.md`); ничего не запускал.
- **Решения:** сама формулировка задачи — эта секция целиком про решение, см. `DECISIONS.md`.
- **Коммит:** `khf-site-cms@5e2b0dc` (только `DECISIONS.md` + эта запись, без кода фронта/CMS)

---

## C-2 · Удалить мёртвые демо-фолбэки — ГОТОВО

- **Сделано:** для каждого символа из списка плана — grep на использование ВНЕ файла определения, удаление только при нуле совпадений:
  - `getGuide`/`getArticle`/`getProjectContent` — все 3 подтверждены неиспользуемыми (единственное совпадение grep — сама сигнатура функции), удалены вместе с их бэкинг-массивами: `guides`/`articles`/`projectsContent` (объекты по slug), `fallbackArticle`, `FALLBACK_SLUG`, `guideSlugs`/`articleSlugs`/`projectSlugs`. Каждая `[slug]/content.ts` теперь содержит только типы (`GuideContent`, `Article`, `ProjectContent` и т.д.) — реальные данные приходят через `fetchInstruction`/`fetchNewsItem`/`fetchProject` (`lib/api.ts`), они и раньше не читали эти демо-фикстуры (страницы `[slug]/page.tsx` их не импортировали вовсе — подтверждено до удаления). Что осталось и почему: `getArticleUi`/`getProjectBreadcrumb` — реально импортируются в соответствующих `page.tsx`, не тронуты.
  - `app/[locale]/alerts/content.ts` — весь файл удалён: ни `alerts/page.tsx`, ни `alerts/[slug]/page.tsx` не импортируют из `./content` вообще (оба уже на `fetchAlerts`/`fetchAlert`), grep по всему репо на `alerts/content` — 0 совпадений.
  - `priority`/`catalog` в `guides/content.ts` — удалены; `guides/page.tsx` вычисляет одноимённые ПО СМЫСЛУ, но другие по имени переменные (`priorityItems`/`catalogItems`) из реальных данных `fetchInstructions()` — сами демо-экспорты `priority: PriorityTile[]`/`catalog` не читались нигде. Заодно удалён их частный тип `PriorityTile` (использовался только в них) и ставший неиспользуемым импорт `GuideItem`/`routes`. Презентационный `topicNum` — проверен отдельно (используется в `guides/page.tsx`), оставлен, как и требует план.
  - `projectStatusColors` и тон/иконки — не трогал; не нашёл ничего с таким именем в затронутых файлах (возможно, из другой части кода, не встретившейся в рамках этой задачи) — grep по репо на них не давал совпадений в файлах, которые я редактировал, значит уже не относится к демо-фолбэкам этой задачи.
  - Заголовочные комментарии в 3 отредактированных `content.ts` переписаны — старые описывали удалённую фикстурную структуру («заглушка CMS», «структура повторяет референс…»), после удаления это было бы враньём в комментарии; переписаны на «здесь только форма, данные из CMS».
- **Проверено:**
  - `npx tsc --noEmit`, `npx eslint .` — чисто.
  - `npx vitest run` — 39/39.
  - `npm run build` — чисто, все 108 страниц (номера/список маршрутов не изменились — ожидаемо, удалённый код не участвовал в рендере).
  - `npx playwright test` (оба проекта) — первый прогон поймал 1 failed на уже известном дев-only Turbopack-флейке (`/en/nonexistent`, тот же `ChunkLoadError`, что документирован в B-5/B-6) — перепрогон сразу дал 33 passed + 1 skipped, 0 failed; не регрессия от этой задачи.
  - `git diff --stat`: 5 файлов, **+9 −1150** — по существу только удаления (9 строк — переписанные шапки-комментарии, не новый код), соответствует критерию приёмки плана дословно.
- **Решения:** не пошёл искать «презентационные мапы» (`projectStatusColors`, tone/icon), упомянутые в плане как «оставить» — раз их не оказалось среди символов, которые я фактически трогал в этой задаче, останавливать их отдельным поиском по всему репо не требовалось критерием приёмки (он про diff, не про подтверждение существования всего перечисленного в плане).
- **Коммит:** `khf-site-front@ac0fada`

---

## C-3 · Аудит переводов перед запуском — ГОТОВО

- **Сделано:**
  - Перед реализацией — разведка существующей инфраструктуры (не с нуля): в проекте уже есть `App\Concerns\TracksTranslationCompleteness` (трейт, `languageCompleteness()` по всем `$translatable`-полям, локали `tg/ru/en`) и `WorkflowService::guardRequiredTranslations()` — блокирует публикацию ОДНОЙ записи, если обязательная локаль (`Setting.languages.require_translation`, по умолчанию `['tg','ru']` — `en` НЕ обязателен) не 100% заполнена. Это per-record enforcement на момент публикации; новая команда — агрегированный обзор по ВСЕМУ уже опубликованному сразу, ради которого план и просит C-3 («результат — редактору»).
  - Новая `php artisan content:translation-report` (`app/Console/Commands/ContentTranslationReport.php`): 3 таблицы — (1) 7 workflow-моделей (alerts/announcements/documents/instructions/news/pages/projects), считает только публично видимый статус (`ContentStatus::isPublic()`); (2) 6 справочных моделей без workflow (categories/districts/home_blocks/menu_items/regions/tags) — считаются все записи (у `home_blocks`/`menu_items` — только `enabled=true`); (3) ключи `Setting` с суффиксом `_tg/_ru/_en`, сгруппированные по базовому имени (`name_ru`+`name_tg`+`name_en` → одна «семья» `org.name`) — ключи без суффикса (телефоны, URL, булевы) не переводимые, пропускаются. В конце — секция «обязательное к запуску» (меню + инструкции населению + настройки групп `org`/`contacts`/`footer`, дословно по списку из плана), явно называет незакрытые пробелы, а не просто печатает таблицы.
  - **Document — особый случай, не general-purpose путь.** У Document `$translatable = ['name']` — это второстепенная подпись, а реальный «переведён ли документ» вопрос — это отдельная per-language файловая медиа-коллекция (`fileLanguages()`: `file_tg`/`file_ru`/`file_en`), совершенно другой механизм, не `HasTranslations`. Команда считает локаль документа полной только если ОБА условия выполнены (переведено название И загружен файл) — иначе полностью переведённое название с отсутствующим файлом на этом языке маскировало бы реальный пробел.
  - **Обнаружил и закрыл реальный пробел по ходу, не выдумал искусственно:** `Document` был единственной из 7 workflow-моделей БЕЗ `languageCompleteness()` вообще (не использовал трейт и не имел свою реализацию) — значит `guardRequiredTranslations()` для документов сейчас **вообще не проверяет** переводы при публикации (`method_exists` в guard тихо это пропускает). Не стал чинить сам guard (это поведенческое изменение публикации — не задача аудита), но фиксирую как отдельный найденный пробел ниже, а свой репорт обходит эту дыру собственной, более строгой логикой (см. выше).
  - Добавил `TracksTranslationCompleteness` в 6 справочных моделей, у которых его не было (`Category`, `District`, `HomeBlock`, `MenuItem`, `Region`, `Tag`) — они уже используют `HasTranslations`, трейт просто не был подключён (5 других моделей его уже используют — `Announcement`/`Instruction`/`News`/`Page`/`Project`). Чисто аддитивно: ни у одной из этих 6 нет workflow, поэтому `guardRequiredTranslations()` их не касается и это не меняет никакого существующего поведения — только даёт им единообразный `languageCompleteness()` вместо специального обходного кода в самой команде (который иначе не проходил PHPStan: вызов `getTranslations()` на параметре, типизированном как общий `Model`, без trait/interface-гарантии — не стал глушить через `@phpstan-ignore`/`@var`-каст, раз задача явно это запрещает, а добавление трейта было настоящим, более простым исправлением, а не обходом).
  - Новый `tests/Feature/ContentTranslationReportTest.php` (5 тестов): считает только `published`, не `draft`; корректно ловит документ с переведённым именем, но без файла; справочные модели без workflow + `enabled=false` не считается; `Setting`-семьи по суффиксу + нетранслируемые ключи игнорируются; пустая БД — «всё переведено».
- **Проверено:**
  - `vendor/bin/pint --dirty --format agent` — чисто.
  - `composer types:check` (Larastan) — 0 новых ошибок; 5 оставшихся — старый трекнутый долг `SearchController.php` (задача #26 в бэклоге, не эта задача).
  - `php artisan test --compact` — **347/347** (было 342 на входе сессии; +5 новых).
  - Ручной прогон `php artisan content:translation-report` на реальной dev-БД (не только в тестах) — команда работает и находит настоящие, не выдуманные для теста пробелы: **все 11 опубликованных `instructions` не переведены НИ на одну локаль** (включая `ru`!). Причина — почти наверняка сид-данные создавались напрямую в статусе `published`, минуя реальный workflow-переход, поэтому `guardRequiredTranslations()` (который срабатывает только на самом переходе статуса) их не проверял. Не стал чинить сидер/данные — вне рамок C-3 (задача — построить аудит-инструмент, не лично перевести весь контент; результат явно adressован редактору per план).
- **Решения:**
  - Не стал делать `guardRequiredTranslations()`-подобную БЛОКИРОВКУ публикации по итогам этого отчёта — команда именно REPORT (только печатает), поведение публикации не меняет; это соответствует и названию команды, и критерию приёмки плана («печатает таблицу»), а не «блокирует релиз».
  - Настройки группирую по суффиксу `_tg/_ru/_en` эвристически (а не по жёстко заданному списку ключей) — устойчиво к появлению новых переводимых настроек без правки команды; нашёл через это реальный пробел (`general.site_title` без `_tg`/`_en`), не заданный заранее.
- **Коммит:** `khf-site-cms@7ba7fd6`

---

## D-1 · Общий rate limit публичного API — ГОТОВО

- **Сделано:**
  - Разведка перед реализацией: `search` (`throttle:60,1`) и `submissions` (`throttle:10,1`) уже были защищены точечными инлайн-throttle на своих маршрутах — план прямо просит их не трогать («оставить более строгие»). Остальные ~15 публичных GET-маршрутов (`home`, `settings`, `menu`, `news`, `pages`, `categories`, `instructions`, `documents`, `projects`, `announcements`, `alerts`, `regions`) были вообще без лимита — вот это и есть пробел D-1.
  - Именованный лимитер `api-public` в `AppServiceProvider::configureRateLimiting()` (тот же паттерн, что уже используется для `login` в `FortifyServiceProvider`): `Limit::perMinute(120)->by($request->ip())`.
  - Применил не на весь groupware `api` (в `bootstrap/app.php`), а точечно — обернул `routes/api.php` в `Route::middleware('throttle:api-public')->group(...)`, **сознательно исключив `health`/`ready`**: это не было explicitly в плане, но инфраструктурная проверка здоровья, поймавшая 429 от прикладного лимита, может ложно вывести инстанс из ротации балансировщиком/оркестратором — посчитал это достаточно важным операционным риском, чтобы решить самостоятельно, не дожидаясь отдельного тикета.
  - `search`/`submissions` остаются внутри обёрнутой группы — их собственный более строгий throttle просто срабатывает раньше общего 120/мин (оба лимитера действуют одновременно, побеждает тот, что исчерпался первым) — ничего в их поведении не изменилось.
  - Новый `tests/Feature/Api/RateLimitTest.php` (3 теста): заголовки `X-RateLimit-Limit`/`Remaining` на обычном ответе; 429 + `Retry-After` на 121-м запросе подряд; `health` НЕ лимитируется даже на 125 запросах подряд. `Cache::flush()` в `beforeEach` — драйвер кэша тестов (`array`, `phpunit.xml`) живёт на весь процесс, не сбрасывается между тестами сам по себе (в отличие от БД через `RefreshDatabase`), иначе бюджет лимитера «утекал» бы между тестами с одним и тем же тестовым IP.
- **Проверено:**
  - `vendor/bin/pint --dirty --format agent`, `composer types:check` — чисто (5 оставшихся PHPStan-ошибок — старый долг `SearchController.php`, не эта задача).
  - `php artisan test --compact` — **350/350** (было 347 на входе задачи).
  - **Живая проверка, не только тест-сьют:** `curl` к работающему `php artisan serve` (порт 8848) подтвердил заголовки на реальном сервере без перезапуска (`X-RateLimit-Limit: 120`), затем — прогнал ПОЛНЫЙ e2e-сьют фронта (`npx playwright test`, оба проекта, 34 теста) против этого живого лимитированного CMS: **0 упавших, 0 случаев 429** — прямая проверка требования плана «фронт не ловит 429 при массовой ревалидации» на реальном трафике, а не предположение.
- **Решения:**
  - 120/мин — взял ровно значение-пример из формулировки плана, не придумывал своё: реальных production-паттернов трафика ещё нет (сайт не запущен), калибровать точнее не по чему; порог легко поднять одной строкой в `configureRateLimiting()`, когда появятся реальные данные.
  - Аллоулист по IP для фронтового ISR-сервера НЕ заводил — живая проверка (см. выше) показала, что в текущем виде лимита достаточно с большим запасом (полный e2e-прогон близко не подошёл к 120/мин на shared localhost-IP, где фронт, тесты и curl-проверка делили один и тот же IP-бюджет). Если прод-трафик после запуска покажет обратное — план сам называет аллоулист как штатный запасной вариант, делать его сейчас без данных было бы гаданием.
  - Health-check исключение — единственное решение в этой задаче, не буквально продиктованное планом; обоснование см. выше в «Сделано».
- **Коммит:** `khf-site-cms@6c343d7`

---
