<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, Lock, Play, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';


const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.is_admin);

const mainNavItems: NavItem[] = [
    {
        title: 'Jogar',
        href: '/play',
        icon: Play,
    },
    ...(isAdmin.value
        ? [
            {
                title: 'Criar artigo',
                href: '/admin/create-lawarticle',
                icon: Lock,
            },
        ]
        : []),
        ...(isAdmin.value
        ? [
            {
                title: 'Legislações',
                href: '/admin/legislations',
                icon: BookOpen,
            },
        ]
        : []),
        ...(isAdmin.value
        ? [
            {
                title: 'Usuários',
                href: '/admin/users',
                icon: Users,
            },
        ]
        : []),
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('play.map')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
