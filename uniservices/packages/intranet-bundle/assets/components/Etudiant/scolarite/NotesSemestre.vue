<script setup>
import {computed} from 'vue';
import {detailPenalite, formatDate, formatNote, resultatEvaluation, resumeNotes, statutUe, tonEvaluation} from '@/utils/scolarite.js';
import {tonNote} from '@/utils/accueil.js';
import JaugeNote from './JaugeNote.vue';
import PastilleNote from './PastilleNote.vue';

const props = defineProps({
  semestre: {type: Object, required: true},
});

const resume = computed(() => resumeNotes(props.semestre));
</script>

<template>
  <div class="notes">
    <div class="resume">
      <p v-for="phrase in resume" :key="phrase" class="resume__phrase">{{ phrase }}</p>
      <p class="resume__aide">
        Moyennes provisoires, calculées sur les notes publiées jusqu'à la sous-commission.
        Une UE se valide à 10.
      </p>
    </div>

    <p v-if="!semestre.ues.length" class="m-0!">Aucune UE pour ce semestre.</p>

    <Accordion v-else multiple class="ues">
      <AccordionPanel v-for="ue in semestre.ues" :key="ue.libelle" :value="ue.libelle" class="ue">
        <AccordionHeader class="ue__entete">
          <span class="ue__ligne">
            <span class="ue__nom">{{ ue.libelle }}</span>
            <span class="ue__jauge"><JaugeNote :moyenne="ue.moyenne" :sans-penalite="ue.moyenneSansPenalite"/></span>
            <span class="ue__chiffre">
              <strong :class="['ue__moyenne', `ue__moyenne--${statutUe(ue).ton ?? 'vide'}`]">{{ formatNote(ue.moyenne) }}</strong>
              <span class="ue__statut">{{ statutUe(ue).texte }}</span>
            </span>
          </span>
        </AccordionHeader>
        <AccordionContent>
          <p v-if="!ue.enseignements.length" class="m-0!">Aucune ressource ni SAÉ dans cette UE.</p>
          <ul v-else class="matieres">
            <li v-for="enseignement in ue.enseignements" :key="enseignement.code" class="matiere">
              <div class="matiere__ligne">
                <span class="matiere__nom">
                  <span class="matiere__code">{{ enseignement.code }}</span> {{ enseignement.libelle }}
                  <span class="matiere__coef">coef. {{ formatNote(enseignement.coefficient) }}</span>
                </span>
                <PastilleNote :ton="tonNote(enseignement.moyennePenalisee)">
                  {{ enseignement.moyennePenalisee === null ? 'Pas de note' : formatNote(enseignement.moyennePenalisee) }}
                </PastilleNote>
              </div>
              <p v-if="detailPenalite(enseignement)" class="matiere__penalite">
                <i class="pi pi-calendar-times" aria-hidden="true"></i>
                {{ detailPenalite(enseignement) }}<span v-if="enseignement.absencesEnAttente"> · levable si le justificatif est accepté</span>
              </p>
              <ul v-if="enseignement.evaluations.length" class="evaluations" :aria-label="`Évaluations de ${enseignement.code}`">
                <li v-for="evaluation in enseignement.evaluations" :key="`${evaluation.libelle}-${evaluation.date}`" class="evaluation">
                  <span class="evaluation__nom">{{ evaluation.libelle }}</span>
                  <span class="evaluation__meta"><time :datetime="evaluation.date">{{ formatDate(evaluation.date) }}</time> · coef. {{ formatNote(evaluation.coefficient) }}</span>
                  <PastilleNote :ton="tonEvaluation(evaluation)" petite>{{ resultatEvaluation(evaluation).texte }}</PastilleNote>
                </li>
              </ul>
            </li>
          </ul>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>
  </div>
</template>

<style scoped>
.notes {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.resume {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.resume__phrase {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.resume__aide {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.ues {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

/* Chaque UE est une carte qui se déplie : le panneau de PrimeVue garde le clavier et l'animation. */
.ue {
  border: 1px solid var(--p-content-border-color) !important;
  border-radius: 14px;
  overflow: hidden;
}

.ue :deep(.p-accordionheader) {
  min-height: 44px;
  padding: 0.875rem 1rem;
  background: transparent;
}

.ue :deep(.p-accordioncontent-content) {
  padding: 0 1rem 1rem;
}

.ue__ligne {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(6rem, 14rem) auto;
  align-items: center;
  gap: 1rem;
  width: 100%;
  margin-right: 0.75rem;
  text-align: left;
}

.ue__nom {
  font-weight: 600;
  color: var(--p-text-color);
}

.ue__chiffre {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 6.5rem;
}

.ue__moyenne {
  font-size: 1.5rem;
  line-height: 1.1;
  color: var(--p-text-color);
}

.ue__moyenne--bonne {
  color: var(--p-seance-tp-color);
}

.ue__moyenne--faible {
  color: var(--p-seance-evaluation-color);
}

.ue__statut {
  font-size: 0.8125rem;
  font-weight: 400;
  color: var(--p-text-muted-color);
}

.matieres {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.matiere {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.75rem 0;
  border-top: 1px solid var(--p-content-border-color);
}

.matiere__ligne {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.matiere__code {
  font-weight: 600;
}

.matiere__coef {
  margin-left: 0.5rem;
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
}

.matiere__penalite {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin: 0;
  font-size: 0.875rem;
  color: var(--p-seance-evaluation-color);
}

.evaluations {
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
  gap: 0.125rem 0.75rem;
  font-size: 0.9375rem;
}

.evaluation__nom {
  flex: 1 1 10rem;
}

.evaluation__meta {
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
}

/* Sur téléphone, la jauge passe sous le nom de l'UE. */
@media (max-width: 639px) {
  .ue__ligne {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .ue__jauge {
    grid-column: 1 / -1;
    grid-row: 2;
  }
}
</style>
