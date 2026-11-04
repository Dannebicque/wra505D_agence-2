<template>
  <component
    :is="to ? 'router-link' : 'button'"
    :to="to"
    :disabled="disabled"
    :aria-disabled="disabled || undefined"
    :type="to ? undefined : 'button'"
    class="flex flex-col items-center justify-center p-3 rounded-xl border transition-colors duration-200 group text-center min-w-[120px]"
    :class="[
      severityClasses,
      disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer shadow-sm'
    ]"
    @click="handleClick"
  >
    <i v-if="typeof icon === 'string'" :class="[icon, 'text-xl mb-1.5']" aria-hidden="true" />
    <component v-else-if="icon" :is="icon" class="w-5 h-5 mb-1.5" aria-hidden="true" />
    <span class="text-sm font-semibold leading-tight select-none">{{ label }}</span>
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  to?: string | object;
  icon: any;
  label: string;
  severity?: 'primary' | 'secondary' | 'success' | 'info' | 'warning' | 'help' | 'danger';
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  severity: 'secondary',
  disabled: false
});

const emit = defineEmits<{
  click: [event: MouseEvent]
}>();

function handleClick(event: MouseEvent) {
  if (props.disabled) {
    event.preventDefault();
    return;
  }
  emit('click', event);
}

const severityClasses = computed(() => {
  switch (props.severity) {
    case 'primary':
      return 'bg-primary text-primary-contrast border-primary hover:brightness-95';
    case 'success':
    case 'info':
    case 'warning':
    case 'help':
      return 'bg-primary-50 hover:bg-primary-100 text-primary border-primary-200 hover:border-primary-300 dark:bg-primary-950/20 dark:hover:bg-primary-900/40 dark:text-primary-300 dark:border-primary-800';
    case 'danger':
      return 'bg-emphasis hover:bg-surface-200 text-color border-surface hover:border-primary dark:bg-surface-800 dark:hover:bg-surface-700';
    case 'secondary':
    default:
      return 'bg-surface-50 hover:bg-surface-100 text-color border-surface hover:border-surface-400 dark:bg-surface-800 dark:hover:bg-surface-700 dark:text-color dark:border-surface-600';
  }
});
</script>
