<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ICONS, RIBBON_SWATCHES, SHIELD_SWATCHES, badgeSVG } from '@/lib/badges/art';
import { type BadgeConfig, loadBadgeFont } from '@/lib/badges/export';
import { computed, onMounted, ref } from 'vue';

const config = defineModel<BadgeConfig>({ required: true });

defineProps<{
    errors?: Record<string, string>;
}>();

const previewStars = ref(3);
const fontReady = ref(false);

onMounted(async () => {
    await loadBadgeFont();
    fontReady.value = true;
});

const update = (fields: Partial<BadgeConfig>) => {
    config.value = { ...config.value, ...fields };
};

const updateText = (value: string | number) => {
    update({ text: String(value).replace(/\s+/g, '').slice(0, 20) });
};

// fontReady entra na dependência: com a fonte carregada, o texto é medido de novo.
const preview = computed(() => (fontReady.value, badgeSVG({ ...config.value, stars: previewStars.value }, 'editor')));

const illustrations = computed(() =>
    Object.entries(ICONS as Record<string, { label: string }>).map(([key, { label }]) => ({
        key,
        label,
        svg: badgeSVG({ icon: key, text: '', shield: config.value.shield, ribbon: config.value.ribbon }, `opt${key}`),
    })),
);

const colorGroups = computed(() => [
    { key: 'shield' as const, label: 'Cor do brasão', swatches: SHIELD_SWATCHES as [string, string][] },
    { key: 'ribbon' as const, label: 'Cor da faixa', swatches: RIBBON_SWATCHES as [string, string][] },
]);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col items-center gap-3 rounded-lg border bg-gray-50 p-4 dark:bg-gray-800/50">
            <div class="aspect-square w-48" role="img" :aria-label="`Prévia do badge, ${previewStars} estrelas`" v-html="preview" />
            <div class="flex items-center gap-1" role="group" aria-label="Estrelas na prévia">
                <button
                    v-for="stars in 5"
                    :key="stars"
                    type="button"
                    class="h-8 w-8 rounded-md border text-sm font-bold transition-colors"
                    :class="
                        previewStars === stars
                            ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900'
                            : 'border-gray-200 dark:border-gray-700'
                    "
                    :aria-pressed="previewStars === stars"
                    @click="previewStars = stars"
                >
                    {{ stars }}
                </button>
            </div>
        </div>

        <div class="space-y-2">
            <Label for="badge-text">Texto da faixa</Label>
            <Input
                id="badge-text"
                :model-value="config.text"
                maxlength="20"
                placeholder="Ex.: Penalista"
                autocomplete="off"
                @update:model-value="updateText"
            />
            <p class="text-xs text-muted-foreground">Uma palavra, com até 20 letras.</p>
            <p v-if="errors?.['badge_config.text']" class="text-sm text-destructive">{{ errors['badge_config.text'] }}</p>
        </div>

        <div class="space-y-2">
            <Label>Ilustração</Label>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <button
                    v-for="option in illustrations"
                    :key="option.key"
                    type="button"
                    class="flex flex-col items-center gap-1 rounded-lg border-2 p-2 text-center text-xs font-medium transition-colors"
                    :class="
                        config.icon === option.key
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                    "
                    :aria-pressed="config.icon === option.key"
                    @click="update({ icon: option.key })"
                >
                    <span class="aspect-square w-16" aria-hidden="true" v-html="option.svg" />
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div v-for="group in colorGroups" :key="group.key" class="space-y-2">
            <Label>{{ group.label }}</Label>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="[name, color] in group.swatches"
                    :key="color"
                    type="button"
                    class="h-9 w-9 rounded-full border-2 transition-all"
                    :class="
                        config[group.key].toLowerCase() === color.toLowerCase()
                            ? 'scale-110 border-gray-900 dark:border-white'
                            : 'border-transparent hover:scale-105'
                    "
                    :style="{ backgroundColor: color }"
                    :title="name"
                    :aria-label="name"
                    :aria-pressed="config[group.key].toLowerCase() === color.toLowerCase()"
                    @click="update({ [group.key]: color })"
                />
                <input
                    type="color"
                    class="h-9 w-9 cursor-pointer rounded-full border-0 bg-transparent p-0"
                    :value="config[group.key]"
                    :aria-label="`${group.label}: outra cor`"
                    title="Outra cor"
                    @input="update({ [group.key]: ($event.target as HTMLInputElement).value })"
                />
            </div>
        </div>
    </div>
</template>
