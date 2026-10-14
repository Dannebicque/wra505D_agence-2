<script setup>
import {computed} from 'vue';
import {formatDate, resultatEvaluation} from '@/utils/scolarite.js';
import {dernieresNotes} from '@/utils/dernieresNotes.js';
import {tonNote} from '@/utils/accueil.js';
import LigneAccueil from './LigneAccueil.vue';

// Le relevé de /api/me/scolarite, lu une fois par l'accueil pour ce bloc et pour « À faire ».
const props = defineProps({
  releve: {type: Object, default: null},
  erreur: {type: Boolean, default: false},
});

const notes = computed(() => dernieresNotes(props.releve));

// La couleur reste discrète : la valeur seule se teinte, sous 10 ou à partir de 14 (décision du
// 14/10). Un statut (absence, dispense) se lit en atténué, sauf l'absence qui compte 0.
const tonValeur = (evaluation) => {
  const resultat = resultatEvaluation(evaluation);
  if (resultat.severite === 'danger') {
    return 'faible';
  }
  return resultat.severite ? 'statut' : tonNote(evaluation.note);
};
</script>

<template>
  <div v-if="releve === null && !erreur" class="flex flex-col gap-2" aria-hidden="true">
    <Skeleton height="2.75rem" border-radius="12px"/>
    <Skeleton height="2.75rem" border-radius="12px"/>
  </div>

  <p v-else-if="erreur" class="m-0!">Vos notes n'ont pas pu être chargées.</p>

  <p v-else-if="notes.length === 0" class="m-0!">Aucune note publiée pour l'instant.</p>

  <ul v-else class="notes">
    <LigneAccueil
        v-for="evaluation in notes"
        :key="`${evaluation.matiere.code}-${evaluation.libelle}-${evaluation.date}`"
    >
      <template #titre>{{ evaluation.libelle }}</template>
      <template #detail>
        {{ evaluation.matiere.code }} {{ evaluation.matiere.libelle }},
        <time :datetime="evaluation.date">{{ formatDate(evaluation.date) }}</time>
      </template>
      <template #valeur>
        <span :class="['note', `note--${tonValeur(evaluation)}`]">{{ resultatEvaluation(evaluation).texte }}</span>
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

.note--faible {
  color: var(--p-seance-evaluation-color);
}

.note--bonne {
  color: var(--p-seance-tp-color);
}

.note--statut {
  font-weight: 400;
  font-size: 0.9375rem;
  color: var(--p-text-muted-color);
}
</style>
