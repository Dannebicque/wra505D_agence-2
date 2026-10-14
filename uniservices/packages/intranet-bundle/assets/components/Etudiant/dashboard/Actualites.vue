<script setup>
import {computed, onMounted, ref} from 'vue';
import {getWidgetDataByCodeService} from '@requests';
import LigneAccueil from './LigneAccueil.vue';

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
    <Skeleton height="2.75rem" border-radius="12px"/>
    <Skeleton height="2.75rem" border-radius="12px"/>
  </div>

  <p v-else-if="actualites.length === 0" class="m-0!">Aucune actualité pour le moment.</p>

  <!-- La source n'est dite que pour l'IUT : le fil est d'abord celui du département (TB-18). -->
  <ul v-else class="actualites">
    <LigneAccueil v-for="actu in actualites" :key="`${actu.source}-${actu.titre}`" :href="actu.lien">
      <template #titre>{{ actu.titre }}</template>
      <template #detail>
        <time v-if="actu.date" :datetime="actu.date">{{ jourCourt(actu.date) }}</time>
        <template v-if="actu.source === 'iut'"> · IUT</template>
      </template>
    </LigneAccueil>
  </ul>
</template>

<style scoped>
.actualites {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  margin: 0;
  padding: 0;
}
</style>
