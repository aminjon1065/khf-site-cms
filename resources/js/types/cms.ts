import type { Locale } from '@/lib/i18n';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    initials: string;
    position: string | null;
    department: string | null;
    region_id: number | null;
    role: string | null;
    role_label: string | null;
    permissions: string[];
    is_super: boolean;
    two_factor_enabled: boolean;
    interface_locale: string;
}

export interface NavBadges {
    approval?: number;
    alerts?: number;
    [key: string]: number | undefined;
}

export interface AppNotification {
    id: string;
    title: string;
    message: string;
    tone: string;
    read_at: string | null;
    created_at: string;
    created_diff: string;
    subject_type: string | null;
    subject_id: number | null;
    url: string | null;
}

export interface FlashBag {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}

export interface SharedProps {
    name: string;
    auth: { user: AuthUser | null };
    locale: Locale;
    flash: FlashBag;
    nav_badges: NavBadges;
    notifications: { unread: number; items: AppNotification[] };
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
