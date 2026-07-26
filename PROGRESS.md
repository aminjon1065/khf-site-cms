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
- **Коммит:** (будет проставлен в записи следующей задачи)

---
