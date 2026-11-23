<script setup>
import {detailPenalite, formatDate, formatNote, resultatEvaluation, tonEvaluation} from '@/utils/scolarite.js';
import PastilleNote from '@/components/Etudiant/scolarite/PastilleNote.vue';

defineProps({
  notes: {type: Object, required: true},
});
</script>

<template>
  <div class="notes">
    <p v-if="detailPenalite(notes)" class="notes__penalite">
      <i class="pi pi-calendar-times" aria-hidden="true"></i>
      <span>{{ detailPenalite(notes) }}<template v-if="notes.absencesEnAttente"> · levable si le justificatif est accepté</template></span>
    </p>
    <p v-if="!notes.evaluations.length" class="m-0!">Aucune évaluation pour l'instant.</p>
    <ul v-else class="evaluations">
      <li v-for="evaluation in notes.evaluations" :key="`${evaluation.libelle}-${evaluation.date}`" class="evaluation">
        <span class="evaluation__nom">{{ evaluation.libelle }}</span>
        <span class="evaluation__meta"><time :datetime="evaluation.date">{{ formatDate(evaluation.date) }}</time> · coef. {{ formatNote(evaluation.coefficient) }}</span>
        <PastilleNote :ton="tonEvaluation(evaluation)" petite>{{ resultatEvaluation(evaluation).texte }}</PastilleNote>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.notes {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.notes__penalite {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin: 0;
  padding: 0.5rem 0.875rem;
  border-radius: 10px;
  font-size: 1rem;
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

/* La liste des évaluations de la page Notes et absences, dans son encart pâle. */
.evaluations {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  border-radius: 14px;
  background: var(--p-content-hover-background);
  list-style: none;
}

.evaluation {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 1rem;
  padding: 0.75rem 1rem;
  font-size: 1rem;
}

.evaluation + .evaluation {
  border-top: 1px solid var(--p-content-border-color);
}

.evaluation__nom {
  flex: 1 1 10rem;
}

.evaluation__meta {
  color: var(--p-text-muted-color);
}
</style>
