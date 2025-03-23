<script setup lang="ts">
// import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineProps({
  height: {
    type: String,
    default: '90px'
  },
  width: {
    type: String,
    default: '110px'
  }
});
</script>

<template>
    <div class="grid flex-1 text-left text-sm">
        <!-- <span class="mb-0.5 truncate font-semibold leading-none">Memorize Direito</span> -->
        <img 
            src="/img/logomemorizeblack.svg"
            :style="{ height, width }"
            class="mt-[-16px] block dark:hidden" 
            alt="Logo" 
        />
        <img 
            src="/img/logomemorize.svg"
            :style="{ height, width }"
            class="mt-[-16px] hidden dark:block" 
            alt="Logo" 
        />
    </div>
</template>
