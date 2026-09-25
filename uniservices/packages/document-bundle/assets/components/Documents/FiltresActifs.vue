<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router';

export interface FiltreActif {
  cle: string;
  libelle: string;
  retrait: RouteLocationRaw;
}

defineProps<{
  filtres: FiltreActif[];
  toutEffacer: RouteLocationRaw;
}>();
</script>

<template>
  <div v-if="filtres.length > 0" class="flex flex-wrap items-center gap-2 mb-4">
    <ul aria-label="Filtres actifs" class="flex flex-wrap items-center gap-2">
      <li v-for="filtre in filtres" :key="filtre.cle">
        <RouterLink
          :to="filtre.retrait"
          :aria-label="`Retirer le filtre ${filtre.libelle}`"
          class="filtre-actif inline-flex items-center gap-2 min-h-[44px] px-3 rounded-full border border-primary-500 bg-primary-50 text-primary-700 text-sm font-medium hover:bg-primary-100"
        >
          {{ filtre.libelle }}
          <i class="pi pi-times text-xs" aria-hidden="true"></i>
        </RouterLink>
      </li>
    </ul>
    <!-- Un seul filtre se retire déjà par sa pastille : le bouton global ne sert qu'à partir de deux. -->
    <RouterLink
      v-if="filtres.length > 1"
      :to="toutEffacer"
      class="filtre-actif inline-flex items-center min-h-[44px] px-2 text-sm font-medium text-primary-700 underline"
    >
      Tout effacer
    </RouterLink>
  </div>
</template>

<style scoped>
.filtre-actif:focus-visible {
  outline: 2px solid var(--p-primary-500);
  outline-offset: 2px;
}
</style>
