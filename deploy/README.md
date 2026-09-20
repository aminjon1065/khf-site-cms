# Исполняемые артефакты развёртывания

Материализованные версии сниппетов из `DEPLOYMENT.md` (audit J-3): то, что
раньше приходилось копировать из документации руками, теперь лежит в репо и
проверяется вместе с кодом. Документация остаётся источником объяснений
«почему»; эти файлы — «что запускать».

## Состав

| Артефакт | Куда на сервере | Назначение |
|---|---|---|
| `supervisor/khf-cms-workers.conf` | `/etc/supervisor/conf.d/` | 4 воркера очередей (redis + database-fallback, основной + media) со `stopwaitsecs` > 180 (DEPLOYMENT §2.8) |
| `cron.d/khf-cms` | `/etc/cron.d/khf-cms` | Единственная cron-строка планировщика (DEPLOYMENT §2.7) |
| `systemd/khf-front.service` | `/etc/systemd/system/` | Публичный сайт Next.js (DEPLOYMENT §3.3) |
| `nginx/cms.conf` | `/etc/nginx/sites-available/khf-cms` | vhost CMS: fastcgi + заголовки + сжатие + **X-Forwarded-For/Proto** для Laravel (audit I-1) |
| `nginx/front.conf` | `/etc/nginx/sites-available/khf-front` | vhost сайта: прокси на :3000 с keepalive-пулом (DEPLOYMENT §3.3) |
| `deploy-cms.sh` | запуск с деплой-хоста | Атомарный релиз CMS: clone → build → migrate → проверки → переключение symlink → reload FPM + queue:restart (DEPLOYMENT §4) |
| `deploy-front.sh` | запуск с деплой-хоста | Атомарный релиз сайта: clone → build (fail-fast по готовности CMS) → переключение symlink → restart (DEPLOYMENT §4) |
| `rollback.sh` | запуск с деплой-хоста | Откат `current` на предыдущий релиз (или на указанный) без сборки |

## Первичная установка (один раз)

```bash
# CMS-хост
install -m 0644 supervisor/khf-cms-workers.conf /etc/supervisor/conf.d/
install -m 0644 cron.d/khf-cms /etc/cron.d/khf-cms
install -m 0644 nginx/cms.conf /etc/nginx/sites-available/khf-cms
ln -s /etc/nginx/sites-available/khf-cms /etc/nginx/sites-enabled/

# Хост публичного сайта
install -m 0644 systemd/khf-front.service /etc/systemd/system/
install -m 0644 nginx/front.conf /etc/nginx/sites-available/khf-front
ln -s /etc/nginx/sites-available/khf-front /etc/nginx/sites-enabled/

# Оба хоста
nginx -t && systemctl reload nginx
supervisorctl reread && supervisorctl update
systemctl daemon-reload && systemctl enable --now khf-front
```

Перед включением скорректируйте в файлах пути (`/var/www/khf-*`), версию
PHP-бинарья (`php8.5`), пользователя и пути сертификатов под реальный стенд.

## Релизы и откаты

```bash
./deploy/rollback.sh --help        # использование
sudo ./deploy/deploy-cms.sh        # переменные окружения см. в шапке скрипта
sudo ./deploy/deploy-front.sh
./deploy/rollback.sh cms           # предыдущий релиз
./deploy/rollback.sh cms 20260920180000   # конкретный
```

Скрипты деплоя поддерживают dry-run (`DRY_RUN=1`) — печатают шаги без
выполнения. Хранятся последние `KEEP_RELEASES` (по умолчанию 5) релизов.

## Что сознательно НЕ автоматизировано

- Выпуск TLS-сертификатов и топология DNS/CDN — окружение стендозависимо;
  требования описаны в DEPLOYMENT §1.
- `php artisan ops:capacity --ram=…` печатает рекомендации по OPcache/FPM-пулу,
  но не правит php.ini сам: применение ручное, значения зависят от стенда.
- Начальный `db:seed`/создание администратора — DEPLOYMENT §2.4 (там же
  предупреждение о демо-пароле).
