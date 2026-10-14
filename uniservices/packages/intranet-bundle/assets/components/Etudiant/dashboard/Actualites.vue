<script setup>
import {computed, onMounted, ref} from 'vue';
import {getWidgetDataByCodeService} from '@requests';

const AFFICHEES = 4;

const departement = ref(null);
const iut = ref([]);
const hasError = ref(false);

// Le fil de l'IUT est un flux extérieur, parfois lent : le département s'affiche sans l'attendre.
onMounted(() => {
  getWidgetDataByCodeService('intranet.actualites')
      .then(reponse => {
        departement.value = (reponse.items ?? []).map(actu => ({
          source: 'departement',
          titre: actu.libelle,
          date: actu.dateDebut,
          lien: actu.link,
        }));
      })
      .catch(() => {
        departement.value = [];
        hasError.value = true;
      });
  getWidgetDataByCodeService('intranet.actualites_iut')
      .then(reponse => {
        iut.value = (reponse.items ?? []).map(actu => ({
          source: 'iut',
          titre: actu.title,
          date: actu.pubDate,
          lien: actu.link,
        }));
      })
      .catch(() => {
        iut.value = [];
      });
});

const actualites = computed(() => [...(departement.value ?? []), ...iut.value]
    .sort((a, b) => new Date(b.date ?? 0) - new Date(a.date ?? 0))
    .slice(0, AFFICHEES));

const jourCourt = (valeur) => (valeur ? new Date(valeur).toLocaleDateString('fr-FR', {day: 'numeric', month: 'short'}) : '');
</script>

<template>
  <p v-if="hasError && actualites.length === 0" class="m-0!">Les actualités n'ont pas pu être chargées.</p>

  <div v-else-if="departement === null" class="flex flex-col gap-2" aria-hidden="true">
    <Skeleton height="2.5rem" border-radius="12px"/>
    <Skeleton height="2.5rem" border-radius="12px"/>
  </div>

  <p v-else-if="actualites.length === 0" class="m-0!">Aucune actualité pour le moment.</p>

  <ul v-else class="actualites">
    <li v-for="actu in actualites" :key="`${actu.source}-${actu.titre}`" class="actualites__item">
      <span :class="['actualites__source', `actualites__source--${actu.source}`]">
        {{ actu.source === 'iut' ? 'IUT' : 'Département' }}
      </span>
      <a v-if="actu.lien" :href="actu.lien" target="_blank" rel="noopener" class="actualites__titre actualites__titre--lien">
        {{ actu.titre }}<span class="sr-only">, nouvel onglet</span>
      </a>
      <span v-else class="actualites__titre">{{ actu.titre }}</span>
      <time v-if="actu.date" :datetime="actu.date" class="actualites__date">{{ jourCourt(actu.date) }}</time>
    </li>
  </ul>
</template>

<style scoped>
.actualites {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.actualites__item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.25rem;
  padding: 0.625rem 0;
}

.actualites__item + .actualites__item {
  border-top: 1px solid var(--p-content-border-color);
}

.actualites__source {
  padding: 0 0.5rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.25rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.actualites__source--departement {
  background: var(--p-seance-cm-pill-background);
  color: var(--p-seance-cm-pill-color);
}

.actualites__source--iut {
  background: var(--p-seance-autre-pill-background);
  color: var(--p-seance-autre-pill-color);
}

.actualites__titre {
  font-size: 1rem;
  font-weight: 600;
}

.actualites__titre--lien {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  margin-block: -0.75rem;
  color: inherit;
}

.actualites__titre--lien:hover {
  text-decoration: underline;
}

.actualites__date {
  font-size: 0.9375rem;
  color: var(--p-text-muted-color);
}
</style>
