<script setup lang="ts">
import { computed } from 'vue';
import type { ViewMode } from '@types';

interface Props {
  viewMode: ViewMode;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  changeView: [mode: ViewMode];
}>();

const affichages = [
  { valeur: 'grid' as ViewMode, libelle: 'Grille', icone: 'pi pi-th-large' },
  { valeur: 'list' as ViewMode, libelle: 'Liste', icone: 'pi pi-list' },
];

const affichage = computed({
  get: () => props.viewMode,
  set: (mode: ViewMode) => emit('changeView', mode),
});
</script>

<template>
  <SelectButton
      v-model="affichage"
      :options="affichages"
      option-label="libelle"
      option-value="valeur"
      :allow-empty="false"
      aria-label="Affichage"
  >
    <template #option="{ option }">
      <i :class="option.icone" aria-hidden="true"></i>
      <span>{{ option.libelle }}</span>
    </template>
  </SelectButton>
</template>
