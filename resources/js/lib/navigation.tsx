import {
    Activity,
    Bell,
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
    Phone,
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
import EmergencyContactController from '@/actions/App/Http/Controllers/Cms/EmergencyContactController';
import HomeBlockController from '@/actions/App/Http/Controllers/Cms/HomeBlockController';
import InstructionController from '@/actions/App/Http/Controllers/Cms/InstructionController';
import LeaderController from '@/actions/App/Http/Controllers/Cms/LeaderController';
import MediaController from '@/actions/App/Http/Controllers/Cms/MediaController';
import MenuController from '@/actions/App/Http/Controllers/Cms/MenuController';
import NewsController from '@/actions/App/Http/Controllers/Cms/NewsController';
import NotificationController from '@/actions/App/Http/Controllers/Cms/NotificationController';
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
    permission?: string;
}

export interface NavGroup {
    labelKey: string;
    items: NavItem[];
}

export const NAV: NavGroup[] = [
    {
        labelKey: 'nav.group.overview',
        items: [
            {
                key: 'dashboard',
                labelKey: 'nav.dashboard',
                href: dashboard.url(),
                icon: LayoutDashboard,
            },
            {
                key: 'control',
                labelKey: 'nav.control_center',
                href: control.url(),
                icon: Gauge,
                permission: 'alerts.view',
            },
            {
                key: 'approvals',
                labelKey: 'nav.approvals',
                href: ApprovalController.index.url(),
                icon: ClipboardCheck,
                badge: 'approval',
            },
        ],
    },
    {
        labelKey: 'nav.group.operational',
        items: [
            {
                key: 'alerts',
                labelKey: 'nav.alerts',
                href: AlertController.index.url(),
                icon: TriangleAlert,
                badge: 'alerts',
                permission: 'alerts.view',
            },
            {
                key: 'notify',
                labelKey: 'nav.notifications',
                href: NotificationController.index.url(),
                icon: Bell,
            },
            {
                key: 'contacts',
                labelKey: 'Экстренные контакты',
                href: EmergencyContactController.index.url(),
                icon: Phone,
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
                key: 'pages',
                labelKey: 'nav.pages',
                href: PageController.index.url(),
                icon: FileText,
                permission: 'pages.view',
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
                key: 'editorial-translations',
                labelKey: 'Очередь переводов',
                href: TranslationQueueController.index.url(),
                icon: Languages,
                permission: 'news.edit',
            },
            {
                key: 'editorial-trash',
                labelKey: 'Корзина материалов',
                href: EditorialTrashController.index.url(),
                icon: Trash2,
                permission: 'news.view',
            },
            {
                key: 'regions',
                labelKey: 'Регионы и районы',
                href: RegionController.index.url(),
                icon: Building2,
                permission: 'regions.view',
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
        ],
    },
    {
        labelKey: 'nav.group.management',
        items: [
            {
                key: 'media',
                labelKey: 'nav.media',
                href: MediaController.index.url(),
                icon: Image,
                permission: 'media.view',
            },
            {
                key: 'taxonomy',
                labelKey: 'Категории и теги',
                href: TaxonomyController.index.url(),
                icon: Tags,
                permission: 'taxonomy.view',
            },
            {
                key: 'menu',
                labelKey: 'Меню сайта',
                href: MenuController.index.url(),
                icon: Map,
                permission: 'settings.view',
            },
            {
                key: 'home',
                labelKey: 'nav.home_blocks',
                href: HomeBlockController.index.url(),
                icon: Home,
                permission: 'home.view',
            },
        ],
    },
    {
        labelKey: 'nav.group.system',
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
                key: 'activity',
                labelKey: 'nav.activity',
                href: ActivityController.index.url(),
                icon: Activity,
                permission: 'users.view',
            },
            {
                key: 'usability',
                labelKey: 'Проверка удобства',
                href: usability.url(),
                icon: ClipboardList,
                permission: 'users.view',
            },
            {
                key: 'settings',
                labelKey: 'nav.settings',
                href: SettingController.index.url(),
                icon: Settings,
                permission: 'settings.view',
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
    href: string;
    permission?: string;
    tone?: string;
}[] = [
    {
        key: 'alert',
        labelKey: 'nav.alerts',
        href: AlertController.create.url(),
        permission: 'alerts.create',
        tone: 'warn',
    },
    {
        key: 'news',
        labelKey: 'nav.news',
        href: NewsController.create.url(),
        permission: 'news.create',
    },
    {
        key: 'instruction',
        labelKey: 'nav.instructions',
        href: InstructionController.create.url(),
        permission: 'instructions.create',
    },
    {
        key: 'document',
        labelKey: 'nav.documents',
        href: DocumentController.create.url(),
        permission: 'documents.create',
    },
    {
        key: 'project',
        labelKey: 'Проекты',
        href: ProjectController.create.url(),
        permission: 'projects.create',
    },
    {
        key: 'announcement',
        labelKey: 'nav.announcements',
        href: AnnouncementController.create.url(),
        permission: 'announcements.create',
    },
    {
        key: 'page',
        labelKey: 'nav.pages',
        href: PageController.create.url(),
        permission: 'pages.create',
    },
];
