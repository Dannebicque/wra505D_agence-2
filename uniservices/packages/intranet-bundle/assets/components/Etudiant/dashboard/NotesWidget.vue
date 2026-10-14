<script setup>
import {computed} from 'vue';
import {formatDate, resultatEvaluation, tonEvaluation} from '@/utils/scolarite.js';
import {dernieresNotes} from '@/utils/dernieresNotes.js';
import {LigneAccueil} from '@components';

// Le relevé de /api/me/scolarite, lu une fois par l'accueil pour ce bloc et pour « À faire ».
const props = defineProps({
  releve: {type: Object, default: null},
  erreur: {type: Boolean, default: false},
});

const notes = computed(() => dernieresNotes(props.releve, 4));
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
        <span :class="['note', `note--${tonEvaluation(evaluation)}`]">{{ resultatEvaluation(evaluation).texte }}</span>
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

.note {
  display: inline-block;
  padding: 0 0.625rem;
  border-radius: 999px;
  line-height: 1.625rem;
  white-space: nowrap;
}

/* Une pastille douce, teinte pâle et texte foncé du même type : la couleur se voit sans crier. */
.note--faible {
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.note--moyenne {
  background: var(--p-seance-cm-background);
  color: var(--p-seance-cm-color);
}

.note--bonne {
  background: var(--p-seance-tp-background);
  color: var(--p-seance-tp-color);
}

.note--statut {
  padding: 0;
  white-space: normal;
  font-weight: 400;
  font-size: 0.9375rem;
  color: var(--p-text-muted-color);
}
</style>
