<script setup>
import {LigneAccueil} from '@components';
import PastilleNote from './PastilleNote.vue';
import {formatDate, formatNote, libellePenalite, resultatEvaluation, tonEvaluation} from '@/utils/scolarite.js';
import {tonNote} from '@/utils/accueil.js';

defineProps({
  semestre: {type: Object, required: true},
});
</script>

<template>
  <div class="ues">
    <section v-for="ue in semestre.ues" :key="ue.libelle" class="ue" :aria-labelledby="`ue-${semestre.id}-${ue.numero}`">
      <div class="ue__entete">
        <h3 :id="`ue-${semestre.id}-${ue.numero}`" class="ue__titre">{{ ue.libelle }}</h3>
        <p class="ue__moyenne">
          <PastilleNote :ton="tonNote(ue.moyenne)">{{ formatNote(ue.moyenne) }}<span v-if="ue.moyenne !== null"> / 20</span></PastilleNote>
          <span v-if="ue.moyenneSansPenalite !== null && ue.moyenneSansPenalite !== ue.moyenne" class="ue__sans">
            {{ formatNote(ue.moyenneSansPenalite) }} sans les absences
          </span>
        </p>
      </div>

      <p v-if="!ue.enseignements.length" class="ue__vide">Aucune ressource ni SAÉ dans cette UE.</p>

      <ul v-else class="ue__matieres">
        <template v-for="enseignement in ue.enseignements" :key="enseignement.code">
          <LigneAccueil>
            <template #titre>{{ enseignement.code }} {{ enseignement.libelle }}</template>
            <template #detail>
              coef. {{ formatNote(enseignement.coefficient) }}
              <span v-if="libellePenalite(enseignement)" class="penalite">
                · {{ libellePenalite(enseignement) }}<span v-if="enseignement.absencesEnAttente">, levable si le justificatif est accepté</span>
              </span>
            </template>
            <template #valeur>
              <PastilleNote :ton="tonNote(enseignement.moyennePenalisee)">
                {{ enseignement.moyennePenalisee === null ? 'Pas de note' : `${formatNote(enseignement.moyennePenalisee)} / 20` }}
              </PastilleNote>
            </template>
          </LigneAccueil>
          <li v-if="enseignement.evaluations.length" class="evaluations">
            <ul class="evaluations__liste" :aria-label="`Évaluations de ${enseignement.code}`">
              <li v-for="evaluation in enseignement.evaluations" :key="`${evaluation.libelle}-${evaluation.date}`" class="evaluation">
                <span class="evaluation__nom">{{ evaluation.libelle }}</span>
                <time class="evaluation__date" :datetime="evaluation.date">{{ formatDate(evaluation.date) }}</time>
                <span class="evaluation__coef">coef. {{ formatNote(evaluation.coefficient) }}</span>
                <PastilleNote :ton="tonEvaluation(evaluation)" petite>{{ resultatEvaluation(evaluation).texte }}</PastilleNote>
              </li>
            </ul>
          </li>
        </template>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.ues {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.ue {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.ue__entete {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--p-content-border-color);
}

.ue__titre {
  margin: 0 !important;
  font-size: 1.0625rem !important;
  font-weight: 600;
}

.ue__moyenne {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.ue__sans,
.ue__vide {
  margin: 0;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.ue__matieres {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  margin: 0;
  padding: 0;
}

.penalite {
  color: var(--p-seance-evaluation-color);
}

.evaluations {
  list-style: none;
  padding: 0 0 0.5rem 0.75rem;
}

.evaluations__liste {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin: 0;
  padding: 0 0 0 0.75rem;
  border-left: 2px solid var(--p-content-border-color);
  list-style: none;
}

.evaluation {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.75rem;
  font-size: 0.9375rem;
}

.evaluation__nom {
  flex: 1 1 10rem;
}

.evaluation__date,
.evaluation__coef {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}
</style>
