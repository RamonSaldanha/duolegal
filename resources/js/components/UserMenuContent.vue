<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { LogOut, Settings, FileText, Scale, Users } from 'lucide-vue-next';

interface Props {
    user: User;
    isAdmin?: boolean;
}

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" as="button">
                <Settings class="mr-2 h-4 w-4" />
                Preferências
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <template v-if="isAdmin">
        <DropdownMenuSeparator />
        <DropdownMenuGroup>
            <DropdownMenuItem :as-child="true">
                <Link class="block w-full" href="/admin/create-lawarticle" as="button">
                    <FileText class="mr-2 h-4 w-4" />
                    Criar artigo
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem :as-child="true">
                <Link class="block w-full" href="/admin/legislations" as="button">
                    <Scale class="mr-2 h-4 w-4" />
                    Legislações
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem :as-child="true">
                <Link class="block w-full" href="/admin/users" as="button">
                    <Users class="mr-2 h-4 w-4" />
                    Usuários
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>
    </template>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" method="post" :href="route('logout')" as="button">
            <LogOut class="mr-2 h-4 w-4" />
            Sair
        </Link>
    </DropdownMenuItem>
</template>
