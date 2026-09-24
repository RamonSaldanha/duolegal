<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import type { User } from '@/types';

interface Props {
    user: User;
    showEmail?: boolean;
}

defineProps<Props>();
</script>

<template>
    <UserAvatar :config="user.avatar_config" :name="user.name" class="size-[34px] rounded-full text-xs" />

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
    </div>
</template>
