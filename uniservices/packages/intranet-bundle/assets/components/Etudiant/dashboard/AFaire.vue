<script setup>
import {computed} from 'vue';
import {absencesARegler, libelleDelai} from '@/utils/accueil.js';
import LigneAccueil from './LigneAccueil.vue';

const props = defineProps({
  // Relevé de /api/me/scolarite et échéances de /api/me/cahier-de-texte ; null tant qu'ils ne
  // sont pas arrivés.
  releve: {type: Object, default: null},
  echeances: {type: Array, default: null},
  erreur: {type: Boolean, default: false},
});

const ECHEANCES_AFFICHEES = 3;

const aujourdhui = new Date();
const absences = computed(() => absencesARegler(props.releve));
const prochaines = computed(() => (props.echeances ?? []).slice(0, ECHEANCES_AFFICHEES));
const charge = computed(() => props.releve !== null && props.echeances !== null);
const rienAFaire = computed(() => charge.value
    && absences.value.aJustifier.length === 0
    && absences.value.enAttente.length === 0
    && prochaines.value.length === 0);

const jourCourt = (iso) => new Date(`${iso.slice(0, 10)}T00:00:00`).toLocaleDateString('fr-FR', {weekday: 'short', day: 'numeric', month: 'short'});
const pluriel = (nombre, mot) => `${nombre} ${mot}${nombre > 1 ? 's' : ''}`;
</script>

<template>
  <p v-if="erreur" class="m-0!">Ce qui vous attend n'a pas pu être chargé.</p>

  <div v-else-if="!charge" class="flex flex-col gap-2" aria-hidden="true">
    <Skeleton height="2.75rem" border-radius="12px"/>
    <Skeleton height="2.75rem" border-radius="12px"/>
  </div>

  <p v-else-if="rienAFaire" class="a-faire__rien">
    <i class="pi pi-check-circle" aria-hidden="true"></i>
    Rien à régler, et aucune évaluation annoncée.
  </p>

  <ul v-else class="a-faire">
    <LigneAccueil v-if="absences.aJustifier.length" to="/intranet/scolarite" icone="pi pi-exclamation-circle" ton="action">
      <template #titre>{{ pluriel(absences.aJustifier.length, 'absence') }} à justifier</template>
      <template #detail>{{ absences.aJustifier[0].matiere }}, {{ jourCourt(absences.aJustifier[0].debut) }}</template>
    </LigneAccueil>
    <LigneAccueil v-if="absences.enAttente.length" to="/intranet/scolarite" icone="pi pi-clock" teinte="td">
      <template #titre>{{ pluriel(absences.enAttente.length, 'justificatif') }} en attente</template>
      <template #detail>{{ absences.enAttente[0].matiere }}</template>
    </LigneAccueil>
    <LigneAccueil
        v-for="echeance in prochaines"
        :key="`${echeance.date}-${echeance.code}-${echeance.libelle}`"
        to="/intranet/cahier-de-texte"
        icone="pi pi-calendar"
        teinte="cm"
    >
      <template #titre>{{ echeance.code }} {{ echeance.matiere }}</template>
      <template #detail>{{ echeance.type || echeance.libelle }} · <time :datetime="echeance.date">{{ jourCourt(echeance.date) }}</time></template>
      <template #valeur>{{ libelleDelai(echeance.date, aujourdhui) }}</template>
    </LigneAccueil>
  </ul>
</template>

<style scoped>
.a-faire {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  margin: 0;
  padding: 0;
}

.a-faire__rien {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  color: var(--p-text-muted-color);
}
</style>
