<script setup>
import {onMounted, ref} from 'vue';
import {getWidgetDataByCodeService} from '@requests';

const AFFICHES = 4;
// L'icône du type de fichier, sur la teinte d'un type de séance : la palette de l'agenda.
const TYPES = {
  pdf: {icone: 'pi pi-file-pdf', teinte: 'evaluation'},
  excel: {icone: 'pi pi-file-excel', teinte: 'tp'},
  word: {icone: 'pi pi-file-word', teinte: 'cm'},
  powerpoint: {icone: 'pi pi-chart-bar', teinte: 'td'},
  image: {icone: 'pi pi-image', teinte: 'sae'},
  archive: {icone: 'pi pi-box', teinte: 'td'},
};
const AUTRE = {icone: 'pi pi-file', teinte: 'autre'};

const documents = ref(null);
const hasError = ref(false);

onMounted(async () => {
  try {
    documents.value = ((await getWidgetDataByCodeService('document.recents')).items ?? []).slice(0, AFFICHES);
  } catch {
    hasError.value = true;
  }
});

const type = (document) => TYPES[document.type] ?? AUTRE;

// Le widget date au format JJ/MM/AAAA HH:MM.
const jourCourt = (valeur) => {
  const [jour, mois, annee] = (valeur ?? '').slice(0, 10).split('/').map(Number);
  return annee ? new Date(annee, mois - 1, jour).toLocaleDateString('fr-FR', {day: 'numeric', month: 'short'}) : '';
};
</script>

<template>
  <p v-if="hasError" class="m-0!">Les documents n'ont pas pu être chargés.</p>

  <div v-else-if="documents === null" class="flex flex-col gap-2" aria-hidden="true">
    <Skeleton height="2.5rem" border-radius="12px"/>
    <Skeleton height="2.5rem" border-radius="12px"/>
  </div>

  <p v-else-if="documents.length === 0" class="m-0!">Aucun document publié récemment.</p>

  <ul v-else class="documents">
    <li v-for="document in documents" :key="document.id">
      <RouterLink :to="{ path: '/documents', query: { document: document.id } }" class="documents__lien">
        <span
            class="documents__icone"
            :style="{ background: `var(--p-seance-${type(document).teinte}-background)`, color: `var(--p-seance-${type(document).teinte}-color)` }"
            aria-hidden="true"
        >
          <i :class="type(document).icone"></i>
        </span>
        <span class="documents__texte">
          <span class="documents__titre">{{ document.title }}</span>
          <span class="documents__detail">{{ document.category }} · {{ jourCourt(document.updatedAt) }}</span>
        </span>
      </RouterLink>
    </li>
  </ul>
</template>

<style scoped>
.documents {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.documents__lien {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 44px;
  padding: 0.375rem 0.5rem;
  margin-inline: -0.5rem;
  border-radius: 12px;
  color: inherit;
}

.documents__lien:hover {
  background: var(--p-content-hover-background);
}

.documents__lien:hover .documents__titre {
  text-decoration: underline;
}

.documents__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border-radius: 999px;
}

.documents__texte {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.documents__titre {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 1rem;
  font-weight: 600;
}

.documents__detail {
  font-size: 0.9375rem;
  color: var(--p-text-muted-color);
}
</style>
