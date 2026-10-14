<script setup>
import {computed, onMounted, onUnmounted, ref} from 'vue';
import CoursCarte from '@/components/Edt/CoursCarte.vue';
import {libelleCours} from '@/utils/agenda.js';
import {libelleJour} from '@/utils/accueil.js';

// La journée de l'emploi du temps, telle quelle : la hauteur d'une carte dit la durée du cours, les
// pauses se voient dans les vides. Seules les heures qui portent un cours sont dessinées.
const props = defineProps({
  journee: {type: Object, required: true},
});

// 64 px par heure en bureau, 56 sur un téléphone : la journée garde de l'air, les cours d'une
// heure restent lisibles.
const telephone = window.matchMedia('(max-width: 1023px)');
const hauteurHeure = ref(telephone.matches ? 56 : 64);
const suivreEcran = (evenement) => {
  hauteurHeure.value = evenement.matches ? 56 : 64;
};
onMounted(() => telephone.addEventListener('change', suivreEcran));
onUnmounted(() => telephone.removeEventListener('change', suivreEcran));

const cours = computed(() => props.journee.coursDuJour.value);

const plage = computed(() => {
  if (!cours.value.length) {
    return null;
  }
  const debut = Math.min(...cours.value.map(seance => seance.start.getHours()));
  const fin = Math.max(...cours.value.map(seance => seance.end.getHours() + (seance.end.getMinutes() ? 1 : 0)));
  return {debut, fin};
});

const heures = computed(() => (plage.value
    ? Array.from({length: plage.value.fin - plage.value.debut + 1}, (_, i) => plage.value.debut + i)
    : []));

const position = (date) => ((date.getHours() - plage.value.debut) * 60 + date.getMinutes()) / 60 * hauteurHeure.value;

// Le trait d'une heure occupe le premier pixel de sa case : comme sur l'emploi du temps, la carte
// laisse le même vide au-dessus et au-dessous.
const placement = (seance) => ({
  top: `${position(seance.start) + 3}px`,
  height: `${position(seance.end) - position(seance.start) - 6}px`,
});

const hauteurCarte = (seance) => position(seance.end) - position(seance.start) - 6;

const ligneMaintenant = computed(() => {
  const maintenant = props.journee.maintenant.value;
  if (!plage.value || maintenant.getHours() < plage.value.debut || maintenant.getHours() >= plage.value.fin) {
    return null;
  }
  return `${position(maintenant)}px`;
});

const heure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
</script>

<template>
  <p v-if="journee.hasError.value" class="m-0!">L'emploi du temps est indisponible pour le moment.</p>

  <Skeleton v-else-if="journee.cours.value === null" height="16rem" border-radius="14px" aria-hidden="true"/>

  <p v-else-if="!cours.length" class="journee-vide">
    Pas de cours aujourd'hui.
    <template v-if="journee.suivant.value">
      Prochain cours <span>{{ libelleJour(journee.suivant.value.debut, journee.maintenant.value) }}</span>
      à {{ heure(journee.suivant.value.debut) }} : {{ journee.suivant.value.codeModule }} {{ journee.suivant.value.libModule }}.
    </template>
  </p>

  <div v-else class="jour" :style="{ height: `${(plage.fin - plage.debut) * hauteurHeure}px` }">
    <div class="jour__heures" aria-hidden="true">
      <span v-for="h in heures" :key="h" class="jour__heure" :style="{ top: `${(h - plage.debut) * hauteurHeure}px` }">
        {{ String(h).padStart(2, '0') }}:00
      </span>
    </div>
    <div class="jour__grille">
      <span v-for="h in heures" :key="h" class="jour__trait" :style="{ top: `${(h - plage.debut) * hauteurHeure}px` }" aria-hidden="true"></span>
      <ol class="jour__cours" aria-label="Cours d'aujourd'hui">
        <li v-for="seance in cours" :key="seance.id ?? `${seance.debut}-${seance.codeModule}`" class="jour__creneau" :style="placement(seance)">
          <span class="sr-only">{{ libelleCours(seance) }}</span>
          <CoursCarte :cours="seance" :hauteur="hauteurCarte(seance)" :maintenant="journee.maintenant.value" aria-hidden="true"/>
        </li>
      </ol>
      <span v-if="ligneMaintenant" class="jour__maintenant" :style="{ top: ligneMaintenant }" aria-hidden="true"></span>
    </div>
  </div>
</template>

<style scoped>
.journee-vide {
  margin: 0;
  color: var(--p-text-muted-color);
}

.jour {
  position: relative;
  display: grid;
  grid-template-columns: 3rem minmax(0, 1fr);
  margin-top: 0.5rem;
}

.jour__heures {
  position: relative;
}

.jour__heure {
  position: absolute;
  left: 0;
  transform: translateY(-50%);
  font-size: 0.9375rem;
  font-variant-numeric: tabular-nums;
  color: var(--p-text-muted-color);
}

.jour__grille {
  position: relative;
}

.jour__trait {
  position: absolute;
  left: 0;
  right: 0;
  border-top: 1px solid var(--p-content-border-color);
}

.jour__cours {
  margin: 0;
  padding: 0;
  list-style: none;
}

.jour__creneau {
  position: absolute;
  z-index: 1;
  left: 0.25rem;
  right: 0;
}

/* Par-dessus les cartes, d'un bord à l'autre, comme sur l'emploi du temps. */
.jour__maintenant {
  position: absolute;
  z-index: 2;
  left: -0.375rem;
  right: 0;
  height: 2px;
  background: var(--p-primary-color);
  pointer-events: none;
}

.jour__maintenant::before {
  content: '';
  position: absolute;
  left: 0;
  top: -3px;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: var(--p-primary-color);
}
</style>
