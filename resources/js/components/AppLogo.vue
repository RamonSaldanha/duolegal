<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="grid flex-1 text-left text-sm">
        <!-- <span class="mb-0.5 truncate font-semibold leading-none">Memorize Direito</span> -->
        <img 
            src="/img/logomemorizeblack.svg"
            class="mt-[-16px] h-[90px] w-[110px] block dark:hidden" 
            alt="Logo" 
        />
        <img 
            src="/img/logomemorize.svg"
            class="mt-[-16px] h-[90px] w-[110px] hidden dark:block" 
            alt="Logo" 
        />
    </div>
</template>
