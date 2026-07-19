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
                href: '/section/pages',
                icon: FileText,
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
                href: '/section/announcements',
                icon: Megaphone,
            },
            {
                key: 'projects',
                labelKey: 'Проекты',
                href: '/section/projects',
                icon: FolderKanban,
            },
            {
                key: 'regions',
                labelKey: 'Региональные подразделения',
                href: '/section/regions',
                icon: Building2,
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
                href: '/section/taxonomy',
                icon: Tags,
            },
            {
                key: 'menu',
                labelKey: 'Меню сайта',
                href: '/section/menu',
                icon: Map,
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
        key: 'announcement',
        labelKey: 'nav.announcements',
        href: '/section/announcements',
    },
    { key: 'page', labelKey: 'nav.pages', href: '/section/pages' },
];
