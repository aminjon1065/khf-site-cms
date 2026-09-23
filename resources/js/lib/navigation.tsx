import {
    Activity,
    BookOpen,
    Building2,
    ClipboardCheck,
    ClipboardList,
    FileStack,
    FileText,
    FolderKanban,
    Gauge,
    Home,
    Image,
    Inbox,
    LayoutDashboard,
    Map,
    Megaphone,
    Network,
    Newspaper,
    Settings,
    ShieldCheck,
    Tags,
    TriangleAlert,
    Trash2,
    Languages,
    UserCog,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import ActivityController from '@/actions/App/Http/Controllers/Cms/ActivityController';
import AlertController from '@/actions/App/Http/Controllers/Cms/AlertController';
import AnnouncementController from '@/actions/App/Http/Controllers/Cms/AnnouncementController';
import ApprovalController from '@/actions/App/Http/Controllers/Cms/ApprovalController';
import DocumentController from '@/actions/App/Http/Controllers/Cms/DocumentController';
import EditorialTrashController from '@/actions/App/Http/Controllers/Cms/EditorialTrashController';
import HomeBlockController from '@/actions/App/Http/Controllers/Cms/HomeBlockController';
import InstructionController from '@/actions/App/Http/Controllers/Cms/InstructionController';
import LeaderController from '@/actions/App/Http/Controllers/Cms/LeaderController';
import MediaController from '@/actions/App/Http/Controllers/Cms/MediaController';
import MenuController from '@/actions/App/Http/Controllers/Cms/MenuController';
import NewsController from '@/actions/App/Http/Controllers/Cms/NewsController';
import PageController from '@/actions/App/Http/Controllers/Cms/PageController';
import ProjectController from '@/actions/App/Http/Controllers/Cms/ProjectController';
import RegionController from '@/actions/App/Http/Controllers/Cms/RegionController';
import RoleController from '@/actions/App/Http/Controllers/Cms/RoleController';
import SettingController from '@/actions/App/Http/Controllers/Cms/SettingController';
import StructureUnitController from '@/actions/App/Http/Controllers/Cms/StructureUnitController';
import SubmissionController from '@/actions/App/Http/Controllers/Cms/SubmissionController';
import TaxonomyController from '@/actions/App/Http/Controllers/Cms/TaxonomyController';
import TranslationQueueController from '@/actions/App/Http/Controllers/Cms/TranslationQueueController';
import UserController from '@/actions/App/Http/Controllers/Cms/UserController';
import { control, dashboard, usability } from '@/routes';

export interface NavItem {
    key: string;
    labelKey: string;
    href: string;
    icon: LucideIcon;
    badge?: 'approval' | 'alerts';
    /** Single ability, or a list where ANY one grants visibility. */
    permission?: string | string[];
}

export interface NavGroup {
    labelKey: string;
    /**
     * Sections staff open now and then: folded until opened, and always open
     * while one of them is the current page.
     */
    collapsible?: boolean;
    items: NavItem[];
}

/**
 * Content modules whose permissions gate shared editorial screens
 * (approval center, translation queue, trash). Mirrors
 * App\Support\ContentTypes::META module names.
 */
const CONTENT_MODULES = [
    'alerts',
    'news',
    'instructions',
    'documents',
    'projects',
    'announcements',
    'pages',
] as const;

export { navItemAllowed } from '@/lib/permissions';

/**
 * The sidebar. Daily work first — what needs attention, then the materials
 * of the site; settings of the site itself and administration are folded.
 * Notifications live behind the bell in the top bar.
 */
export const NAV: NavGroup[] = [
    {
        labelKey: 'nav.group.work',
        items: [
            {
                key: 'dashboard',
                labelKey: 'nav.dashboard',
                href: dashboard.url(),
                icon: LayoutDashboard,
            },
            {
                key: 'approvals',
                labelKey: 'nav.approvals',
                href: ApprovalController.index.url(),
                icon: ClipboardCheck,
                badge: 'approval',
                permission: CONTENT_MODULES.map((m) => `${m}.approve`),
            },
            {
                key: 'alerts',
                labelKey: 'nav.alerts',
                href: AlertController.index.url(),
                icon: TriangleAlert,
                badge: 'alerts',
                permission: 'alerts.view',
            },
            {
                key: 'control',
                labelKey: 'nav.control_center',
                href: control.url(),
                icon: Gauge,
                permission: 'alerts.view',
            },
            {
                key: 'submissions',
                labelKey: 'Обращения граждан',
                href: SubmissionController.index.url(),
                icon: Inbox,
                permission: 'submissions.view',
            },
        ],
    },
    {
        labelKey: 'nav.group.content',
        items: [
            {
                key: 'news',
                labelKey: 'nav.news',
                href: NewsController.index.url(),
                icon: Newspaper,
                permission: 'news.view',
            },
            {
                key: 'instructions',
                labelKey: 'nav.instructions',
                href: InstructionController.index.url(),
                icon: BookOpen,
                permission: 'instructions.view',
            },
            {
                key: 'documents',
                labelKey: 'nav.documents',
                href: DocumentController.index.url(),
                icon: FileStack,
                permission: 'documents.view',
            },
            {
                key: 'announcements',
                labelKey: 'nav.announcements',
                href: AnnouncementController.index.url(),
                icon: Megaphone,
                permission: 'announcements.view',
            },
            {
                key: 'projects',
                labelKey: 'Проекты',
                href: ProjectController.index.url(),
                icon: FolderKanban,
                permission: 'projects.view',
            },
            {
                key: 'pages',
                labelKey: 'nav.pages',
                href: PageController.index.url(),
                icon: FileText,
                permission: 'pages.view',
            },
            {
                key: 'media',
                labelKey: 'nav.media',
                href: MediaController.index.url(),
                icon: Image,
                permission: 'media.view',
            },
            {
                key: 'editorial-translations',
                labelKey: 'Очередь переводов',
                href: TranslationQueueController.index.url(),
                icon: Languages,
                permission: CONTENT_MODULES.map((m) => `${m}.edit`),
            },
            {
                key: 'editorial-trash',
                labelKey: 'Корзина материалов',
                href: EditorialTrashController.index.url(),
                icon: Trash2,
                permission: CONTENT_MODULES.map((m) => `${m}.view`),
            },
        ],
    },
    {
        labelKey: 'nav.group.site',
        collapsible: true,
        items: [
            {
                key: 'home',
                labelKey: 'nav.home_blocks',
                href: HomeBlockController.index.url(),
                icon: Home,
                permission: 'home.view',
            },
            {
                key: 'menu',
                labelKey: 'Меню сайта',
                href: MenuController.index.url(),
                icon: Map,
                permission: 'settings.view',
            },
            {
                key: 'leadership',
                labelKey: 'Руководство',
                href: LeaderController.index.url(),
                icon: UserCog,
                permission: 'leadership.view',
            },
            {
                key: 'structure',
                labelKey: 'Структура',
                href: StructureUnitController.index.url(),
                icon: Network,
                permission: 'structure.view',
            },
            {
                key: 'regions',
                labelKey: 'Регионы и районы',
                href: RegionController.index.url(),
                icon: Building2,
                permission: 'regions.view',
            },
            {
                key: 'taxonomy',
                labelKey: 'Рубрики и метки',
                href: TaxonomyController.index.url(),
                icon: Tags,
                permission: 'taxonomy.view',
            },
        ],
    },
    {
        labelKey: 'nav.group.admin',
        collapsible: true,
        items: [
            {
                key: 'users',
                labelKey: 'nav.users',
                href: UserController.index.url(),
                icon: Users,
                permission: 'users.view',
            },
            {
                key: 'roles',
                labelKey: 'nav.roles',
                href: RoleController.index.url(),
                icon: ShieldCheck,
                permission: 'users.view',
            },
            {
                key: 'settings',
                labelKey: 'nav.settings',
                href: SettingController.index.url(),
                icon: Settings,
                permission: 'settings.view',
            },
            {
                key: 'activity',
                labelKey: 'nav.activity',
                href: ActivityController.index.url(),
                icon: Activity,
                permission: 'users.view',
            },
            {
                // A research tool for testing the CMS with staff, not a
                // working screen: administrators only.
                key: 'usability',
                labelKey: 'Проверка удобства',
                href: usability.url(),
                icon: ClipboardList,
                permission: 'settings.edit',
            },
        ],
    },
];

/**
 * The "+ Создать" menu (6 content types).
 */
export const CREATE_ITEMS: {
    key: string;
    labelKey: string;
    /** What is created, in the singular: «+ Новость». */
    createLabel: string;
    href: string;
    permission?: string;
    tone?: string;
}[] = [
    {
        key: 'alert',
        labelKey: 'nav.alerts',
        createLabel: 'Предупреждение',
        href: AlertController.create.url(),
        permission: 'alerts.create',
        tone: 'warn',
    },
    {
        key: 'news',
        labelKey: 'nav.news',
        createLabel: 'Новость',
        href: NewsController.create.url(),
        permission: 'news.create',
    },
    {
        key: 'instruction',
        labelKey: 'nav.instructions',
        createLabel: 'Инструкция',
        href: InstructionController.create.url(),
        permission: 'instructions.create',
    },
    {
        key: 'document',
        labelKey: 'nav.documents',
        createLabel: 'Документ',
        href: DocumentController.create.url(),
        permission: 'documents.create',
    },
    {
        key: 'project',
        labelKey: 'Проекты',
        createLabel: 'Проект',
        href: ProjectController.create.url(),
        permission: 'projects.create',
    },
    {
        key: 'announcement',
        labelKey: 'nav.announcements',
        createLabel: 'Объявление',
        href: AnnouncementController.create.url(),
        permission: 'announcements.create',
    },
    {
        key: 'page',
        labelKey: 'nav.pages',
        createLabel: 'Страница',
        href: PageController.create.url(),
        permission: 'pages.create',
    },
];
