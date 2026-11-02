<script setup>
import {computed} from 'vue';
import {semestresParAnnee} from '@/utils/scolarite.js';

// Les semestres en pastilles, groupés par année : tous visibles, un clic pour changer.
const props = defineProps({
  semestres: {type: Array, required: true},
});
const choisi = defineModel({type: Number, default: null});

const annees = computed(() => semestresParAnnee(props.semestres));
</script>

<template>
  <fieldset class="selecteur">
    <legend class="sr-only">Semestre</legend>
    <div v-for="groupe in annees" :key="groupe.annee" class="selecteur__annee">
      <span class="selecteur__libelle" aria-hidden="true">{{ groupe.annee }}</span>
      <button
          v-for="semestre in groupe.semestres"
          :key="semestre.id"
          type="button"
          :class="['pastille', { 'pastille--choisie': semestre.id === choisi }]"
          :aria-pressed="semestre.id === choisi"
          :aria-label="`${semestre.libelle}, ${groupe.annee}${semestre.actif ? ', année en cours' : ''}`"
          @click="choisi = semestre.id"
      >{{ semestre.libelle }}</button>
    </div>
  </fieldset>
</template>

<style scoped>
.selecteur {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1.25rem;
  margin: 0;
  padding: 0;
  border: 0;
}

.selecteur__annee {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.selecteur__libelle {
  margin-right: 0.25rem;
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
}

.pastille {
  min-width: 44px;
  min-height: 44px;
  padding: 0 1rem;
  border: 1px solid var(--ui-border-color, var(--p-content-border-color));
  border-radius: 999px;
  background: var(--p-content-background);
  color: var(--p-text-color);
  font-weight: 600;
  cursor: pointer;
  transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease;
}

.pastille:hover {
  background: var(--p-content-hover-background);
}

/* La primaire porte du texte blanc : 9,95:1 en clair. */
.pastille--choisie,
.pastille--choisie:hover {
  border-color: var(--p-primary-color);
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}

.pastille:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
  .pastille {
    transition: none;
  }
}
</style>
