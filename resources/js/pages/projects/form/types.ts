import type { ContentStatus } from '@/lib/domain';

export type LocaleMap = { ru: string; tg: string; en: string };
export type GoalMap = { ru: string[]; tg: string[]; en: string[] };
export type TimelineItem = { date: string; text: string; tone: string };
export type Direction = { address: string; phone: string; email: string };
export type PublishMode = 'now' | 'review';

export interface Option {
    value: string;
    label: string;
}

export interface ProjectData {
    id: number;
    title: LocaleMap;
    summary: LocaleMap;
    body: LocaleMap;
    slug: string | null;
    status: ContentStatus;
    lifecycle_status: string;
    code: string | null;
    years: string | null;
    customer: string | null;
    partner: string | null;
    budget: string | null;
    goals: GoalMap;
    timeline: TimelineItem[];
    direction: Direction;
    cover_url: string | null;
    sort: number;
}
