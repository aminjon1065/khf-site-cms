import {
    Activity,
    Bell,
    BookOpen,
    Building2,
    ClipboardCheck,
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
    Newspaper,
    Phone,
    Settings,
    ShieldCheck,
    Tags,
    TriangleAlert,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

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
                href: '/dashboard',
                icon: LayoutDashboard,
            },
            {
                key: 'control',
                labelKey: 'nav.control_center',
                href: '/control',
                icon: Gauge,
            },
            {
                key: 'approvals',
                labelKey: 'nav.approvals',
                href: '/approvals',
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
                href: '/alerts',
                icon: TriangleAlert,
                badge: 'alerts',
                permission: 'alerts.view',
            },
            {
                key: 'notify',
                labelKey: 'nav.notifications',
                href: '/section/notify',
                icon: Bell,
            },
            {
                key: 'map',
                labelKey: 'Карта регионов',
                href: '/control',
                icon: Map,
            },
            {
                key: 'contacts',
                labelKey: 'Экстренные контакты',
                href: '/section/contacts',
                icon: Phone,
            },
            {
                key: 'submissions',
                labelKey: 'Обращения граждан',
                href: '/submissions',
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
                href: '/news',
                icon: Newspaper,
                permission: 'news.view',
            },
            {
                key: 'instructions',
                labelKey: 'nav.instructions',
                href: '/instructions',
                icon: BookOpen,
                permission: 'instructions.view',
            },
            {
                key: 'pages',
                labelKey: 'nav.pages',
                href: '/pages',
                icon: FileText,
                permission: 'pages.view',
            },
            {
                key: 'documents',
                labelKey: 'nav.documents',
                href: '/documents',
                icon: FileStack,
                permission: 'documents.view',
            },
            {
                key: 'announcements',
                labelKey: 'nav.announcements',
                href: '/announcements',
                icon: Megaphone,
                permission: 'announcements.view',
            },
            {
                key: 'projects',
                labelKey: 'Проекты',
                href: '/projects',
                icon: FolderKanban,
                permission: 'projects.view',
            },
            {
                key: 'regions',
                labelKey: 'Регионы и районы',
                href: '/regions',
                icon: Building2,
                permission: 'regions.view',
            },
        ],
    },
    {
        labelKey: 'nav.group.management',
        items: [
            {
                key: 'media',
                labelKey: 'nav.media',
                href: '/media',
                icon: Image,
                permission: 'media.view',
            },
            {
                key: 'taxonomy',
                labelKey: 'Категории и теги',
                href: '/taxonomy',
                icon: Tags,
                permission: 'taxonomy.view',
            },
            {
                key: 'menu',
                labelKey: 'Меню сайта',
                href: '/menu',
                icon: Map,
                permission: 'settings.view',
            },
            {
                key: 'home',
                labelKey: 'nav.home_blocks',
                href: '/home-blocks',
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
                href: '/users',
                icon: Users,
                permission: 'users.view',
            },
            {
                key: 'roles',
                labelKey: 'nav.roles',
                href: '/roles',
                icon: ShieldCheck,
                permission: 'users.view',
            },
            {
                key: 'activity',
                labelKey: 'nav.activity',
                href: '/activity',
                icon: Activity,
                permission: 'users.view',
            },
            {
                key: 'settings',
                labelKey: 'nav.settings',
                href: '/settings',
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
        href: '/alerts/create',
        permission: 'alerts.create',
        tone: 'warn',
    },
    {
        key: 'news',
        labelKey: 'nav.news',
        href: '/news/create',
        permission: 'news.create',
    },
    {
        key: 'instruction',
        labelKey: 'nav.instructions',
        href: '/instructions/create',
        permission: 'instructions.create',
    },
    {
        key: 'document',
        labelKey: 'nav.documents',
        href: '/documents/create',
        permission: 'documents.create',
    },
    {
        key: 'project',
        labelKey: 'Проекты',
        href: '/projects/create',
        permission: 'projects.create',
    },
    {
        key: 'announcement',
        labelKey: 'nav.announcements',
        href: '/announcements/create',
        permission: 'announcements.create',
    },
    {
        key: 'page',
        labelKey: 'nav.pages',
        href: '/pages/create',
        permission: 'pages.create',
    },
];
