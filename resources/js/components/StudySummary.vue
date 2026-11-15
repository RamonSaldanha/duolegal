<script setup lang="ts">
// Resumo do material de estudo. O HTML já vem convertido do markdown pelo servidor
// (StudyMaterialService::renderSummary), sem HTML embutido e sem links inseguros.
defineProps<{ html: string }>();
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div class="study-summary" v-html="html" />
</template>

<style scoped>
.study-summary {
    @apply text-base leading-relaxed text-gray-800 dark:text-gray-200;
    overflow-wrap: anywhere;
}
.study-summary :deep(h1),
.study-summary :deep(h2),
.study-summary :deep(h3),
.study-summary :deep(h4) {
    @apply mb-2 mt-6 font-bold leading-snug text-gray-900 dark:text-white;
}
.study-summary :deep(h1) {
    @apply text-2xl;
}
.study-summary :deep(h2) {
    @apply text-xl;
}
.study-summary :deep(h3) {
    @apply text-lg;
}
.study-summary :deep(> :first-child) {
    @apply mt-0;
}
.study-summary :deep(p) {
    @apply my-3;
}
.study-summary :deep(strong) {
    @apply font-bold text-gray-900 dark:text-white;
}
.study-summary :deep(ul),
.study-summary :deep(ol) {
    @apply my-3 space-y-1.5 pl-6;
}
.study-summary :deep(ul) {
    @apply list-disc;
}
.study-summary :deep(ol) {
    @apply list-decimal;
}
.study-summary :deep(li > ul),
.study-summary :deep(li > ol) {
    @apply my-1.5;
}
.study-summary :deep(blockquote) {
    @apply my-4 border-l-4 border-blue-400 bg-blue-50 px-4 py-2 text-gray-700 dark:border-blue-500 dark:bg-blue-950/40 dark:text-gray-300;
}
.study-summary :deep(a) {
    @apply font-medium text-blue-600 underline underline-offset-2 dark:text-blue-400;
}
.study-summary :deep(code) {
    @apply rounded bg-gray-100 px-1 py-0.5 text-sm dark:bg-gray-800;
}
.study-summary :deep(hr) {
    @apply my-6 border-gray-200 dark:border-gray-700;
}
.study-summary :deep(table) {
    @apply my-4 block w-full border-collapse overflow-x-auto text-sm;
}
.study-summary :deep(th),
.study-summary :deep(td) {
    @apply border border-gray-200 px-3 py-2 text-left align-top dark:border-gray-700;
}
.study-summary :deep(th) {
    @apply bg-gray-100 font-bold text-gray-900 dark:bg-gray-800 dark:text-white;
}
</style>
