<script setup>
import {computed, onMounted, ref} from 'vue';
import {getMaScolariteService} from '@requests';
import {formatDate, resultatEvaluation} from '@/utils/scolarite.js';
import {dernieresNotes} from '@/utils/dernieresNotes.js';

const releve = ref(null);
const isLoading = ref(true);
const hasError = ref(false);

const notes = computed(() => dernieresNotes(releve.value));

onMounted(async () => {
  try {
    releve.value = await getMaScolariteService();
  } catch {
    hasError.value = true;
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <div class="flex flex-col gap-3">
    <div v-if="isLoading">
      <Skeleton class="mb-2"/>
      <Skeleton width="70%" class="mb-2"/>
      <Skeleton width="85%"/>
    </div>

    <p v-else-if="hasError" class="m-0!">Vos notes n'ont pas pu être chargées.</p>

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
        <strong v-else>{{ resultatEvaluation(evaluation).texte }}</strong>
      </li>
    </ul>

    <Button as="router-link" to="/intranet/scolarite" label="Voir toutes mes notes" class="w-full touch-target" severity="secondary" size="small"/>
  </div>
</template>
