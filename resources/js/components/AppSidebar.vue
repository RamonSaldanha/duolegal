<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from './AppLogo.vue';


const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.is_admin);

const mainNavItems = computed(() => [
    {
        title: 'Jogar',
        href: '/play',
        iconPath: '/icons/livro.png',
    },
    {
        title: 'Desafios',
        href: '/challenges',
        iconPath: '/icons/trofeu.png',
    },
    {
        title: 'Ranking',
        href: '/ranking',
        iconPath: '/icons/medalha.png',
    },
    {
        title: 'Preferências',
        href: '/user/legal-references',
        iconPath: '/icons/configuracoes.png',
    },
    ...(isAdmin.value
        ? [
            {
                title: 'Criar artigo',
                href: '/admin/create-lawarticle',
                iconPath: '/icons/configuracoes.png',
            },
        ]
        : []),
    ...(isAdmin.value
        ? [
            {
                title: 'Legislações',
                href: '/admin/legislations',
                iconPath: '/icons/configuracoes.png',
            },
        ]
        : []),
    ...(isAdmin.value
        ? [
            {
                title: 'Usuários',
                href: '/admin/users',
                iconPath: '/icons/configuracoes.png',
            },
        ]
        : []),
]);

const footerNavItems: NavItem[] = [];
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
