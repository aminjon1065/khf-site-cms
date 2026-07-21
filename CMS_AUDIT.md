# Аудит и статус реализации CMS КЧС

**Первичный аудит:** 20 июля 2026 года  
**Последнее обновление:** 20 июля 2026 года  
**Проект:** Laravel 13 + Inertia 3 + React 19 + Tailwind CSS 4  
**Объём:** backend, frontend, маршруты, роли и права, workflow, публичный API, миграции, тесты, статический анализ и production build.

## Итог

Все программно устранимые блокеры P0 из первичного аудита закрыты и покрыты регрессионными тестами. CMS больше не содержит известных обходов региональной авторизации, центр согласования проверяет доступ к объекту, права таксономии разделены, бизнес-таймзона настроена, 2FA включена, а полный CI проходит.

Реализована также основная часть P1: единый workflow для всех редакционных типов, корректная плановая публикация, полезные уведомления, безопасное сохранение меню и медиабиблиотеки, транзакции, ограниченная пагинация, профиль и security settings, публичный поиск, operational control center, контакты и health/readiness endpoints.

**Текущий вывод:** кодовая база готова к staging и повторной приёмке. Production-запуск всё ещё должен быть заблокирован до настройки и проверки внешней инфраструктуры: backup/restore, постоянно работающих queue worker и scheduler, monitoring, реальных каналов оповещения и ручной проверки ролей. Эти пункты нельзя достоверно реализовать или подтвердить только внутри репозитория.

## Результаты итоговых проверок

| Проверка | Результат |
|---|---|
| `composer ci:check` | **PASS** |
| Pest | **PASS:** 309 tests, 1093 assertions |
| PHPStan / Larastan | **PASS:** 0 errors |
| Laravel Pint | **PASS** |
| ESLint | **PASS** |
| Prettier | **PASS** |
| TypeScript `tsc --noEmit` | **PASS** |
| Production build Vite | **PASS:** 2318 modules |
| `composer validate --strict` | **PASS** |
| Генерация Wayfinder с form variants | **PASS** |
| Git whitespace check | **PASS** |
| Composer dependency audit | **PASS:** advisories не найдены |
| NPM dependency audit | Не выполнен: security policy среды запретила передачу dependency metadata во внешний registry; выполнить в доверенном CI |
| Browser/E2E-приёмка всех ролей | Требуется на staging |

Примечание по локальному окружению: системный Homebrew Node 25 повреждён из-за отсутствующей `libsimdjson`; CI успешно выполнен на Node 22, указанном в deployment-требованиях. Production build сохраняет неблокирующие предупреждения о размере чанка RichEditor и optional-пакете `fontaine`.

## Что реализовано по итогам аудита

### P0 — закрыто

- **Региональная авторизация.** Добавлены единые scopes для редакционного контента и предупреждений, объектные policy-проверки, запрет общенациональных/чужих территорий и проверка соответствия районов регионам. Scope действует также в dashboard, календаре, задачах, nav badges и control center.
- **Центр согласования.** Добавлена авторизация очереди и выбранного объекта; объект вне разрешённой очереди возвращает 404/403.
- **Таксономия.** Создание, изменение и удаление определяются по diff и отдельно проверяют `taxonomy.create`, `taxonomy.edit`, `taxonomy.delete`; сохранение выполняется транзакционно.
- **Время.** `APP_TIMEZONE=Asia/Dushanbe`, scheduler и active-alert logic учитывают границы времени и `starts_at`.
- **CI.** Исправлена типизация media conversions, отформатирован frontend, устранены все ошибки Larastan.
- **Защита аккаунтов.** Включён Fortify 2FA с подтверждением пароля, setup/challenge/recovery flows, страницы профиля и безопасности. Для привилегированных ролей добавлена политика обязательного 2FA с датой включения из settings.

### P1 — реализовано полностью или в безопасном минимальном объёме

- **Workflow.** Project, Announcement и Page добавлены в `ContentTypes`, очередь, badges и уведомления. Обычные переходы больше не используют `force: true`; добавлен fallback выбора согласующих и полный набор проверок переходов.
- **Плановая публикация.** Alert и News имеют рабочий schedule. Для типов без scheduler ложный режим `schedule` удалён из backend validation и frontend.
- **Уведомления.** Payload содержит URL материала, отправка выполняется через queue после commit, добавлена полная страница уведомлений и Wayfinder-маршруты.
- **Меню.** Сохранение обёрнуто в транзакцию, а не переданные дочерние пункты больше не удаляются. Полный nested editor остаётся отдельной UX-задачей.
- **Обращения.** Публичный API валидирует и сохраняет `region_id`; tracking, внутренние комментарии и CMS-фильтры сохранены. Расширенный citizen workflow перечислен в backlog ниже.
- **Медиабиблиотека.** Update использует `media.edit`, picker отдаёт только library-owned изображения, inline upload принимает только изображения.
- **Транзакции.** Синхронизация Taxonomy, Menu, HomeBlock, Region и сохранение редакционного контента выполняются атомарно.
- **Pagination.** CMS `per_page` ограничен; публичные Documents, Instructions, Projects, Announcements, Pages, Categories и Regions пагинируются с верхней границей 50.
- **Локаль интерфейса.** UI-контракт приведён к реально поддерживаемым `ru`/`tg`; английский сохранён как язык контента API.
- **Целостность Page.** Запрещены self-parent и циклы через потомков.

### Дополнительные улучшения

- Добавлен публичный `GET /api/v1/search` с единым результатом по основным типам контента и пагинацией.
- Добавлены `GET /api/v1/health` и `GET /api/v1/ready`: БД, writable storage и heartbeat scheduler; readiness также сообщает queue connection.
- Добавлены рабочие CMS-страницы `/control`, `/contacts`, `/notifications`, `/settings/profile`, `/settings/security`; generic stub-маршрут удалён.
- Расширен audit log для регионов, районов, категорий, тегов, меню, блоков главной, настроек и media assets. Значение секретной настройки не попадает в лог.
- Activity dashboard скрыт без `users.view`; dashboard закрыт для аккаунтов без CMS permissions.
- Обновлены metadata Composer, `.env.example`, `DEPLOYMENT.md`; старый `CMS_IMPLEMENTATION_PLAN.md` отмечен как архивный baseline.

## Актуальный статус модулей

| Модуль | Статус | Что осталось |
|---|---|---|
| Предупреждения | Основной CMS/API workflow готов | CAP и реальная доставка RSS/SMS/SOS; staging-проверка срочной публикации |
| Новости | Основной CRUD/workflow/API готов | Кэширование публичных ответов, browser acceptance |
| Инструкции | Основной CRUD/workflow/API готов | Preview/revisions при необходимости |
| Документы | Основной CRUD/workflow/API готов | Preview/revisions и нагрузочная проверка файлов |
| Проекты | Основной CRUD/workflow/API готов | Preview/revisions; планирование намеренно не заявлено |
| Объявления | Основной CRUD/workflow/API готов | Preview/revisions; планирование намеренно не заявлено |
| Страницы | CRUD/workflow и защита дерева готовы | Preview, revisions, trash/restore UI |
| Регионы и районы | CRUD/API, транзакции и аудит готовы | Проверка бизнес-актуальности контактов и счётчиков |
| Обращения граждан | Базовый intake и CMS workflow готовы | Вложения, уведомления заявителю, публичный статус, retention/PII policy |
| Медиабиблиотека | Основной безопасный сценарий готов | Региональные правила повторного использования, антивирус для документов |
| Категории и теги | Готово | Дополнительный granular UI — по необходимости |
| Меню | Безопасное сохранение готово | Полноценный nested drag-and-drop editor или отказ от вложенности |
| Главная страница | Базовое редактирование готово | Preview и расширенные publication rules |
| Настройки сайта | Частично готово | UI для integrations, backup, emergency services и части footer |
| Пользователи | CRUD, профиль и пароль готовы | Решение, нужны ли self-service email changes и email verification |
| Роли | Просмотр матрицы готов | Редактирование ролей, только если оно разрешено требованиями безопасности |
| Авторизация и 2FA | Готово на уровне приложения | Staging-проверка TOTP/recovery и регламент восстановления доступа |
| Согласование | Все редакционные типы подключены | SLA/escalation и замещение согласующего — продуктовая задача |
| Уведомления | Drawer, ссылки, queue и полная страница готовы | Фильтры/архивирование и внешние каналы при необходимости |
| Dashboard | Авторизация и региональная изоляция готовы | Расширение метрик на все типы контента |
| Журнал действий | Существенно расширен | Экспорт/retention и централизованное хранение логов |
| Публичный API | CRUD endpoints, пагинация, поиск и health готовы | Подписки, OpenAPI, rate limits, cache/invalidation |
| Центр контроля | Рабочая карта и список активных предупреждений готовы | Real-time transport и интеграция с внешними оперативными системами |
| Экстренные контакты | Рабочая страница на основе settings/regions | Отдельный управляемый справочник, если нужен бизнесу |

## Оставшийся backlog

### Обязательно до production — инфраструктура и приёмка

1. Настроить Supervisor/systemd для `queue:work`, cron для `schedule:run`, production mail и фактические transport credentials.
2. Настроить автоматические backup, проверить восстановление БД и файлов на отдельном staging-контуре.
3. Подключить monitoring к `/api/v1/health` и `/api/v1/ready`, централизованные логи и alerting дежурной команды.
4. Выполнить Composer и NPM audit в доверенном CI с сетевым доступом и зафиксировать результат в release evidence.
5. Провести ручную приёмку матрицы ролей, 2FA/recovery, desktop/mobile UI и критического alert flow.
6. Провести security review/penetration test и проверить production cookie, TLS, CORS, trusted proxies и rate limits.

### Требуют продуктовых требований или внешних интеграций

1. **CAP/RSS/SMS/SOS delivery.** Нужны спецификации, credentials, retry/idempotency, delivery status и ответственные каналы.
2. **Подписки граждан.** Нужны правила согласия, подтверждения контакта, география подписки, unsubscribe и политика персональных данных.
3. **Расширенный workflow обращений.** Вложения, антивирус, номер/подтверждение заявителю, внешний статус, уведомления и retention.
4. **Revisions/preview/trash restore.** Нужны правила версионирования, сравнения, восстановления и publication preview.
5. **Структура руководства КЧС.** Нужно решить, является ли она отдельной сущностью или набором обычных страниц.
6. **Редактируемый справочник экстренных служб.** Текущая страница использует централизованные настройки и регионы; отдельный CRUD нужен только при подтверждённой модели данных.

### Технический P2

- Перевести оставшиеся hardcoded frontend URL на Wayfinder. Новые и security-critical страницы уже используют типизированные маршруты.
- Добавить cache/invalidation для settings, home и публичных справочников.
- Перевести поиск с ограниченного application-level поиска на полнотекстовый индекс при росте объёма данных.
- Расширить dashboard на все workflow-типы.
- Разбить крупные `alerts/wizard.tsx`, `RichEditor.tsx`, `projects/form.tsx`; динамически загружать RichEditor и уменьшить чанки.
- Установить optional `fontaine` или отключить `optimizedFallbacks`, если предупреждение сборки нежелательно.
- Добавить browser/component/E2E-тесты форм, RichEditor, media picker, меню, responsive sidebar и workflow.
- Добавить OpenAPI, API contract tests и явные правила rate limiting.
- Определить DB constraints для `parent_id` Page/Menu с учётом стратегии удаления.

## Критерии production release

- [x] Все программные P0 закрыты негативными feature-тестами.
- [x] `composer ci:check`, production build и `composer validate --strict` проходят.
- [x] Regional editor не читает и не изменяет чужой контент через CRUD, dashboard или control center.
- [x] Все текущие workflowable-типы доступны в очереди согласования.
- [x] Scheduler и alert activity проверены в `Asia/Dushanbe`.
- [x] CMS-заглушки удалены из маршрутов или заменены рабочими страницами.
- [x] 2FA, profile и security settings реализованы и покрыты feature-тестами.
- [ ] Queue worker, scheduler, mail, storage и monitoring проверены на staging.
- [ ] Backup/restore подтверждён контрольным восстановлением.
- [ ] Реальные внешние каналы оповещения согласованы и протестированы либо официально исключены из первой версии.
- [ ] Выполнены dependency audits в release CI.
- [ ] Выполнена ручная приёмка всех ролей на desktop/mobile и повторный security review.

---

Этот документ отражает состояние репозитория после реализации аудита. Он не заменяет penetration test, нагрузочное тестирование, disaster-recovery drill и проверку production-инфраструктуры.
