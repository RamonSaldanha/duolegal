<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    href: string;
    tabindex?: number;
    method?: string;
    as?: string;
}

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="hover:!decoration-current text-foreground underline decoration-gray-300 underline-offset-4 transition-colors duration-300 ease-out dark:decoration-gray-500"
    >
        <slot />
    </Link>
</template>
