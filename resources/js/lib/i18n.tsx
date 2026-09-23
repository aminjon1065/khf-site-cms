import { createContext, useCallback, useContext, useMemo } from 'react';
import type { ReactNode } from 'react';

/**
 * The staff interface is Russian-only (owner decision, 2026-09-23); content
 * itself is still written in ru/tg/en. The dictionary keeps labels in one
 * place.
 */
export type Locale = 'ru';

/** Flat key → string dictionary. */
const ru: Record<string, string> = {
    'app.name': 'КЧС РТ · Панель управления',
    'app.tagline': 'Комитет по чрезвычайным ситуациям и гражданской обороне',

    // generic actions
    'action.create': 'Создать',
    'action.save': 'Сохранить',
    'action.save_draft': 'Сохранить черновик',
    'action.cancel': 'Отмена',
    'action.close': 'Закрыть',
    'action.delete': 'Удалить',
    'action.edit': 'Редактировать',
    'action.open': 'Открыть',
    'action.preview': 'Предпросмотр',
    'action.duplicate': 'Дублировать',
    'action.history': 'История',
    'action.back': 'Назад',
    'action.next': 'Далее',
    'action.submit': 'Отправить',
    'action.publish': 'Опубликовать',
    'action.unpublish': 'Снять с публикации',
    'action.approve': 'Согласовать',
    'action.return': 'Вернуть на доработку',
    'action.send_review': 'Отправить на согласование',
    'action.search': 'Поиск',
    'action.filter': 'Фильтр',
    'action.reset': 'Сбросить',
    'action.apply': 'Применить',
    'action.export': 'Экспорт',
    'action.export_csv': 'Экспорт CSV',
    'action.upload': 'Загрузить',
    'action.replace': 'Заменить',
    'action.invite': 'Пригласить',
    'action.deactivate': 'Деактивировать',
    'action.activate': 'Активировать',
    'action.copy_from_ru': 'Скопировать из русского',
    'action.mark_all_read': 'Отметить всё прочитанным',
    'action.open_site': 'Открыть сайт',
    'action.confirm': 'Подтвердить',
    'action.select': 'Выбрать',
    'action.add': 'Добавить',
    'action.remove': 'Убрать',
    'action.logout': 'Выйти',

    // common nouns / labels
    'common.title': 'Название',
    'common.status': 'Статус',
    'common.author': 'Автор',
    'common.date': 'Дата',
    'common.region': 'Регион',
    'common.regions': 'Регионы',
    'common.type': 'Тип',
    'common.severity': 'Уровень',
    'common.language': 'Язык',
    'common.languages': 'Языки',
    'common.published': 'Публикация',
    'common.deadline': 'Срок',
    'common.actions': 'Действия',
    'common.all': 'Все',
    'common.none': 'Нет',
    'common.yes': 'Да',
    'common.no': 'Нет',
    'common.loading': 'Загрузка…',
    'common.saving': 'Сохранение…',
    'common.saved': 'Сохранено',
    'common.unsaved': 'Есть несохранённые изменения',
    'common.required': 'Обязательное поле',
    'common.optional': 'необязательно',
    'common.category': 'Рубрика',
    'common.tags': 'Метки',
    'common.updated_at': 'Обновлено',
    'common.created_at': 'Создано',
    'common.role': 'Роль',
    'common.email': 'Эл. почта',
    'common.phone': 'Телефон',
    'common.name': 'Имя',
    'common.position': 'Должность',
    'common.department': 'Отдел',
    'common.last_login': 'Последний вход',
    'common.channels': 'Каналы',
    'common.source': 'Источник',
    'common.contacts': 'Контакты',

    // states
    'state.empty': 'Ничего не найдено',
    'state.empty_hint': 'Измените фильтры или создайте новую запись.',
    'state.error': 'Ошибка загрузки',
    'state.error_hint': 'Попробуйте обновить страницу.',
    'state.no_permission': 'Нет доступа',
    'state.no_permission_hint':
        'У вас недостаточно прав для просмотра этого раздела.',
    'state.search_empty': 'По запросу ничего не найдено',
    'state.no_translation': 'Перевод отсутствует',
    'state.no_image': 'Изображение не загружено',
    'state.file_missing': 'Файл не загружен',

    // sidebar groups
    'nav.group.work': 'Работа',
    'nav.group.content': 'Материалы',
    'nav.group.site': 'Сайт',
    'nav.group.admin': 'Администрирование',
    'nav.group.account': 'Учётная запись',

    // sidebar items
    'nav.dashboard': 'Обзор',
    'nav.control_center': 'Оперативная обстановка',
    'nav.alerts': 'Предупреждения',
    'nav.approvals': 'Согласование',
    'nav.news': 'Новости',
    'nav.instructions': 'Инструкции населению',
    'nav.documents': 'Документы',
    'nav.pages': 'Страницы',
    'nav.announcements': 'Объявления',
    'nav.media': 'Медиатека',
    'nav.home_blocks': 'Главная страница',
    'nav.users': 'Пользователи',
    'nav.roles': 'Роли и права',
    'nav.activity': 'Журнал действий',
    'nav.settings': 'Настройки',
    'nav.profile': 'Профиль',
    'nav.notifications': 'Уведомления',
    'nav.search': 'Поиск',

    // auth
    'auth.login_title': 'Вход в систему',
    'auth.login_sub': 'Единая система управления контентом КЧС РТ',
    'auth.email': 'Эл. почта',
    'auth.password': 'Пароль',
    'auth.show_password': 'Показать пароль',
    'auth.hide_password': 'Скрыть пароль',
    'auth.remember': 'Запомнить это устройство',
    'auth.forgot': 'Забыли пароль?',
    'auth.sign_in': 'Войти',
    'auth.security_notice':
        'Доступ только для уполномоченных сотрудников. Все действия фиксируются в журнале.',
    'auth.2fa_title': 'Двухфакторная аутентификация',
    'auth.2fa_sub': 'Введите 6-значный код из приложения-аутентификатора',
    'auth.2fa_recovery': 'Использовать код восстановления',
    'auth.2fa_verify': 'Подтвердить',
    'auth.interface_lang': 'Язык интерфейса',

    // severities
    'severity.info': 'Информация',
    'severity.attention': 'Внимание',
    'severity.warning': 'Предупреждение',
    'severity.danger': 'Опасность',
    'severity.critical': 'Критический',

    // statuses
    'status.draft': 'Черновик',
    'status.review': 'На согласовании',
    'status.translation_check': 'Проверка перевода',
    'status.approved': 'Согласовано',
    'status.scheduled': 'Запланировано',
    'status.published': 'Опубликовано',
    'status.updated': 'Обновлено',
    'status.completed': 'Завершено',
    'status.cancelled': 'Отменено',
    'status.returned': 'Возвращено',
    'status.archived': 'В архиве',

    // hazards
    'hazard.mudflow': 'Сель',
    'hazard.earthquake': 'Землетрясение',
    'hazard.flood': 'Наводнение',
    'hazard.avalanche': 'Лавина',
    'hazard.fire': 'Пожар',
    'hazard.wind': 'Сильный ветер',
    'hazard.heat': 'Жара',
    'hazard.frost': 'Мороз',
    'hazard.landslide': 'Оползень',
    'hazard.storm': 'Гроза',

    // channels
    'channel.site': 'Сайт',
    'channel.sos_app': 'Приложение SOS',
    'channel.rss': 'RSS',
    'channel.sms': 'СМС',

    // languages
    'lang.tg': 'ТҶ',
    'lang.ru': 'РУ',
    'lang.en': 'EN',
    'lang.tg_full': 'Тоҷикӣ',
    'lang.ru_full': 'Русский',
    'lang.en_full': 'English',
};

type Translator = (
    key: string,
    params?: Record<string, string | number>,
) => string;

const I18nContext = createContext<{ locale: Locale; t: Translator }>({
    locale: 'ru',
    t: (key) => key,
});

function interpolate(
    template: string,
    params?: Record<string, string | number>,
): string {
    if (!params) {
        return template;
    }

    return template.replace(/:(\w+)/g, (_, name) =>
        String(params[name] ?? `:${name}`),
    );
}

export function I18nProvider({ children }: { children: ReactNode }) {
    const locale: Locale = 'ru';

    const t = useCallback<Translator>(
        (key, params) => interpolate(ru[key] ?? key, params),
        [],
    );

    const value = useMemo(() => ({ locale, t }), [t]);

    return (
        <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
    );
}

export function useT() {
    return useContext(I18nContext);
}
