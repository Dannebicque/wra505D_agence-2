<script setup>
import {computed} from 'vue';
import {BlocAccueil, LigneAccueil} from '@components';
import {creneauAbsence, formatNote, justificationAbsence} from '@/utils/scolarite.js';

const props = defineProps({
  semestre: {type: Object, required: true},
});

const absences = computed(() => props.semestre.absences);
// Seule une absence sans justificatif, ou au justificatif refusé, demande une action : le rouge
// lui est réservé, comme sur l'accueil.
const tonAbsence = (justification) => (['aucune', 'refusee'].includes(justification) ? 'action' : 'neutre');
</script>

<template>
  <BlocAccueil titre="Absences" icone="pi pi-flag">
    <p class="regle">
      <template v-if="semestre.penalite.active">
        Chaque absence non justifiée retire {{ formatNote(semestre.penalite.points) }} point à la moyenne de sa matière.
        Un justificatif accepté lève la pénalité.
      </template>
      <template v-else>Les absences ne pénalisent pas les moyennes de ce semestre.</template>
    </p>

    <ul class="compteurs">
      <li :class="['compteur', { 'compteur--action': absences.injustifiees }]">
        <span class="compteur__valeur">{{ absences.injustifiees }}</span> non justifiée{{ absences.injustifiees > 1 ? 's' : '' }}
      </li>
      <li class="compteur">
        <span class="compteur__valeur">{{ absences.enAttente }}</span> en attente
      </li>
      <li class="compteur">
        <span class="compteur__valeur">{{ absences.justifiees }}</span> justifiée{{ absences.justifiees > 1 ? 's' : '' }}
      </li>
    </ul>

    <p v-if="!absences.total" class="m-0!">Aucune absence ce semestre.</p>
    <ul v-else class="absences">
      <LigneAccueil v-for="absence in absences.liste" :key="absence.debut" :ton="tonAbsence(absence.justification)" icone="pi pi-calendar-times">
        <template #titre>{{ absence.matiere || 'Cours' }}</template>
        <template #detail>{{ creneauAbsence(absence) }}</template>
        <template #valeur><span class="statut">{{ justificationAbsence(absence.justification).texte }}</span></template>
      </LigneAccueil>
    </ul>
  </BlocAccueil>
</template>

<style scoped>
.regle {
  margin: 0;
  font-size: 0.9375rem;
  color: var(--p-text-muted-color);
}

.compteurs {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.compteur {
  display: flex;
  flex-direction: column;
  padding: 0.625rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
  background: var(--p-seance-autre-background);
  color: var(--p-text-color);
}

.compteur--action {
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.compteur__valeur {
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.2;
}

.absences {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  margin: 0;
  padding: 0;
}

.statut {
  font-size: 0.875rem;
  white-space: nowrap;
}
</style>
