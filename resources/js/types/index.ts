import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { AvatarConfig } from '@/lib/avatar';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    iconPath?: string;
    isActive?: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    /** Avatar montado no criador. Nulo para quem nunca editou. */
    avatar_config?: AvatarConfig | null;
    email_verified_at?: string | null;
    current_streak?: number;
    created_at?: string;
    updated_at?: string;
    lives?: number;
    xp?: number;
    has_infinite_lives?: boolean;
    is_admin?: boolean;
    debug_info?: {
        has_active_subscription: boolean;
        on_trial: boolean;
        subscribed: boolean;
        trial_ends_at: string | null;
    };
}

export interface Discipline {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description?: string;
    legal_references_count?: number;
}

export interface DisciplineProgress {
    discipline: Discipline;
    total_xp: number;
    level: number;
    current_xp_in_level: number;
    xp_for_next_level: number;
    progress_percent: number;
}

export type BreadcrumbItemType = BreadcrumbItem;
