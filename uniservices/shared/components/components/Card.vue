<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: ''
  },
  icon: {
    type: [String, Object, Function],
    default: null
  },
  color: {
    type: String,
    default: ''
  },
  iconClass: {
    type: String,
    default: ''
  },
  iconBgClass: {
    type: String,
    default: ''
  },
  badge: {
    type: String,
    default: ''
  },
  badgeSeverity: {
    type: String,
    default: 'secondary'
  },
  bodyClass: {
    type: String,
    default: ''
  }
});

const colorMap = {
  blue: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  green: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  emerald: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  yellow: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  amber: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  purple: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  violet: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  indigo: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  red: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  pink: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  teal: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  orange: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  },
  gray: {
    bg: 'bg-highlight',
    text: 'text-primary',
    border: 'border-surface'
  }
};

const colorClasses = computed(() => {
  if (!props.color) return null;
  return colorMap[props.color] || colorMap.blue;
});
</script>

<template>
  <div class="card">
    <div v-if="title || $slots.header" class="card-header flex justify-between items-center gap-4">
      <slot name="header">
        <div class="flex flex-col min-w-0">
          <div class="flex items-center gap-3">
            <span v-if="icon" :class="[
              'flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center border transition-all duration-300',
              colorClasses ? `${colorClasses.bg} ${colorClasses.text} ${colorClasses.border}` : 'bg-emphasis border-surface',
              iconBgClass
            ]">
              <!-- Support PrimeIcons string and Heroicons component -->
              <i v-if="typeof icon === 'string'" :class="[icon, 'text-sm', colorClasses ? '' : iconClass]" />
              <component v-else :is="icon" :class="['w-4 h-4', colorClasses ? '' : iconClass]" />
            </span>
            <h3 class="text-sm font-bold text-color leading-snug">{{ title }}</h3>
          </div>
          <p v-if="subtitle" :class="['text-sm text-muted-color mt-0.5 leading-normal', icon ? 'pl-11' : '']">
            {{ subtitle }}
          </p>
        </div>
        <div v-if="badge" class="flex-shrink-0">
          <Tag :value="badge" :severity="badgeSeverity" class="text-xs font-bold font-sans" />
        </div>
      </slot>
    </div>
    <div :class="['card-body', bodyClass]">
      <slot />
    </div>
  </div>
</template>

<style scoped></style>
