<script setup>
import {computed} from 'vue';
import {LigneAccueil} from '@components';
import {formatDate, formatNote, resultatEvaluation, tonEvaluation} from '@/utils/scolarite.js';
import PastilleNote from './PastilleNote.vue';

const props = defineProps({
  semestre: {type: Object, required: true},
});

// Toutes les évaluations du semestre, de la plus récente à la plus ancienne, celles à venir en tête.
const evaluations = computed(() => props.semestre.ues
    .flatMap(ue => ue.enseignements.flatMap(enseignement => enseignement.evaluations.map(evaluation => ({...evaluation, enseignement}))))
    .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? '')));
</script>

<template>
  <p v-if="!evaluations.length" class="m-0!">Aucune évaluation pour ce semestre.</p>
  <ul v-else class="notes">
    <LigneAccueil v-for="evaluation in evaluations" :key="`${evaluation.enseignement.code}-${evaluation.libelle}-${evaluation.date}`">
      <template #titre>{{ evaluation.libelle }}</template>
      <template #detail>
        {{ evaluation.enseignement.code }} {{ evaluation.enseignement.libelle }},
        <time :datetime="evaluation.date">{{ formatDate(evaluation.date) }}</time>, coef. {{ formatNote(evaluation.coefficient) }}
      </template>
      <template #valeur>
        <PastilleNote :ton="tonEvaluation(evaluation)">{{ resultatEvaluation(evaluation).texte }}</PastilleNote>
      </template>
    </LigneAccueil>
  </ul>
</template>

<style scoped>
.notes {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  margin: 0;
  padding: 0;
}
</style>
