<script setup lang="ts">
import SimpleSkeleton from './loader/SimpleSkeleton.vue';

const props = withDefaults(defineProps<{
  label: string;
  value: string | number;
  icon?: any;
  color?: string;
  description?: string;
  loading?: boolean;
  /** Tuile compacte : libellé discret, valeur et alerte facultative. */
  compact?: boolean;
  alerte?: string;
}>(), {
  icon: null,
  color: 'primary',
  loading: false,
  compact: false,
  alerte: '',
});
</script>

<template>
  <div v-if="props.compact" class="card p-3 m-0 flex flex-col gap-1">
    <span class="text-sm text-muted-color">{{ props.label }}</span>
    <SimpleSkeleton v-if="props.loading" class="w-1/2" />
    <span v-else class="text-2xl font-semibold text-color">{{ props.value }}</span>
    <Message v-if="props.alerte" severity="warn" size="small" variant="simple">{{ props.alerte }}</Message>
  </div>
  <div v-else class="card w-full flex items-center justify-center flex-col">
    <div class="font-bold text-lg card-header text-center">{{ props.label }}</div>
    <div class="flex items-center gap-2 card-body">
      <i v-if="props.icon" :class="[props.icon, `text-${props.color}`]" class="text-3xl!" aria-hidden="true"></i>
      <SimpleSkeleton v-if="props.loading" class="w-1/2" />
      <span v-else :class="`text-${props.color}`" class="text-4xl font-extrabold">{{ props.value }}</span>
    </div>
    <p v-if="props.description" class="text-xs text-muted-color mt-1">
      {{ props.description }}
    </p>
  </div>
</template>
