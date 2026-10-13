<script setup>
import {computed} from 'vue';
import {absencesARegler, libelleDelai} from '@/utils/accueil.js';

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
    <Skeleton height="3.25rem" border-radius="14px"/>
    <Skeleton height="3.25rem" border-radius="14px"/>
  </div>

  <p v-else-if="rienAFaire" class="a-faire__rien">
    <i class="pi pi-check-circle" aria-hidden="true"></i>
    Rien à régler, et aucune évaluation annoncée.
  </p>

  <ul v-else class="a-faire">
    <li v-if="absences.aJustifier.length" class="a-faire__tuile a-faire__tuile--evaluation">
      <RouterLink to="/intranet/scolarite" class="a-faire__lien">
        <span class="a-faire__titre">{{ pluriel(absences.aJustifier.length, 'absence') }} à justifier</span>
        <span class="a-faire__detail">
          {{ absences.aJustifier[0].matiere }}, {{ jourCourt(absences.aJustifier[0].debut) }}
        </span>
      </RouterLink>
    </li>
    <li v-if="absences.enAttente.length" class="a-faire__tuile a-faire__tuile--td">
      <RouterLink to="/intranet/scolarite" class="a-faire__lien">
        <span class="a-faire__titre">{{ pluriel(absences.enAttente.length, 'justificatif') }} en attente</span>
        <span class="a-faire__detail">{{ absences.enAttente[0].matiere }}</span>
      </RouterLink>
    </li>
    <li v-for="echeance in prochaines" :key="`${echeance.date}-${echeance.code}-${echeance.libelle}`" class="a-faire__tuile a-faire__tuile--cm">
      <RouterLink to="/intranet/cahier-de-texte" class="a-faire__lien">
        <span class="a-faire__titre">
          <span class="a-faire__pastille">{{ echeance.type || echeance.libelle }}</span>
          {{ echeance.code }} {{ echeance.matiere }}
        </span>
        <span class="a-faire__detail">
          <time :datetime="echeance.date">{{ jourCourt(echeance.date) }}</time> · {{ libelleDelai(echeance.date, aujourdhui) }}
        </span>
      </RouterLink>
    </li>
  </ul>
</template>

<style scoped>
.a-faire {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.a-faire__tuile {
  border-radius: 14px;
  background: var(--tuile-fond);
  color: var(--tuile-couleur);
}

.a-faire__tuile--evaluation {
  --tuile-fond: var(--p-seance-evaluation-background);
  --tuile-couleur: var(--p-seance-evaluation-color);
}

.a-faire__tuile--td {
  --tuile-fond: var(--p-seance-td-background);
  --tuile-couleur: var(--p-seance-td-color);
}

.a-faire__tuile--cm {
  --tuile-fond: var(--p-seance-cm-background);
  --tuile-couleur: var(--p-seance-cm-color);
}

.a-faire__lien {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-height: 44px;
  padding: 0.5rem 0.75rem;
  border-radius: inherit;
  color: inherit;
}

.a-faire__lien:hover .a-faire__titre {
  text-decoration: underline;
}

.a-faire__titre {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.875rem;
  font-weight: 700;
}

.a-faire__detail {
  font-size: 0.8125rem;
  color: var(--p-text-color);
}

.a-faire__pastille {
  padding: 0 0.5rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  line-height: 1.25rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  background: var(--p-seance-cm-pill-background);
  color: var(--p-seance-cm-pill-color);
}

.a-faire__rien {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  color: var(--p-seance-tp-color);
  font-weight: 600;
}
</style>
