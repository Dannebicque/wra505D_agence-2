<script setup>
import {computed} from 'vue';
import {formatDate, resultatEvaluation} from '@/utils/scolarite.js';
import {dernieresNotes} from '@/utils/dernieresNotes.js';
import {tonNote} from '@/utils/accueil.js';

// Le relevé de /api/me/scolarite, lu une fois par l'accueil pour ce bloc et pour « À faire ».
const props = defineProps({
  releve: {type: Object, default: null},
  erreur: {type: Boolean, default: false},
});

const notes = computed(() => dernieresNotes(props.releve));

// Sous la moyenne, entre 10 et 14, à partir de 14 : les pastilles de l'évaluation, du CM et du TP.
const PASTILLES = {faible: 'evaluation', moyenne: 'cm', bonne: 'tp'};
const pastille = (evaluation) => PASTILLES[tonNote(evaluation.note)];
</script>

<template>
  <div class="flex flex-col gap-3">
    <div v-if="releve === null && !erreur" aria-hidden="true">
      <Skeleton class="mb-2"/>
      <Skeleton width="70%" class="mb-2"/>
      <Skeleton width="85%"/>
    </div>

    <p v-else-if="erreur" class="m-0!">Vos notes n'ont pas pu être chargées.</p>

    <p v-else-if="notes.length === 0" class="m-0!">Aucune note publiée pour l'instant.</p>

    <ul v-else class="list-none m-0 p-0 flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
      <li
          v-for="evaluation in notes"
          :key="`${evaluation.matiere.code}-${evaluation.libelle}-${evaluation.date}`"
          class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2"
      >
        <span class="flex flex-col">
          <span class="font-semibold">{{ evaluation.libelle }}</span>
          {{ ' ' }}
          <span class="text-sm">
            {{ evaluation.matiere.code }} {{ evaluation.matiere.libelle }},
            <time :datetime="evaluation.date">{{ formatDate(evaluation.date) }}</time>
          </span>
        </span>
        {{ ' ' }}
        <Tag
            v-if="resultatEvaluation(evaluation).severite"
            :severity="resultatEvaluation(evaluation).severite"
            :value="resultatEvaluation(evaluation).texte"
        />
        <strong
            v-else
            class="note"
            :style="{ background: `var(--p-seance-${pastille(evaluation)}-pill-background)`, color: `var(--p-seance-${pastille(evaluation)}-pill-color)` }"
        >{{ resultatEvaluation(evaluation).texte }}</strong>
      </li>
    </ul>

  </div>
</template>

<style scoped>
.note {
  padding: 0 0.625rem;
  border-radius: 999px;
  font-size: 0.8125rem;
  line-height: 1.5rem;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>
