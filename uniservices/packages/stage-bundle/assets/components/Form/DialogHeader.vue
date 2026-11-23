<script setup lang="ts">
import { computed, type Component } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { TONE_CLASSES, type ToneColor } from './tones';

const props = withDefaults(defineProps<{
  title: string;
  subtitle?: string;
  icon?: Component;
  tone?: ToneColor;
  closable?: boolean;
}>(), {
  tone: 'teal',
  closable: true
});

defineEmits<{
  (e: 'close'): void;
}>();

const toneClasses = computed(() => TONE_CLASSES[props.tone] || TONE_CLASSES.teal);
</script>

<template>
  <div class="flex items-start justify-between gap-4 w-full pb-4 border-b border-surface ">
    <div class="flex items-center gap-3">
      <div
        v-if="icon"
        :class="['w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border shadow-2xs', toneClasses.container]"
      >
        <component :is="icon" :class="['w-5 h-5', toneClasses.icon]" />
      </div>
      <div>
        <h3 class="text-base font-bold text-color -contrast leading-tight">
          {{ title }}
        </h3>
        <p v-if="subtitle" class="text-xs text-muted-color  mt-0.5">
          {{ subtitle }}
        </p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <slot name="actions" />
      <button
        v-if="closable"
        type="button"
        class="p-1.5 text-muted-color hover:text-muted-color dark:hover:text-color rounded-lg hover:bg-emphasis dark:hover:bg-emphasis transition-colors cursor-pointer"
        aria-label="Fermer"
        @click="$emit('close')"
      >
        <XMarkIcon class="w-5 h-5" />
      </button>
    </div>
  </div>
</template>
