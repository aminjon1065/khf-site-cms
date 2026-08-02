# План максимальной оптимизации KHF: публичный сайт + CMS

**Дата аудита:** 27 июля 2026

**Область:** `khf-site-cms` и соседний `khf-site-front`

**Статус:** исполнимый технический план, дополняющий `PROJECT_PLAN.md`

## 1. Цель

Система должна стать быстрой не только в одном локальном Lighthouse-запуске, но и для реальных посетителей на слабых телефонах и нестабильном мобильном интернете, а CMS — понятной сотруднику без технической подготовки.

Целевое состояние:

- публичный сайт стабильно получает **99–100** в лабораторном Lighthouse на контрольных маршрутах;
- Accessibility, Best Practices и SEO — **100**;
- реальные Core Web Vitals проходят оценку Google на 75-м перцентиле;
- оригиналы изображений сохраняются неизменными, а фронт никогда не загружает их без необходимости;
- превью, карточки, hero/LCP и rich-text получают подходящий размер, формат и placeholder;
- публикация контента не ждёт обработки картинок или внешнего webhook;
- обычный редактор может создать, проверить, перевести, опубликовать и при необходимости восстановить материал без знания терминов Laravel, SEO, URL, MIME или workflow;
- производительность и доступность защищены автоматическими бюджетами в CI.

> **Важно:** Google прямо указывает, что 100 баллов — сложная и нестабильная лабораторная оценка. Она зависит от версии Lighthouse, сервера, сети, устройства и содержимого. Поэтому договорённость такая: 99–100 — CI-бюджет для фиксированного стенда и набора страниц; продуктовая гарантия — зелёные Core Web Vitals в реальных данных.

## 2. Что проанализировано

### CMS

- Laravel 13.20, PHP 8.5, Inertia 3, React 19, Tailwind 4, Vite 8.
- 137 собственных маршрутов, включая 25 публичных API-маршрутов.
- Около 46 900 строк PHP/TypeScript/CSS в `app`, `database`, `routes`, `resources/js` и `tests`.
- 21 модуль админки, workflow, права, 2FA, журнал действий, переводы, медиатека и webhook ревалидации.
- Схема БД, индексы, основные Eloquent-запросы, shared Inertia props, кэширование, очереди и production build.
- Текущий production build CMS проходит, но содержит тяжёлые чанки:
  - `RichEditor`: **515,67 KB minified / 162,08 KB gzip**;
  - общий `utils`: **342,59 KB / 107,72 KB gzip**;
  - CSS приложения: **113,13 KB / 19,65 KB gzip**.
- Тестовый baseline: **338 тестов, 320 passed, 11 failed**. Красный CI — блокер любой оптимизации.

### Публичный фронтенд

- Next.js 16.2.10 App Router, React 19, Tailwind 4.
- Около 14 000 строк TypeScript/TSX/CSS.
- 20 публичных разделов × 3 локали, SSG/ISR, metadata, sitemap, robots и webhook ревалидации.
- TypeScript и ESLint проходят.
- Production build компилируется. При недоступной CMS большая часть `fetch*` возвращает пустые данные, поэтому возможен формально успешный, но фактически пустой production build.
- Текущий PageSpeed/Lighthouse score не зафиксирован: для честного baseline нужен доступный production-like стенд с CMS, реальными изображениями, CDN и HTTP-кэшированием. До этого нельзя утверждать, что 99–100 уже достигнуты.
- На фронте найдено **10 обычных `<img>`** и ни одного использования `next/image`.
- `PublicHeader.tsx` — глобальный client component на 607 строк; всего 16 client components.
- Наиболее крупные статические JS-чанки текущей сборки: примерно **72,6 KB**, **39,5 KB** и **38,6 KB gzip**. Точный first-load JS по каждому маршруту нужно закрепить в CI-трассировке.
- Глобальный CSS: около **53,4 KB / 11,1 KB gzip**.
- Подключены две семьи Fira Sans, пять начертаний и три unicode subset — это создаёт много файлов шрифтов.

### Медиа

Уже сделано правильно:

- используется Spatie Media Library;
- оригинал не перезаписывается;
- есть закрытый диск для неопубликованного контента;
- есть `sm/md/lg` и `srcset`;
- rich-text сохраняет `srcset` и `sizes`;
- клиентское кадрирование сохраняет новый asset;
- SVG запрещён в пользовательских загрузках;
- HTML проходит серверный sanitizer.

Текущие ограничения:

- варианты 480/960/1600 создаются синхронно в HTTP-запросе;
- все производные принудительно JPEG, даже если исходник PNG/WebP;
- нет AVIF, отдельного WebP fallback, LQIP/blur placeholder и dominant color;
- API отдаёт плоские `image` и `image_srcset`, но не отдаёт width/height/aspect ratio/bytes;
- cover, rich-text и CMS-thumbnail используют одну матрицу размеров;
- публичный Next всё равно рендерит обычный `<img>`;
- в медиатеке нет технического статуса `processing/ready/failed`;
- `alt` необязателен;
- вставленный HTML хранит конкретные URL. Смена домена/CDN может сломать старый контент;
- обработка изображения на canvas создаёт новый JPEG, но происхождение и рецепт редактирования не сохраняются;
- публикация перемещает оригиналы и conversions между дисками; на S3 это может стать дорогой операцией copy/delete.

## 3. Неподвижные критерии качества

### 3.1. Lighthouse

Контрольные страницы:

1. `/ru`, `/tj`, `/en`;
2. список новостей;
3. детальная новость с hero и двумя изображениями в body;
4. карта;
5. документы;
6. контакты с формой;
7. самый тяжёлый проект;
8. 404 и error state.

Для каждого маршрута запускать минимум три Lighthouse-прогона на production build. В CI оценивать медиану, а для LCP/CLS дополнительно худший прогон.

| Метрика | Блокирующий бюджет |
|---|---:|
| Performance mobile | ≥ 0,99 |
| Performance desktop | 1,00 |
| Accessibility | 1,00 |
| Best Practices | 1,00 |
| SEO | 1,00 |
| FCP mobile | ≤ 1,2 s |
| LCP mobile | ≤ 1,8 s |
| TBT | ≤ 100 ms |
| CLS | ≤ 0,02 |
| Speed Index | ≤ 2,0 s |

Если 99 на всех тяжёлых страницах окажется нестабильным из-за среды Lighthouse, допустимый merge-gate — 95, а nightly/release-gate — 99. Нельзя ослаблять бюджеты без приложенного before/after отчёта.

### 3.2. Реальные Core Web Vitals

Официальный Google pass:

- LCP p75 ≤ 2,5 s;
- INP p75 ≤ 200 ms;
- CLS p75 ≤ 0,1.

Внутренние цели с запасом:

- LCP p75 ≤ 1,8 s;
- INP p75 ≤ 150 ms;
- CLS p75 ≤ 0,05;
- TTFB p75 ≤ 500 ms для HTML;
- минимум 75% визитов проходят все три CWV.

Lighthouse не измеряет реальный INP, поэтому TBT используется только как лабораторный proxy. Нужен RUM через `web-vitals` или возможности выбранной observability-платформы.

### 3.3. Вес публичной страницы

Бюджеты задаются на холодный первый вход:

| Ресурс | Бюджет gzip/transfer |
|---|---:|
| First-load JS обычного контентного маршрута | ≤ 120 KB |
| First-load JS карты | ≤ 170 KB |
| CSS | ≤ 35 KB |
| Критические шрифты первого экрана | ≤ 100 KB |
| LCP-изображение mobile | ≤ 120 KB |
| LCP-изображение desktop | ≤ 220 KB |
| Изображение карточки mobile | ≤ 60 KB |
| HTML документа | ≤ 80 KB |
| Полный initial transfer без lazy media | ≤ 350 KB |

Это стартовые бюджеты. После первой production-трассировки их можно сделать строже, но нельзя увеличивать молча.

### 3.4. CMS

- серверный p95 обычного Inertia GET ≤ 400 ms;
- p95 сохранения черновика без медиаконверсий ≤ 700 ms;
- переход между уже посещёнными разделами ≤ 1 s на среднем офисном ПК;
- первая загрузка списка без rich editor не должна скачивать Tiptap/Cropper;
- никакая публикация не падает из-за недоступного фронта;
- ни одна форма не теряет введённые данные после случайного закрытия или истечения сессии;
- основные сценарии проходят с клавиатуры и соответствуют WCAG 2.2 AA.

## 4. Целевая архитектура изображений

### 4.1. Один источник истины

Рекомендуемая цепочка:

```text
upload
  → validation + metadata
  → immutable original in private object storage
  → media job
      → auto-orient
      → strip unsafe metadata from public derivatives
      → width variants
      → AVIF + WebP + fallback
      → CMS thumbnail
      → tiny placeholder + dominant color
  → ready
  → publish derivatives to CDN
  → structured image DTO
  → next/image with custom CMS/CDN loader
```

Оригинал:

- сохраняется без изменения под UUID/content hash;
- никогда не используется в карточках и списках;
- по умолчанию не публичен;
- содержит исходный MIME, bytes, width, height, checksum и дату загрузки;
- доступен только пользователю с правом скачать оригинал;
- участвует в дедупликации.

Производные:

- имена и URL неизменяемые и версионированные;
- `Cache-Control: public, max-age=31536000, immutable`;
- генерируются очередью `media`, а не внутри POST;
- повторная генерация идемпотентна;
- не увеличиваются выше исходного размера;
- при прозрачности имеют корректный fallback, а не принудительный JPEG;
- могут быть полностью перестроены из оригинала.

### 4.2. Матрица вариантов

Не надо создавать десятки файлов без потребителя. Матрица должна совпадать с `deviceSizes/imageSizes` Next и реальными слотами дизайна.

| Назначение | Ширины | Форматы | Примерный quality |
|---|---|---|---:|
| Placeholder | 24 или 32 px | WebP/JPEG data URL | 25–35 |
| CMS grid thumb | 192, 320 | WebP | 60–70 |
| Карточка | 320, 480, 640 | AVIF, WebP, fallback | 45/70/78 |
| Article/Project | 640, 960, 1280 | AVIF, WebP, fallback | 50/72/80 |
| Hero/LCP | 960, 1280, 1600 | AVIF, WebP, fallback | 50/74/82 |
| Open Graph | 1200×630 crop | JPEG/WebP | 82 |

Quality — только исходная точка. На реальном фотонаборе выполнить визуальное сравнение и измерить SSIM/размер. Иллюстрации, логотипы и фото не должны иметь один профиль.

### 4.3. Структурированный image DTO

Плоские `image` и `image_srcset` заменить версионированным объектом:

```json
{
  "id": 42,
  "uuid": "…",
  "alt": "Спасатели во время учений",
  "caption": null,
  "width": 2400,
  "height": 1600,
  "aspect_ratio": 1.5,
  "focal_point": { "x": 0.52, "y": 0.36 },
  "placeholder": {
    "data_url": "data:image/webp;base64,…",
    "color": "#7d8791"
  },
  "sources": {
    "avif": [{ "url": "…", "width": 480, "bytes": 18420 }],
    "webp": [{ "url": "…", "width": 480, "bytes": 24110 }],
    "fallback": [{ "url": "…", "width": 480, "bytes": 31800 }]
  }
}
```

Переход сделать совместимым:

1. API временно возвращает старые и новые поля;
2. фронт переходит на новый DTO;
3. contract-тест подтверждает shape;
4. старые поля удаляются только в следующей версии API.

### 4.4. Next.js и отсутствие двойной оптимизации

Предпочтительный вариант:

- CMS/CDN владеет файлами и форматами;
- `next/image` владеет разметкой, `sizes`, lazy-loading, preload и blur placeholder;
- custom loader Next сопоставляет запрошенную ширину с готовым CMS/CDN-вариантом;
- произвольная PHP-обработка на публичном запросе запрещена.

Альтернатива для облачного CDN: CMS хранит только оригинал и несколько служебных thumbnails, а Cloudflare Images/Imgix/аналог динамически создаёт публичные размеры. Выбор делается до реализации; одновременно держать полную матрицу CMS и повторную транскодировку Next нельзя.

Для каждого `<Image>` обязательны:

- реальные `width`/`height` или `fill` с контейнером фиксированной геометрии;
- точный `sizes`;
- `alt`;
- `placeholder="blur"` и `blurDataURL` для контентных изображений;
- `preload` только у фактического LCP-изображения;
- lazy-loading для всего ниже первого экрана.

### 4.5. CMS-медиатека «как WordPress, но лучше»

Добавить:

- drag-and-drop и пакетную загрузку;
- очередь со статусами «Загружается», «Обрабатывается», «Готово», «Ошибка — повторить»;
- автоматическое чтение размеров и ориентации до сохранения;
- обязательный alt для смыслового изображения перед публикацией;
- отдельный флаг decorative для пустого alt;
- focal point вместо ручного создания копии под каждый crop;
- фильтры по типу, дате, автору, использованию, размеру и статусу;
- поиск по title, alt, caption и filename;
- предупреждение о дубле по checksum;
- «Где используется» и запрет удаления используемого файла;
- «Заменить во всех местах» только с предпросмотром и подтверждением;
- корзину и восстановление;
- parent/derived связь для отредактированных копий;
- массовое заполнение метаданных;
- статистику экономии: original bytes против public derivatives;
- фоновые команды regenerate, audit missing conversions и cleanup orphaned derivatives;
- antivirus/malware scan для PDF/Office перед публикацией.

Rich-text в перспективе должен хранить media UUID или editor JSON, а не только абсолютный URL. Рендерер резолвит актуальный CDN URL. Это позволяет менять storage/CDN без массового редактирования HTML.

## 5. Оптимизация публичного Next.js-фронта

### F-01. Не допускать «зелёный пустой build» — P0

Сейчас production build может завершиться с пустыми списками, если CMS недоступна: list-функции ловят сетевую ошибку и возвращают `[]`. На одном прогоне недоступность API, наоборот, обрушила prerender detail-маршрутов. Поведение зависит от того, какие slug были получены.

Сделать два явных режима:

- production build: предварительный `/ready`, обязательная доступность CMS и fail-fast;
- runtime/ISR: stale content + контролируемая деградация;
- preview/local: разрешён fallback, но показывается заметный diagnostic banner.

Нужны timeout через `AbortSignal`, единый error policy и агрегированный лог вместо сотен одинаковых `console.error`.

### F-02. Перевести все изображения на единый компонент — P0

Затронуты:

- `components/public/ImageSlot.tsx`;
- `components/public/PublicHeader.tsx`;
- `components/public/PublicFooter.tsx`;
- `app/[locale]/page.tsx`;
- detail news/project;
- symbols.

Создать `CmsImage` поверх `next/image`, который принимает новый image DTO и требует `sizes`. Статические логотипы импортировать статически, чтобы Next знал их геометрию.

### F-03. Уменьшить глобальный client boundary — P0

`PublicHeader.tsx` на 607 строк является client component на каждой странице. Разделить:

- серверная статическая оболочка header;
- маленький `MobileMenuButton`;
- `LocaleSwitcher`;
- `ThemeToggle`;
- `SearchToggle`.

Не передавать в client component больше данных, чем ему нужно. Карта, слайдер и фильтры остаются отдельными lazy interactive islands.

### F-04. Исправить data layer — P0

`lib/api.ts` содержит около 760 строк повторяющегося fetch/error/cache-кода.

Нужен единый типизированный клиент:

- базовый URL валидируется при старте;
- общий timeout и Request ID;
- нормализованная ошибка `not_found/unavailable/invalid_contract`;
- retry только для безопасных GET и только на transient error;
- granular cache tags;
- runtime schema validation на границе API;
- типы генерируются из OpenAPI либо общего contract package;
- list/detail имеют разную политику деградации;
- search не записывает бесконечное количество уникальных ответов в долгий cache.

### F-05. Гранулярная ISR-инвалидация — P1

Сейчас любое изменение вызывает `revalidateTag("cms", "max")`, то есть делает устаревшим весь сайт.

Целевые теги:

- `cms:shell:{locale}` — меню/настройки;
- `cms:home:{locale}`;
- `cms:news:{locale}`;
- `cms:news:{slug}:{locale}`;
- аналогично для projects, alerts, guides, pages;
- `cms:sitemap`.

Webhook передаёт тип, slug, локали и событие. Несколько быстрых изменений объединяются одной unique/debounced job.

### F-06. Убрать повторные запросы — P1

Наблюдаемые повторы:

- `PageShell` отдельно получает settings/menu на каждой ветке;
- главная получает `home`, а `home` уже содержит часть settings;
- generateMetadata и page иногда повторно запрашивают один detail/list;
- detail-страницы получают полный список для related/params.

Использовать memoization React `cache()` внутри одного render-pass и корректный Next Data Cache между запросами. Related items и список slug вынести в лёгкие API endpoints, не загружать 50 полных объектов.

### F-07. Шрифты — P1

- проверить, какие unicode ranges реально preload;
- оставить только критические начертания первого экрана;
- не preload все пять weights;
- рассмотреть один variable/self-hosted WOFF2 либо системный heading fallback;
- `font-display: swap`;
- цель — не более двух критических font requests и 100 KB transfer.

Смена шрифта — продуктовый выбор, а не скрытая техническая оптимизация.

### F-08. JS и динамические модули — P1

- карту `d3-geo/topojson` загружать только на `/map` и при приближении к viewport;
- модальные окна, share tools и тяжёлые фильтры — dynamic import;
- импортировать icon-модули tree-shakable путём;
- проверить bundle analyzer на дубли React/lucide/copy;
- carousel не должен гидратировать все изображения;
- длинные статические `content.ts` не должны попадать в client bundle;
- удалить мёртвые demo fallback из общего плана.

### F-09. HTML/CSS/rendering — P1

- зарезервировать геометрию всех media, banner, map и skeleton;
- убрать дублирующиеся CSS-правила;
- below-the-fold секциям применять `content-visibility: auto` только после проверки accessibility и anchor navigation;
- избегать тяжёлых blur/filter/mix-blend на больших областях слабых GPU;
- соблюдать `prefers-reduced-motion`;
- не создавать DOM для невидимых слайдов;
- pagination вместо рендера 50 карточек.

### F-10. Сеть и edge — P1

- CDN перед Next и media origin;
- Brotli для HTML/CSS/JS/JSON, gzip fallback;
- HTTP/2 или HTTP/3;
- immutable cache для hashed assets/media derivatives;
- короткий CDN SWR для HTML;
- keep-alive между Next и CMS;
- API/media на близком origin, чтобы не платить лишний DNS/TLS/RTT;
- security headers: CSP, HSTS, `X-Content-Type-Options`, Referrer Policy, Permissions Policy;
- не разрешать произвольные remote image hosts.

### F-11. SEO и доступность — P1

Уже есть canonical/hreflang/Open Graph/Twitter/sitemap/robots. Добавить:

- JSON-LD `GovernmentOrganization`, `NewsArticle`, `BreadcrumbList`;
- локализованный global 404;
- default OG image 1200×630;
- корректные lastModified в sitemap;
- manifest/theme-color/icons;
- axe + keyboard E2E;
- 200% zoom, contrast, landmarks, heading order, form errors;
- доступную альтернативу интерактивной карте;
- доступные статусы загрузки через `aria-live`.

### F-12. Измерение реальных пользователей — P2

Отправлять LCP/INP/CLS с:

- route template, locale, device class и connection type;
- без URL-параметров поиска и персональных данных;
- release SHA;
- отметкой cache HIT/MISS, если доступно.

Dashboard должен показывать p75 по типам страниц, а не только среднее по origin.

## 6. Оптимизация Laravel API и CMS backend

### B-01. Сначала зелёный CI — P0

До performance-рефакторинга:

- изолировать тесты от реального `localhost:3000/api/revalidate`;
- устранить workflow-регрессии;
- исключить гонку тестов с заменой Vite manifest/assets;
- запретить внешнюю сеть в tests;
- запустить `composer ci:check` зелёным.

Иначе невозможно доказать, что кэширование, очереди и media jobs не ломают публикацию.

### B-02. Redis и настоящие очереди — P0

Production:

- Redis для cache, queue и rate limiting;
- session — Redis или database по инфраструктурным требованиям;
- отдельные очереди `critical`, `default`, `media`, `webhooks`;
- постоянные workers под supervisor/systemd/container;
- scheduler каждую минуту;
- queue health: depth, oldest job, failed jobs и processing time;
- `retry_after` всегда больше максимального job timeout.

Критическое предупреждение имеет высокий приоритет, но его сохранение не должно ждать image conversions.

### B-03. Media processing в queue — P0

Убрать `nonQueued()` из production-пути. Job:

- запускается after commit;
- имеет timeout/tries/backoff;
- идемпотентен;
- не создаёт дубликаты;
- пишет статус и понятную ошибку;
- имеет `failed()` и кнопку retry;
- не блокирует сохранение материала;
- для маленького CMS-thumb может быть отдельная быстрая операция, если UX требует немедленного preview.

### B-04. Кэшировать результат, а не только ставить ETag — P0

`PublicApiResponse` сейчас вычисляет ETag после выполнения запросов и сериализации. Ответ 304 экономит transfer, но не DB/CPU.

Кэшировать готовые публичные DTO:

- settings/menu: 10–60 минут + явная invalidation;
- home: fresh 30–60 s, stale 5 min;
- regions directory: 5–30 min;
- detail опубликованного материала: до изменения;
- list: ключ из locale/page/filter/version;
- alert snapshot: короткий TTL 10–30 s и немедленная invalidation.

Использовать Laravel 13 `Cache::flexible()` для stale-while-revalidate и request memoization для повторного чтения. Cache tags допустимы только при Redis/Memcached; для database cache нужна versioned-key стратегия.

### B-05. Убрать дублирование в home API — P1

`HomeController`:

- получает alert snapshot;
- затем повторно запрашивает active alerts;
- вызывает settings;
- выполняет отдельные запросы для каждого блока.

Собрать `HomePageReadModel`:

- один предсказуемый набор запросов;
- cache per locale;
- явные версии блоков;
- `select()` только нужных колонок;
- лимиты применяются в SQL;
- snapshot не вычисляется дважды;
- query-count test фиксирует верхнюю границу.

### B-06. Уменьшить payload и количество запросов CMS — P1

Сейчас index-контроллеры обычно используют `select *`, хотя модели содержат большие translated body/JSON.

Для списков выбирать только:

- id/title/status/author/category/timestamps;
- поля сортировки и permission scope;
- thumbnail metadata;
- никакого body, seo, sections, timeline.

Отдельно:

- `NavBadges::for()` не должен загружать все review-модели в PHP и фильтровать коллекцию на каждом Inertia GET;
- notifications не должны делать list + unread count на каждом переходе;
- permissions/roles пользователя memoize;
- badges и notifications сделать deferred/once props либо получать при открытии;
- dashboard-агрегаты кэшировать и пересчитывать по событиям.

### B-07. Индексы по реальным query plans — P1

Перед добавлением выполнить `EXPLAIN` на production-подобной MySQL с реалистичным объёмом.

Кандидаты:

- news `(status, published_at, id)`;
- news `(status, show_on_home, is_pinned, published_at)`;
- alerts `(status, starts_at, ends_at, severity)`;
- announcements `(status, deadline, id)`;
- documents `(status, doc_date, id)`;
- instructions `(status, is_priority, sort, id)`;
- projects `(status, sort, published_at, id)`;
- pages `(status, parent_id, sort)`;
- menu_items `(location, enabled, sort, parent_id)`;
- workflow `(subject_type, subject_id, created_at)`;
- submissions `(status, assigned_to, created_at)`;
- media `(model_type, model_id, collection_name, order_column)`.

Не добавлять все индексы вслепую: каждый индекс замедляет запись и занимает память.

### B-08. Поиск — P1

Текущий SQL union-search достаточно хорош только на малых данных.

Этапы:

1. минимальная длина и rate limit уже обязательны;
2. индексируемые нормализованные search columns;
3. MySQL FULLTEXT по нужным языкам после проверки таджикской морфологии;
4. при росте — Meilisearch/OpenSearch как отдельное решение;
5. search analytics без хранения персональных данных;
6. cursor/page limits и максимальный `per_page`.

### B-09. Гранулярный webhook — P1

`RevalidateFrontend` уже имеет tries/backoff и HTTP timeout. Добавить:

- `ShouldBeUnique` или debounce по набору tags;
- payload с content type/id/slug/locales/event;
- dispatch after commit;
- job timeout;
- throttling exceptions;
- `failed()` с alert;
- webhook не влияет на HTTP-ответ публикации;
- тесты success, timeout, 401, 5xx, retry и disabled config.

### B-10. API logging — P1

`PublicApiResponse` пишет каждый API GET на уровне info. На нагрузке это станет I/O и storage hotspot.

- access log оставить Nginx/edge;
- приложение логирует ошибки, slow requests и sample;
- request ID протянуть CMS → Next → response;
- метрики route/status/duration/cache hit без высококардинальных slug;
- slow query log и DB listener только с порогом;
- содержимое и персональные данные не писать.

### B-11. Production Laravel/PHP — P1

Release:

```bash
composer install --no-dev --prefer-dist --classmap-authoritative
php artisan optimize
php artisan migrate --force
```

Инфраструктура:

- `APP_DEBUG=false`;
- OPcache включён и рассчитан на количество файлов;
- PHP-FPM workers рассчитаны по памяти, а не выставлены «максимально»;
- Nginx отдаёт static/media и compression;
- health/readiness проверяют DB, Redis, storage и queue, но не раскрывают секреты;
- graceful reload workers после deploy;
- backups с регулярным restore-test;
- media lifecycle и backup оригиналов;
- zero-downtime deploy и rollback.

Octane рассматривать только после профилирования. Он не заменяет кэш/индексы и добавляет требования к очистке request state.

## 7. CMS для нетехнических сотрудников

### UX-01. Главная — не аналитика, а «что мне сделать»

Первый экран по роли:

- «Создать новость/предупреждение/документ»;
- «Продолжить мои черновики»;
- «Нужно исправить»;
- «Ожидает моего согласования»;
- «Запланировано»;
- последние успешные публикации;
- состояние media/queue понятными словами.

Технические health-карточки оставить в «Центре контроля» для администратора.

### UX-02. Один редакционный шаблон

News/Page/Project/Instruction/Announcement/Document должны использовать общий `EditorialFormShell`:

- одинаковый header;
- одинаковые кнопки и их порядок;
- одна зона ошибок;
- один language switcher;
- одинаковый sticky action bar;
- одинаковый preview;
- одинаковая защита несохранённых данных.

Сейчас крупные формы 600–775 строк повторяют похожую логику, что ведёт к разному UX и большому техдолгу.

### UX-03. Простые действия вместо workflow-терминов

Редактор видит одну основную кнопку:

- «Сохранить черновик»;
- затем «Отправить на проверку»;
- согласующий — «Опубликовать» или «Вернуть с комментарием».

Статусы `translation_check`, `updated`, `completed` можно показывать в деталях, но не заставлять пользователя выбирать внутренний enum.

Перед публикацией — checklist:

- обязательные поля;
- языки;
- alt;
- SEO preview;
- дата/расписание;
- битые ссылки;
- media ready;
- вид на mobile/desktop.

### UX-04. Автосохранение и восстановление

Сейчас автосохранение есть только в edit-mode предупреждений, а Ctrl/Cmd+S — только в части форм.

Сделать для всех редакционных сущностей:

- draft autosave после паузы, а не каждые N секунд без изменений;
- индикатор «Сохранено в 14:32»;
- offline/local recovery copy;
- предупреждение при уходе;
- восстановление после истёкшей сессии;
- защита от одновременного редактирования;
- conflict screen с выбором версии;
- revision history и restore.

### UX-05. Переводы

- вкладка исходного языка закреплена рядом;
- «Скопировать из русского» как старт, но не как готовый перевод;
- прогресс считается по смысловым обязательным полям;
- публикация объясняет, какого перевода не хватает;
- preview каждой локали;
- фильтр «нужен перевод»;
- массовая очередь на перевод;
- машинный перевод, если появится, всегда помечается как непроверенный.

### UX-06. Ошибки и помощь

- ошибка рядом с полем и summary сверху;
- после submit фокус переходит к первой ошибке;
- текст объясняет действие, а не код: «Добавьте обложку минимум 1200×630»;
- примеры внутри сложных полей;
- короткая справка и 60-секундный onboarding;
- empty states с одной понятной кнопкой;
- destructive action всегда сообщает последствия и возможность восстановления.

### UX-07. Preview

Единый preview:

- выбранная локаль;
- mobile/desktop;
- реальный публичный дизайн;
- share/OG preview;
- предупреждения о fallback;
- private signed URL;
- не индексируется и не кэшируется публичным CDN.

### UX-08. Производительность интерфейса

- Tiptap загружается только на form routes;
- Cropper — только после открытия image editor;
- media modal — dynamic import;
- большие select используют серверный поиск;
- таблицы пагинируются;
- Inertia Link prefetch только для вероятных переходов;
- deferred props получают skeleton;
- optimistic update только для безопасных действий вроде read notification/reorder;
- формы не пересылают неизменившиеся огромные props.

### UX-09. Доступность CMS

- полная keyboard navigation;
- видимый focus;
- modal focus trap и возврат фокуса;
- touch targets минимум 44×44;
- drag-and-drop имеет кнопочную альтернативу;
- status не кодируется только цветом;
- contrast AA;
- zoom 200%;
- screen-reader labels для icon buttons;
- live regions для autosave/upload/publish.

### UX-10. Подтверждение простоты тестированием

Провести usability test минимум с пятью будущими редакторами.

Задания:

1. создать новость на двух языках;
2. загрузить и кадрировать обложку;
3. исправить alt;
4. запланировать публикацию;
5. найти возвращённый материал и исправить;
6. восстановить удалённое;
7. создать критическое предупреждение.

Цели:

- ≥ 90% задач без помощи;
- первая новость ≤ 10 минут;
- повторная ≤ 5 минут;
- critical alert ≤ 3 минут;
- System Usability Scale ≥ 85;
- ноль необратимых ошибок.

## 8. Автоматическая защита результата

### 8.1. Front CI

Добавить:

- lockfile policy и один package manager;
- TypeScript;
- ESLint;
- unit tests;
- contract tests;
- Next production build с обязательной test CMS;
- Playwright на ru/tj/en;
- axe;
- Lighthouse CI;
- bundle-size budgets;
- broken links;
- sitemap/robots/metadata tests;
- visual regression ключевых страниц.

### 8.2. CMS CI

- Pest;
- Larastan;
- Pint;
- frontend types/lint/build;
- query count tests;
- cache invalidation tests;
- media conversion tests на JPEG/PNG/WebP/animated GIF;
- original checksum test;
- placeholder/dimensions DTO test;
- queue retry/failure tests;
- authorization matrix;
- browser tests для common form shell/media/workflow.

### 8.3. Performance datasets

Тестировать не только маленькие seeders:

- 10 000 новостей;
- 5 000 media;
- 500 MB суммарных тестовых оригиналов в отдельном benchmark storage;
- изображения 10 MB и 8000 px;
- длинные rich-text статьи;
- 50 одновременных публичных запросов;
- 5–10 одновременных CMS-редакторов;
- недоступные Redis, storage, CMS API и frontend webhook.

Production-like performance тесты не запускать против production и не включать в обычный быстрый unit pipeline.

## 9. Порядок внедрения

### Этап 0 — измеримость и зелёный baseline, 2–4 дня

- [x] Исправить 11 CMS-тестов. **Доказательство:** `composer ci:check` — 343 Pest-теста зелёные; workflow, timezone sorting, search и Vite-without-manifest regression tests проходят.
- [x] Запретить внешнюю сеть в tests. **Доказательство:** глобальный `Http::preventStrayRequests()` и Pest-тест на `StrayRequestException`; `RevalidateFrontend` отдельно покрыт для success/disabled/sync failure/async retry.
- [x] Добавить front CI и первые smoke/E2E. **Доказательство:** GitHub Actions запускает Node 22, test CMS, TypeScript, ESLint, 24 Vitest-теста, production build, 15 Chromium smoke/axe-тестов и сохраняет Playwright-отчёт; локально весь функциональный набор зелёный.
- [x] Добавить Lighthouse CI с артефактами. **Доказательство:** merge-gate выполняет по 3 mobile-прогона на `/ru`, `/tj`, `/en`, `/ru/news`, `/ru/news/test-news`, `/ru/map` и сохраняет JSON/HTML; локальные медианы Performance — 95/95/95/97/97/96, Accessibility/Best Practices/SEO — 100. Nightly включает строгие 99 и LCP ≤ 1,8 s: текущий LCP 2,69–3,07 s остаётся явно отслеживаемым performance debt, а не скрытым ослаблением merge-порога.
- [x] Добавить bundle report. **Доказательство:** CMS и Next.js получают
  воспроизводимые JSON/Markdown-отчёты из Vite manifest и Next 16
  `route-bundle-stats`, сохраняют top routes/chunks, raw+gzip и валят CI при
  превышении четырёх/трёх зафиксированных budgets; оба отчёта загружаются как
  GitHub Actions artifacts. Текущий CMS: entry 199,4 KiB, largest JS 458,3 KiB,
  total JS 1517,1 KiB; frontend: largest route 578,3 KiB, largest chunk
  227,1 KiB, total route chunks 721,1 KiB. Budget tests 2+2 зелёные; CMS
  `composer ci:check` — 504/504, frontend — 87 Vitest, TypeScript, ESLint и
  Next production build на 52 страницах.
- [x] Включить RUM Web Vitals. **Доказательство:** Next.js 16 `useReportWebVitals` собирает только LCP/INP/CLS с настраиваемой выборкой и отправляет их через same-origin proxy; payload не содержит IP, user-agent или session ID. CMS проверяет server-only secret, rate limit, дедупликацию и нормализует slug-маршруты, хранит 35 дней и считает точный p75 по метрике/маршруту/устройству. До внедрения RUM-покрытие было 0 метрик; после — 3 CWV с exact-p75 и CMS dashboard, client chunk 9 505 bytes / 3 667 bytes gzip. Полный `composer ci:check` — 420 тестов / 2076 assertions; frontend — 41 Vitest, 19 Chromium/axe, TypeScript, ESLint и production build на 55 страниц.
- [x] Зафиксировать production-like test content. **Доказательство:**
  `benchmark:seed` создаёт в изолированных DB/storage детерминированные 10 000
  published news, длинные rich-text записи, 5 000 media и ровно 500 MiB
  оригиналов, включая SVG 8000×8000 и файл 10 MiB. Команда идемпотентна,
  ограничивает профиль и отказывается работать вне `APP_ENV=benchmark` или БД
  без `benchmark` в имени. Полный временный прогон: 10 000/5 000/524 288 000
  bytes за 0,953 s, 540 MiB on disk; 3 Pest-теста / 21 assertions, Pint и
  PHPStan зелёные.
- [x] Сделать production build fail-fast при недоступной CMS. **Доказательство:** `npm test` — 12 Node contract/assertion checks; production `npm run build` останавливается до компиляции за 0,72 с при `ECONNREFUSED` и за 0,60 с при неверном `/ready` contract; с ready CMS Next.js 16 собирает 98/98 страниц; preview build остаётся зелёным с diagnostic banner, а outage-лог сокращён с 1710 до 139 строк (6 агрегированных worker events).

**Gate:** все проверки зелёные; есть baseline по каждому контрольному маршруту.

### Этап 1 — изображения, 5–8 дней

- [x] Спроектировать DTO и API compatibility window. **Доказательство:** `image_data.version=2` добавлен параллельно legacy `image`/`image_srcset` для news/projects/instructions; публичные `sources` содержат только generated derivatives, а frontend выбирает derivative через `cmsImageSource`. Pest API contract, 3 Vitest-теста и Playwright `currentSrc`/`naturalWidth` check зелёные.
- [x] Добавить width/height/checksum/focal/status/placeholder metadata. **Доказательство:** event listeners сохраняют dimensions, bytes, MIME, SHA-256, focal point, conversion status, derivative metadata и WebP data-URL/average color placeholder; Pest доказывает metadata, неизменность SHA-256 оригинала и 3 fallback derivatives. Полный `composer ci:check` — 351 тест / 1302 assertions, PHPStan/Pint/CMS frontend checks зелёные; frontend — 27 Vitest, TypeScript, ESLint, 16 Playwright (включая axe) и production build зелёные.
- [x] Перевести conversions в queue. **Доказательство:** `nonQueued()` удалён; сохранение выполняет 0 inline-conversions и ставит job для 3 derivatives в отдельную `media` queue after commit. 6 Pest-тестов (22 assertions) доказывают создание `sm/md/lg`, неизменность SHA-256 оригинала, идемпотентный повтор без новых файлов, timeout/retry/backoff, status/error/retry и 403 без permission; полный `composer ci:check` — 349 тестов / 1277 assertions, ESLint, Prettier, TypeScript, Pint и PHPStan зелёные; Vite production build зелёный.
- [x] Создать AVIF/WebP/fallback matrix. **Доказательство:** media queue
  создаёт `sm/md/lg` в исходном fallback-формате без upscale, три WebP и три
  настоящих AVIF через проверяемый `avifenc`; DTO отдаёт ровно 3×3 публичных
  source. PNG alpha=127 и SHA-256 оригинала сохраняются. 11 media Pest /
  75 assertions зелёные.
- [x] Добавить CMS thumbnails. **Доказательство:** отдельные WebP `cms-192` и
  `cms-320` quality 65 создаются той же идемпотентной media job, а MediaPicker
  использует только их versioned `srcset`, не публичные крупные derivatives.
  Pest проверяет оба файла; CMS TypeScript, ESLint и production build зелёные.
- [x] Настроить immutable CDN cache. **Доказательство:** Media Library включает
  versioned URLs (`?v=updated_at`), Apache применяет только к
  `/storage/*/conversions/*` `public, max-age=31536000, immutable`, оригиналы
  под правило не попадают. Конфигурационный Pest и API URL contract зелёные.
- [x] Сделать `CmsImage`/custom loader. **Доказательство:** `CmsImage` требует `sizes`, использует Next.js 16 `fill`/generated `srcset`, поддерживает blur DTO; production build и browser derivative check зелёные.
- [x] Заменить 10 `<img>`. **Доказательство:** source-guard Vitest не допускает raw `<img>` в `app`/`components`; Playwright проверяет успешные `/_next/image` derivatives, 24 unit + 15 browser/axe tests проходят.
- [x] Обновить rich-text media resolution. **Доказательство:** Tiptap сохраняет
  очищаемый `data-media-id`; public API одним запросом разрешает ID в versioned
  `<picture>` с AVIF/WebP/fallback `srcset`, `sizes`, lazy loading и decoding,
  не подставляя original. CMS sanitizer и detail API покрыты Pest; 29 news
  regression tests / 115 assertions зелёные.
- [x] Добавить media audit/regenerate. **Доказательство:** `media:audit`
  завершается ошибкой при отсутствующем original/любом из 11 derivatives, а
  `--regenerate` безопасно ставит повреждённые изображения в отдельную
  `media` queue. Два command Pest проверяют failure, regeneration dispatch и
  clean library. Общий Stage 1 gate: 41 Pest / 193 assertions, PHPStan, Pint,
  TypeScript, ESLint и Vite build (2370 modules / 8.27 s) зелёные.

**Gate:** оригинал сохранён; публичная страница не загружает original; CLS от media равен нулю; LCP media входит в бюджет.

### Этап 2 — Lighthouse frontend, 5–10 дней

- [x] Разделить `PublicHeader`. **Доказательство:** статическая оболочка и вся навигация переведены в Server Component, интерактивность изолирована в `ThemeToggle`, `LocaleSwitcher` и `MobileMenuButton`; объём исходников под client boundary сокращён с 23 540 до 2 629 bytes (-88,8%), production chunks не содержат статический copy шапки. 31 Vitest, TypeScript, ESLint, 18 Playwright/axe и production build на 54 страницы зелёные; Lighthouse CI (18 прогонов) — Performance 95/95/95/96/95/96, Accessibility/Best Practices/SEO 100, median TBT 4,5–6 ms.
- [x] Lazy-load map и остальные islands. **Доказательство:** `d3-geo` и
  `topojson-client` лежат в отдельных чанках (26,0 и 24,3 KiB) и не входят в
  граф первой загрузки ни одного маршрута — проверено по `firstLoadChunkPaths`
  всех маршрутов. Остальные islands (`MapExplorer`, `NewsSlider`,
  `ContactForm`, `ShareButton`, `ArticleActions`) — небольшие собственные
  компоненты без тяжёлых внешних зависимостей: маршрутный JS сверх общей
  оболочки 555,8 KiB составляет `/map` +11,5 KiB, главная +6,1, `contacts` и
  `alerts` +3,3, остальные 0, поэтому дополнительное дробление не окупается.
  Байтовый бюджет эту ленивость не защищал: запас до `largestRouteBytes` ~53 KiB
  при весе карты ~50 KiB, и подложенный статический импорт дал 588,3 KiB против
  бюджета 620 KiB — то есть прошёл бы молча. Добавлена отдельная проверка
  «библиотека обязана грузиться только по требованию»; на той же регрессии она
  назвала все 4 маршрута с картой. Полный фронт-гейт: TypeScript, ESLint,
  107 Vitest, production build, `bundle:report` без нарушений, 62 Playwright.
- [x] Уменьшить fonts. **Доказательство:** Fira Sans/Fira Sans Condensed сохранены и self-hosted; критический путь сокращён с 7 subset-файлов до 2 успешных WOFF2-запросов (48 724 bytes суммарно, бюджет ≤ 100 KB), что защищено Vitest и Playwright.
- [x] Убрать повторные API calls. **Доказательство:** замер на mock-CMS со
  счётчиком запросов и чистым кэшем — повторов не осталось ни на одной
  проверенной странице. `/ru`: `settings ×1`, `menu ×1`, `home ×1`;
  `/ru/structure`: `settings ×1`, `menu ×1`, `structure ×1` — хотя настройки
  нужны и макету, и странице; `/ru/news/test-news`: `/news/test-news ×1` —
  хотя `fetchNewsItem` вызывают и `generateMetadata`, и тело страницы.
  Отдельно замечено и НЕ закрыто здесь: `generateStaticParams` и блок related
  тянут полные списки (`per_page=50` на каждую из трёх локалей) ради одного
  поля `slug` — полный список против slug-only даёт 13,6× у news, 41,9× у
  projects, 19,6× у announcements, 21,0× у instructions. Это не повтор
  запроса, а лишние поля в ответе, поэтому передано в пункт «Select only
  required columns».
- [x] Granular tags и webhook. **Доказательство:** CMS отправляет проверяемый контракт `type/id/slug/locales/event/tags`; `RevalidateFrontend` имеет `ShouldBeUnique` (30 s), explicit `afterCommit()`, timeout, backoff, exception throttling и permanent-failure alert. Next 16 валидирует соответствие metadata→tags и вызывает `revalidateTag(tag, "max")` только для `shell/home/list/detail/sitemap` нужного типа и локали. Pest покрывает success/disabled/timeout/401/5xx/retry/unique/after-commit, Vitest — list/detail tags, auth и invalid contract; полный CMS CI: 363 теста / 1352 assertions, frontend: 36 unit tests, TypeScript, ESLint и production build на 54 страницы.
- [x] Pagination/filtering server-side. **Доказательство:** проверено запросами
  ко всем двенадцати публичным спискам: `per_page=999999` зажимается до 50
  везде, кроме `/alerts`, у которого постраничности нет намеренно. Шесть
  списочных страниц портала берут страницу с сервера (`news` 12, `projects` 12,
  `guides` 15, `documents` 20, `announcements` 20, `search` 20) и рендерят
  `Pagination`; отбор по категории, типу, виду и поисковой строке выполняет CMS
  до постраничности, счётчики берутся из `meta.total`. Ключевое — что это
  действительно сервер, а не клиентская фильтрация: новый
  `tests/e2e/no-js-lists.spec.ts` гоняет категории, GET-форму поиска и ссылки
  страниц **с выключенным JavaScript**. Страж дополнен: `PublicPaginationTest`
  не покрывал `/news` и `/search` (обе постраничны, ни одна не была защищена) —
  теперь покрывает, а отсутствие постраничности у `/alerts` зафиксировано
  отдельным тестом как решение. Стоимость этого решения измерена на стенде с
  41 предупреждением: +128 B gzip (3 288 B без сжатия) HTML на предупреждение
  при бюджете документа 80 KB — запас в несколько сотен записей. CMS —
  539 тестов / 3945 assertions, PHPStan, Pint; фронт — tsc, ESLint, 110 Vitest,
  65 Playwright.
- [x] CSS/rendering audit. **Доказательство:** Lighthouse (mobile, по 3 прогона)
  на пяти контрольных страницах — `CLS = 0,0000` и медианой, и худшим прогоном
  при блокирующем бюджете 0,02; аудит `layout-shifts` — notApplicable, то есть
  сдвигов нет вообще (геометрия media/баннеров/карты зарезервирована).
  `unused-css-rules` и `unminified-css` — 0 B; разбор `globals.css`: 146 правил,
  144 уникальных селектора, **0 полных дублей**. Вес CSS 47 471 B без сжатия и
  10,1 KiB gzip при бюджете 35 KB. DOM главной — 467 элементов (порог
  предупреждения Lighthouse 800). `non-composited-animations` — notApplicable;
  `prefers-reduced-motion` соблюдается двумя блоками CSS и остановкой
  автопрокрутки слайдера; DOM для невидимых слайдов не создаётся; тяжёлых
  `blur`/`backdrop-filter` нет вовсе, единственный `mix-blend-mode: color`
  работает на баннерах фиксированной высоты 220–340 px, а не на больших
  областях. `content-visibility: auto` сознательно не применён: при DOM 467 и
  нулевом CLS выигрыша нет, а риски для anchor-навигации и поиска по странице
  реальны — план разрешает применять его только после такой проверки.
  Единственный render-blocking ресурс — собственная таблица стилей (11 468 B,
  153 ms); при FCP 756–908 ms против бюджета 1,2 s дробление на критический и
  отложенный CSS не окупается. **Что исправлено:** бюджет CSS из §3.3 не
  проверялся нигде — добавлен в `bundle:report` (`cssGzipBytes`), проверен
  подложенной регрессией на настоящей сборке (1 400 правил → 37 082 B против
  35 840 B, страж назвал метрику по имени). Фронт — tsc, ESLint, 112 Vitest,
  65 Playwright, `bundle:report` без нарушений.
  **Вне пункта:** LCP 2,9–3,5 s против бюджета 1,8 s — уже зафиксированный
  долг Этапа 0, к CSS и рендерингу отношения не имеет.
- [ ] JSON-LD и localized 404.
- [ ] Security headers и compression.

**Gate:** бюджеты раздела 3 проходят на всех контрольных страницах.

### Этап 3 — Laravel/API, 5–10 дней

- [x] Redis/cache/queue topology. **Доказательство:** production topology использует Laravel 13 cache/queue failover Redis→database, bounded Redis reconnect, `after_commit` и отдельные `critical/notifications/revalidation/default/media` queues; CPU-heavy media обслуживается отдельным worker. Scheduler ставит worker heartbeat и запускает `queue:monitor` для Redis и database fallback, `/ready` проверяет scheduler/worker и показывает failed jobs, а QueueBusy/failed-over/job-failed события логируются как operational alerts. Pest с принудительно недоступным Redis доказывает сохранение cache/job в БД; 35 релевантных тестов и полный `composer ci:check` зелёные: 374 теста / 1426 assertions, ESLint, Prettier, TypeScript, Pint и PHPStan.
- [x] Cache read models + invalidation. **Доказательство:** `PublicReadModelCache` хранит готовые locale/query-aware DTO через Laravel 13 `Cache::flexible()` и versioned keys, совместимые с database/Redis; after-commit observer инвалидирует settings/menu/categories/regions/alerts/home при изменениях моделей. На повторном GET application-table queries снижены: settings 1→0, menu 2→0, home 15→0, regions 3→0, categories 2→0. 7 новых cache/query/invalidation Pest-тестов и полный `composer ci:check` зелёные: 358 тестов / 1339 assertions, ESLint, Prettier, TypeScript, Pint и PHPStan.
- [x] Оптимизировать HomeController. **Доказательство:** сборка DTO вынесена в `HomePageReadModel`; snapshot и карточки используют одну выборку active alerts, все content limits применяются в SQL, list queries выбирают только поля публичного DTO и только нужные media collections. На текущих данных uncached application queries снижены 15→14; populated query-budget Pest фиксирует 15 запросов и ровно один `SELECT alerts` (старый план — 17). 94 API-теста / 447 assertions и полный `composer ci:check` зелёные: 364 теста / 1362 assertions, ESLint, Prettier, TypeScript, Pint и PHPStan.
- [x] Select only required columns. **Доказательство:** список-контроллеры уже
  выбирали поля явно, и это защищено `PublicApiColumnSelectionTest` (ни одного
  `select *` по 12 публичным таблицам). Оставался переданный из F-06 остаток:
  `generateStaticParams` шести детальных маршрутов и `sitemap.ts` тянули полный
  DTO ради одного поля `slug`. Добавлен `GET /api/v1/slugs/{type}` — те же
  строки, что у соответствующего списка (тот же `public()`/`active()` и тот же
  контракт локали), одна колонка. Замер на живой CMS: сборочные вызовы
  `generateStaticParams` (6 типов × 3 локали) 60 338 → 2 860 B (21,1×), карта
  сайта (6 типов) 26 585 → 1 178 B (22,6×); по типам 14,2×–47,1×. Множества
  slug'ов совпадают со старыми по всем шести типам, сборка даёт те же 111
  страниц, карта сайта — те же 138 URL. `SlugApiTest` держит паритет строк со
  списком, фильтр по локали, отсев непубличных, единственную колонку и
  соотношение к списку; счётчик операций OpenAPI 27 → 28. Полный CMS-набор
  536 тестов / 3933 assertions, PHPStan, Pint, Prettier, tsc, bundle-тесты;
  фронт — tsc, ESLint, 110 Vitest, production build, `bundle:report` без
  нарушений (largest route 567,3 KiB), 62 Playwright.
- [x] Убрать тяжёлые shared props. **Доказательство:** Inertia `auth`/permissions и `nav_badges` стали `once` props с TTL, notification list загружается `optional` partial reload только при открытии drawer, а начальный payload содержит один дешёвый unread `count(*)` без body (8×4 KB fixture отсутствует, response < 50 KB). Approval badges считаются SQL `count(*)` без гидрации review-моделей. 16 релевантных Pest-тестов / 99 assertions, TypeScript, ESLint, Prettier и полный `composer ci:check` зелёные: 368 тестов / 1395 assertions, Pint и PHPStan.
- [x] EXPLAIN и составные индексы. **Доказательство:** на отдельной MySQL 8.4 БД с 345 000 production-like строк выполнен `EXPLAIN ANALYZE` для 9 основных query shapes. Добавлены только два индекса, реально выбранные оптимизатором: `menu_items(location, enabled, sort, parent_id)` уменьшил 0,765→0,206 ms (−73%), `submissions(status, created_at, id)` — 4,96→0,036 ms (−99%). Кандидаты для news/instructions/projects/pages не добавлены, поскольку MySQL продолжил выбирать table scan. CI дополнен полным Pest job на MySQL 8; локально SQLite и MySQL: 369 тестов / 1399 assertions, полный `composer ci:check`, Pint и PHPStan зелёные.
- [x] OpenAPI и generated types. **Доказательство:** OpenAPI 3.1 описывает все 25 публичных операций `api/v1` (включая добавленный O-019 RUM endpoint); Pest сверяет реальный route registry и рекурсивно валидирует успешные ответы всех операций. Frontend генерирует 54 TypeScript-типа из зафиксированного schema snapshot, `api:types:check` блокирует drift в CI, ручные API DTO удалены из `lib/api.ts`. Текущий полный `composer ci:check` — 420 тестов / 2076 assertions; frontend TypeScript, ESLint, 41 Vitest-тест и production build на 55 страниц зелёные.
- [ ] Нормализовать logging/metrics.
- [ ] Production optimize/OPcache/FPM/CDN.

**Gate:** p95 и query-count бюджеты проходят на production-like data; cache invalidation корректна.

### Этап 4 — простая CMS, 10–20 дней

- [x] Role-based task dashboard. **Доказательство:** сервер формирует только
  выполнимые текущей ролью задачи: автору — свои returned/draft, переводчику —
  неполные `translation_check`, согласующему — approvals, оператору — истекающие
  alerts. Все выборки проходят module permission, model policy и regional
  scope; приоритеты `исправить → согласовать → перевести → истекает → черновик`
  стабильны, а dashboard ведёт в доступный роли task center. 9 целевых Pest /
  114 assertions, Playwright role regression 2/2; полный `composer ci:check` —
  504 теста / 3716 assertions; production Vite build — 2370 modules / 8.34 s.
- [x] Общий `EditorialFormShell`. **Доказательство:** News/Page/Project/Instruction/Announcement/Document используют один shell с единым header, language switcher, error summary, порядком действий, Ctrl/Cmd+S и защитой несохранённых данных; submit/back URL генерируются Wayfinder. Браузерная проверка реальной News-формы подтвердила dirty-state confirm, отсутствие console errors и 44 px actions при viewport 390×844. 84 релевантных Pest-теста / 333 assertions и полный `composer ci:check` зелёные: 381 тест / 1511 assertions, ESLint, Prettier, TypeScript, Pint и PHPStan.
- [x] Autosave/recovery/concurrency. **Доказательство:** общий `useEditorialAutosave` для шести сущностей сохраняет только изменившийся JSON после 1,5-секундной паузы, держит offline/localStorage recovery copy и показывает «Сохранено в HH:MM». Immutable `editorial_revisions` дают историю/restore; restore не меняет workflow status и бинарные media. `updated_at` version token блокирует stale normal save, а server autosave cursor выявляет две вкладки и показывает выбор server/local. 13 новых Pest-тестов / 52 assertions проверяют durable autosave без мутации published model, conflict/force resolution, policy, revision list и restore; полный `composer ci:check` зелёный: 394 теста / 1563 assertions, ESLint, Prettier, TypeScript, Pint и PHPStan; Vite production build зелёный.
- [x] Единый preview/checklist. **Доказательство:** шесть редакционных форм используют общий preview с live unsaved data, локалями `tg/ru/en`, desktop/mobile и share/OG режимами; fallback явно помечен. Private signed URL требует авторизацию и policy, имеет TTL 15 минут, `no-store` и `noindex`. Серверный `PublicationChecklist` блокирует публикацию при неполном обязательном переводе, пустом alt обложки, unsafe href или незавершённой media conversion и показывает неблокирующий SEO warning. 11 новых Pest-сценариев и workflow/form regression suite зелёные; полный `composer ci:check` — 405 тестов / 1637 assertions, ESLint, Prettier, TypeScript, Pint и PHPStan; Vite production build зелёный.
- [x] Media UX. **Доказательство:** изображения получили доступный focal-point picker (точка сохраняется в media DTO, копируется в News/Project/Instruction и управляет публичным `object-position`), обязательную пару alt либо explicit decorative, поиск по title/alt/caption и экран «Где используется». Usage service находит структурные media-копии и прямые rich-text URL; используемый файл нельзя удалить. Свободный asset перемещается в фильтруемую корзину и восстанавливается без удаления оригинала и derivatives. 6 новых Pest-сценариев плюс media/news/project/instruction regression suite зелёные; полный `composer ci:check` — 411 тестов / 1706 assertions, ESLint, Prettier, TypeScript, Pint и PHPStan; Vite production build зелёный.
- [x] Revision history/trash. **Доказательство:** immutable revision history
  охватывает все шесть редакционных форм и восстанавливает только editorial
  fields, сохраняя workflow status и создавая `before_restore` snapshot.
  Добавлена единая пагинируемая корзина News/Page/Project/Instruction/
  Announcement/Document через SQL `UNION ALL`: список ограничен view-policy и
  региональным scope, restore требует delete-policy, выполняется транзакционно
  и сохраняет прежний workflow status. 18 целевых Pest-тестов / 99 assertions;
  полный `composer ci:check` — 496 тестов / 3605 assertions; Playwright 10/10,
  включая axe экрана корзины; production Vite build — 2368 modules / 8.21 s.
- [x] Translation queue. **Доказательство:** единая пагинируемая очередь
  News/Page/Project/Instruction/Announcement/Document использует SQL `UNION
  ALL`, показывает только неполные смысловые поля `tg/ru/en`, фильтруется по
  типу и требуемой локали и ставит `translation_check` выше остальных статусов.
  Доступ ограничен module edit-policy и regional author scope; каждая строка
  ведёт прямо в разрешённый редактор. 5 Pest-тестов / 62 assertions; полный
  `composer ci:check` — 501 тест / 3667 assertions; axe accessibility 9/9;
  production Vite build — 2370 modules / 8.15 s.
- [x] Accessibility browser tests. **Доказательство:** `@axe-core/playwright`
  проверяет `/dashboard`, `/news`, `/media` и `/news/create` по WCAG 2.0/2.1
  A/AA без serious/critical нарушений; общие Modal/Drawer и command palette
  удерживают Tab/Shift+Tab, закрываются по Escape и возвращают видимый focus
  исходному trigger. Playwright также доказал screen-reader names у форм и
  icon actions, текстовые статусы, `aria-live` autosave, кнопочные альтернативы
  загрузки/медиатеки, reflow без горизонтального overflow при 320 CSS px и пять
  touch targets topbar ≥44×44 при 390×844. Полный `npm run test:e2e`: 9/9;
  `composer ci:check`: 490 тестов / 3555 assertions; production Vite build:
  2366 modules / 8.27 s.
- [ ] Usability test с реальными сотрудниками. **Инженерный контур готов:** в CMS добавлен защищённый анонимный протокол 8 заданий, стандартный SUS, точный p75 и автоматический gate по всем целям UX-10; 8 Pest-тестов / 59 assertions. Последующий mobile QA устранил overflow topbar/dashboard и добавил Playwright regression на 390×844; полный `composer ci:check` — 490 тестов / 3555 assertions, Vite production build зелёный. Изолированная браузерная проверка доказала login → save → обновление отчёта, отсутствие console errors, семантические tables/fieldsets и touch targets ≥ 44 px на desktop и viewport 390×844. **Полевой результат: 0/5 реальных сотрудников; чекбокс нельзя закрыть до проведения сессий.**

> ⚠️ **Этот пункт — внешняя приёмка, а не инженерная задача, и он НЕ блокирует остальную работу.** Сессии проводит человек с живыми будущими редакторами; ни агент, ни браузерная автоматизация не могут их заменить, а синтетическая сессия является фальсификацией данных и запрещена. Набор фасилитатора готов: [`USABILITY_FIELD_KIT.md`](./USABILITY_FIELD_KIT.md) — протокол, задания, бланк, анкета SUS и порядок ввода. Пока полевых данных нет, O-020 остаётся со статусом «инженерный контур готов, приёмка ожидается», а работа продолжается по остальным незакрытым пунктам этапов 1–5. Возврат к O-020 — только после появления реальных сессий: если гейт не пройден, из отчёта заводятся задачи `UX-fix-*`, они чинятся, и тест повторяется с другими участниками.

**Gate:** пользовательские метрики UX-10 достигнуты. **Не является блокирующим для этапов 1–3 и 5** — они закрываются независимо.

### Этап 5 — эксплуатация, 3–7 дней

- [ ] Queue/scheduler monitoring.
- [ ] CWV/latency/error dashboards.
- [ ] Backup + restore drill.
- [ ] Media lifecycle/orphan cleanup.
- [ ] Load/chaos scenarios.
- [ ] Runbook и rollback.

**Gate:** сбой frontend webhook, Redis или image conversion не теряет материал и не ломает публикацию.

## 10. Приоритетный backlog

| ID | Приоритет | Работа | Главный эффект |
|---|---|---|---|
| O-001 | P0 | Вернуть зелёный CMS CI | Безопасность всех последующих изменений |
| O-002 | P0 | Production build front fail-fast | Исключает пустой релиз |
| O-003 | P0 | Front tests + LHCI | Защита 99–100 |
| O-004 | P0 | `CmsImage` и замена 10 `<img>` | LCP/CLS/traffic |
| O-005 | P0 | Media jobs вместо `nonQueued()` | Быстрое сохранение CMS |
| O-006 | P0 | Original + structured derivatives DTO | Правильная media-архитектура |
| O-007 | P1 | Разделить global client header | Меньше JS/TBT/INP |
| O-008 | P1 | Кэшировать API read models | TTFB/DB/CPU |
| O-009 | P1 | Granular ISR tags | Меньше регенераций |
| O-010 | P1 | Оптимизировать HomeController | Query count/latency |
| O-011 | P1 | Убрать тяжёлые Inertia shared props | Скорость CMS |
| O-012 | P1 | EXPLAIN + composite indexes | Рост данных |
| O-013 | P1 | Redis + workers + media queue | Надёжность и latency |
| O-014 | P1 | Общий editorial form shell | Простота и поддержка |
| O-015 | P1 | Autosave/recovery/revisions | Нет потери работы |
| O-016 | P1 | Preview + publication checklist | Меньше ошибок |
| O-017 | P1 | Media focal point/alt/usage/trash | CMS «лучше WordPress» |
| O-018 | P2 | OpenAPI/generated types — **выполнено: 25 operations, 54 generated types, contract CI** | Стабильный контракт |
| O-019 | P2 | RUM CWV dashboard — **выполнено: anonymous LCP/INP/CLS, exact p75, CMS dashboard** | Реальная скорость |
| O-020 | P2 | Usability testing — **контур готов; полевые сессии 0/5** | Доказанная простота |

## 11. Что не делать

- Не обещать постоянные 100 PSI без фиксации среды и реального контента.
- Не обслуживать оригинал 4000–8000 px как LCP.
- Не генерировать производные в публичном HTTP-запросе.
- Не делать все изображения JPEG.
- Не создавать AVIF/WebP одновременно в CMS, Next и ещё одном CDN без ясного владельца оптимизации.
- Не ставить `preload/priority` всем изображениям.
- Не кэшировать ответы без карты invalidation.
- Не добавлять индексы без `EXPLAIN`.
- Не переносить все компоненты в client ради удобства.
- Не скрывать контент или отключать accessibility ради Lighthouse.
- Не включать Octane, service worker или новый search engine до профилирования.
- Не передавать технические статусы и ошибки нетехническому редактору.
- Не удалять оригиналы при cleanup derivatives.

## 12. Definition of Done

Работа завершена только когда одновременно выполнено:

- CMS CI и front CI зелёные;
- Lighthouse budgets проходят на контрольных страницах и приложены JSON/HTML-артефакты;
- CrUX/RUM показывает зелёные LCP/INP/CLS либо, до накопления 28 дней, внутренний RUM проходит цели;
- production build не может тихо выпустить пустой сайт;
- все публичные изображения используют структурированный DTO и `CmsImage`;
- original checksum после всех преобразований не изменён;
- conversion failure виден, повторяем и не блокирует черновик;
- API cache имеет тесты invalidation;
- queue, scheduler, storage и webhook наблюдаемы;
- редактор восстанавливает несохранённую работу;
- нет действий без policy/authorization;
- usability test выполнен реальными сотрудниками;
- backup restore и rollback проверены.

## 13. Официальные источники, использованные при составлении

- Google PageSpeed Insights: https://developers.google.com/speed/docs/insights/v5/about
- Lighthouse scoring: https://developer.chrome.com/docs/lighthouse/performance/performance-scoring
- Core Web Vitals thresholds: https://web.dev/articles/defining-core-web-vitals-thresholds
- Web Vitals/RUM: https://web.dev/articles/vitals
- Performance budgets: https://web.dev/articles/your-first-performance-budget
- Next.js 16 local docs: `node_modules/next/dist/docs/01-app/`
- Laravel 13 cache/queue/routing docs — проверены через Laravel Boost version-specific search.
- Spatie Media Library docs — responsive images, tiny placeholders и queued conversions проверены через Laravel Boost.
