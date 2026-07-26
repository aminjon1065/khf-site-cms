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
