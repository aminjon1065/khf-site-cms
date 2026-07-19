import { usePage } from '@inertiajs/react';
import { createContext, useCallback, useContext, useMemo } from 'react';
import type { ReactNode } from 'react';

export type Locale = 'ru' | 'tg';

/**
 * Flat key → string dictionaries. `ru` is the complete reference; `tg` (тоҷикӣ)
 * overrides where a genuine Tajik term exists and otherwise falls back to `ru`.
 * Domain vocabulary (severities, statuses, hazards) is provided in both.
 */
const ru: Record<string, string> = {
    'app.name': 'КЧС РТ · CMS',
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
    'action.send_review': 'Отправить на проверку',
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
    'common.category': 'Категория',
    'common.tags': 'Теги',
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
    'nav.group.overview': 'Обзор',
    'nav.group.operational': 'Оперативная информация',
    'nav.group.content': 'Контент сайта',
    'nav.group.management': 'Управление контентом',
    'nav.group.system': 'Система',

    // sidebar items
    'nav.dashboard': 'Дашборд',
    'nav.control_center': 'Центр контроля',
    'nav.alerts': 'Предупреждения',
    'nav.approvals': 'Центр согласования',
    'nav.news': 'Новости',
    'nav.instructions': 'Инструкции населению',
    'nav.documents': 'Документы',
    'nav.pages': 'Страницы',
    'nav.announcements': 'Объявления',
    'nav.media': 'Медиабиблиотека',
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
    'status.review': 'На проверке',
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

const tg: Record<string, string> = {
    'app.name': 'КҲӢ ҶТ · CMS',
    'app.tagline': 'Кумитаи ҳолатҳои фавқулодда ва мудофиаи граждании ҶТ',

    'action.create': 'Эҷод',
    'action.save': 'Захира',
    'action.save_draft': 'Захираи сиёҳнавис',
    'action.cancel': 'Бекор',
    'action.close': 'Пӯшидан',
    'action.delete': 'Нест кардан',
    'action.edit': 'Таҳрир',
    'action.open': 'Кушодан',
    'action.preview': 'Пешнамоиш',
    'action.duplicate': 'Нусхабардорӣ',
    'action.history': 'Таърих',
    'action.back': 'Бозгашт',
    'action.next': 'Оянда',
    'action.submit': 'Фиристодан',
    'action.publish': 'Нашр кардан',
    'action.unpublish': 'Аз нашр гирифтан',
    'action.approve': 'Тасдиқ',
    'action.return': 'Баргардонидан барои такмил',
    'action.send_review': 'Фиристодан ба санҷиш',
    'action.search': 'Ҷустуҷӯ',
    'action.filter': 'Филтр',
    'action.reset': 'Аз нав',
    'action.apply': 'Татбиқ',
    'action.export': 'Содирот',
    'action.export_csv': 'Содироти CSV',
    'action.upload': 'Боркунӣ',
    'action.replace': 'Иваз кардан',
    'action.invite': 'Даъват',
    'action.deactivate': 'Ғайрифаъол',
    'action.activate': 'Фаъол',
    'action.copy_from_ru': 'Нусха аз русӣ',
    'action.mark_all_read': 'Ҳамаро хондашуда қайд кунед',
    'action.open_site': 'Кушодани сайт',
    'action.confirm': 'Тасдиқ',
    'action.select': 'Интихоб',
    'action.add': 'Илова',
    'action.remove': 'Хориҷ',
    'action.logout': 'Баромадан',

    'common.title': 'Номгӯй',
    'common.status': 'Ҳолат',
    'common.author': 'Муаллиф',
    'common.date': 'Сана',
    'common.region': 'Минтақа',
    'common.regions': 'Минтақаҳо',
    'common.type': 'Навъ',
    'common.severity': 'Дараҷа',
    'common.language': 'Забон',
    'common.languages': 'Забонҳо',
    'common.published': 'Нашр',
    'common.deadline': 'Мӯҳлат',
    'common.actions': 'Амалҳо',
    'common.all': 'Ҳама',
    'common.loading': 'Боркунӣ…',
    'common.saving': 'Захира шуда истодааст…',
    'common.saved': 'Захира шуд',
    'common.unsaved': 'Тағйироти захиранашуда мавҷуд аст',
    'common.role': 'Нақш',
    'common.email': 'Почтаи электронӣ',
    'common.phone': 'Телефон',
    'common.name': 'Ном',
    'common.position': 'Вазифа',
    'common.last_login': 'Вуруди охирин',
    'common.channels': 'Каналҳо',
    'common.contacts': 'Тамосҳо',

    'nav.group.overview': 'Шарҳи умумӣ',
    'nav.group.operational': 'Маълумоти оперативӣ',
    'nav.group.content': 'Мундариҷаи сайт',
    'nav.group.management': 'Идораи мундариҷа',
    'nav.group.system': 'Система',

    'nav.dashboard': 'Дашборд',
    'nav.control_center': 'Маркази назорат',
    'nav.alerts': 'Огоҳиномаҳо',
    'nav.approvals': 'Маркази мувофиқа',
    'nav.news': 'Хабарҳо',
    'nav.instructions': 'Дастурҳо ба аҳолӣ',
    'nav.documents': 'Ҳуҷҷатҳо',
    'nav.pages': 'Саҳифаҳо',
    'nav.announcements': 'Эълонҳо',
    'nav.media': 'Китобхонаи медиа',
    'nav.home_blocks': 'Саҳифаи асосӣ',
    'nav.users': 'Корбарон',
    'nav.roles': 'Нақшҳо ва ҳуқуқҳо',
    'nav.activity': 'Журнали амалҳо',
    'nav.settings': 'Танзимот',
    'nav.profile': 'Профил',
    'nav.notifications': 'Огоҳиномаҳо',
    'nav.search': 'Ҷустуҷӯ',

    'auth.login_title': 'Вуруд ба система',
    'auth.login_sub': 'Системаи ягонаи идораи мундариҷаи КҲӢ ҶТ',
    'auth.email': 'Почтаи электронӣ',
    'auth.password': 'Рамз',
    'auth.remember': 'Ин дастгоҳро дар хотир доред',
    'auth.forgot': 'Рамзро фаромӯш кардед?',
    'auth.sign_in': 'Ворид шудан',
    'auth.security_notice':
        'Дастрасӣ танҳо барои кормандони ваколатдор. Ҳама амалҳо сабт мешаванд.',
    'auth.2fa_title': 'Аутентификатсияи дуомила',
    'auth.2fa_sub': 'Рамзи 6-рақамаро аз барнома ворид кунед',
    'auth.2fa_recovery': 'Истифодаи рамзи барқарорсозӣ',
    'auth.2fa_verify': 'Тасдиқ',
    'auth.interface_lang': 'Забони интерфейс',

    'severity.info': 'Иттилоот',
    'severity.attention': 'Диққат',
    'severity.warning': 'Огоҳӣ',
    'severity.danger': 'Хатар',
    'severity.critical': 'Бӯҳронӣ',

    'status.draft': 'Сиёҳнавис',
    'status.review': 'Дар санҷиш',
    'status.translation_check': 'Санҷиши тарҷума',
    'status.approved': 'Тасдиқшуда',
    'status.scheduled': 'Ба нақша гирифташуда',
    'status.published': 'Нашршуда',
    'status.updated': 'Навшуда',
    'status.completed': 'Анҷомёфта',
    'status.cancelled': 'Бекоршуда',
    'status.returned': 'Баргардонидашуда',
    'status.archived': 'Дар бойгонӣ',

    'hazard.mudflow': 'Сел',
    'hazard.earthquake': 'Заминҷунбӣ',
    'hazard.flood': 'Обхезӣ',
    'hazard.avalanche': 'Тарма',
    'hazard.fire': 'Сӯхтор',
    'hazard.wind': 'Шамоли сахт',
    'hazard.heat': 'Гармӣ',
    'hazard.frost': 'Сармо',
    'hazard.landslide': 'Ярч',
    'hazard.storm': 'Раъду барқ',

    'channel.site': 'Сайт',
    'channel.sos_app': 'Барномаи SOS',
    'channel.rss': 'RSS',
    'channel.sms': 'СМС',

    'lang.tg': 'ТҶ',
    'lang.ru': 'РУ',
    'lang.en': 'EN',
    'lang.tg_full': 'Тоҷикӣ',
    'lang.ru_full': 'Русский',
    'lang.en_full': 'English',
};

const dictionaries: Record<Locale, Record<string, string>> = { ru, tg };

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
    const page = usePage<{ locale?: Locale }>();
    const locale: Locale = page.props.locale === 'tg' ? 'tg' : 'ru';

    const t = useCallback<Translator>(
        (key, params) => {
            const value =
                dictionaries[locale][key] ?? dictionaries.ru[key] ?? key;

            return interpolate(value, params);
        },
        [locale],
    );

    const value = useMemo(() => ({ locale, t }), [locale, t]);

    return (
        <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
    );
}

export function useT() {
    return useContext(I18nContext);
}
