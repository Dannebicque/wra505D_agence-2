<script setup>
import {onMounted, ref} from 'vue';
import {getWidgetDataByCodeService} from '@requests';
import LigneAccueil from './LigneAccueil.vue';

const AFFICHES = 4;
// La forme de l'icône dit le type de fichier ; la couleur reste aux cours (TB-11).
const ICONES = {
  pdf: 'pi pi-file-pdf',
  excel: 'pi pi-file-excel',
  word: 'pi pi-file-word',
  powerpoint: 'pi pi-chart-bar',
  image: 'pi pi-image',
  archive: 'pi pi-box',
};

const documents = ref(null);
const hasError = ref(false);

onMounted(async () => {
  try {
    documents.value = ((await getWidgetDataByCodeService('document.recents')).items ?? []).slice(0, AFFICHES);
  } catch {
    hasError.value = true;
  }
});

// Le widget date au format JJ/MM/AAAA HH:MM.
const jourCourt = (valeur) => {
  const [jour, mois, annee] = (valeur ?? '').slice(0, 10).split('/').map(Number);
  return annee ? new Date(annee, mois - 1, jour).toLocaleDateString('fr-FR', {day: 'numeric', month: 'short'}) : '';
};
</script>

<template>
  <p v-if="hasError" class="m-0!">Les documents n'ont pas pu être chargés.</p>

  <div v-else-if="documents === null" class="flex flex-col gap-2" aria-hidden="true">
    <Skeleton height="2.75rem" border-radius="12px"/>
    <Skeleton height="2.75rem" border-radius="12px"/>
  </div>

  <p v-else-if="documents.length === 0" class="m-0!">Aucun document publié récemment.</p>

  <ul v-else class="documents">
    <LigneAccueil
        v-for="document in documents"
        :key="document.id"
        :to="{ path: '/documents', query: { document: document.id } }"
        :icone="ICONES[document.type] ?? 'pi pi-file'"
    >
      <template #titre>{{ document.title }}</template>
      <template #detail>{{ document.category }} · {{ jourCourt(document.updatedAt) }}</template>
    </LigneAccueil>
  </ul>
</template>

<style scoped>
.documents {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  margin: 0;
  padding: 0;
}
</style>
