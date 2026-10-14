<script setup>
import {computed} from 'vue';
import {useUsersStore} from '@stores';
import {formatDelai, minutesEntre} from '@/utils/prochainCours.js';
import {libelleJour} from '@/utils/accueil.js';

// La journée lue par l'accueil (useJournee) : la carte n'en garde que l'essentiel, elle est d'abord
// là pour l'identité de l'IUT.
const props = defineProps({
  journee: {type: Object, required: true},
});

const userStore = useUsersStore();

const heure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
const lieu = (seance) => seance.salle || 'lieu non précisé';

const semaine = computed(() => {
  const numero = props.journee.semaineFormation.value;
  return numero ? `${numero}${numero === 1 ? 're' : 'e'} semaine de formation` : null;
});

const finDeJournee = computed(() => {
  const cours = props.journee.coursDuJour.value;
  return cours.length ? new Date(Math.max(...cours.map(seance => seance.end))) : null;
});
</script>

<template>
  <section class="bienvenue" aria-labelledby="accueil-bonjour">
    <span class="bienvenue__cercle bienvenue__cercle--accent" aria-hidden="true"></span>
    <span class="bienvenue__cercle bienvenue__cercle--clair" aria-hidden="true"></span>
    <span class="bienvenue__cercle bienvenue__cercle--sombre" aria-hidden="true"></span>
    <span class="bienvenue__cercle bienvenue__cercle--point" aria-hidden="true"></span>

    <h2 id="accueil-bonjour" class="bienvenue__bonjour">
      <span class="bienvenue__salut">Bonjour,</span>
      {{ userStore.user?.prenom }}
    </h2>
    <p class="bienvenue__date">
      {{ journee.maintenant.value.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) }}
      <template v-if="semaine"><br>{{ semaine }}</template>
    </p>

    <p v-if="journee.hasError.value" class="bienvenue__infos">L'emploi du temps est indisponible pour le moment.</p>
    <Skeleton v-else-if="journee.cours.value === null" width="70%" height="2.5rem" class="bienvenue__infos opacity-40" aria-hidden="true"/>
    <dl v-else class="bienvenue__infos">
      <div v-if="journee.situation.value.enCours">
        <dt>En ce moment</dt>
        <dd>{{ lieu(journee.situation.value.enCours) }}, jusqu'à {{ heure(journee.situation.value.enCours.fin) }}</dd>
      </div>
      <div v-else-if="journee.situation.value.prochain">
        <dt>Prochain cours</dt>
        <dd>
          {{ heure(journee.situation.value.prochain.debut) }}, {{ lieu(journee.situation.value.prochain) }}
          · dans {{ formatDelai(minutesEntre(journee.maintenant.value, journee.situation.value.prochain.debut)) }}
        </dd>
      </div>
      <div v-else>
        <dt>{{ journee.situation.value.aDesCours ? 'Journée terminée' : 'Pas de cours aujourd\'hui' }}</dt>
        <dd v-if="journee.suivant.value">
          <span class="capitalize">{{ libelleJour(journee.suivant.value.debut, journee.maintenant.value) }}</span>
          {{ heure(journee.suivant.value.debut) }}, {{ journee.suivant.value.codeModule }} en {{ lieu(journee.suivant.value) }}
        </dd>
        <dd v-else>Aucun cours d'ici la semaine prochaine.</dd>
      </div>
      <div v-if="finDeJournee && journee.maintenant.value < finDeJournee">
        <dt>Fin de journée</dt>
        <dd>{{ heure(finDeJournee) }}</dd>
      </div>
    </dl>
  </section>
</template>

<style scoped>
/* Le violet de l'IUT en plein, quel que soit le thème : le texte blanc y garde 9,95:1. Les
   cercles reprennent ceux du logo ; aucun ne passe sous un texte. */
.bienvenue {
  position: relative;
  display: flex;
  flex-direction: column;
  min-height: 12rem;
  padding: 1.25rem 1.375rem;
  border-radius: 18px;
  background: var(--p-primary-500);
  color: #FFFFFF;
  overflow: hidden;
}

.bienvenue__cercle {
  position: absolute;
  border-radius: 999px;
  pointer-events: none;
}

.bienvenue__cercle--accent {
  right: -2.5rem;
  top: -2.5rem;
  width: 8rem;
  height: 8rem;
  background: var(--accent-color);
}

.bienvenue__cercle--clair {
  right: 6rem;
  top: 3.25rem;
  width: 1.75rem;
  height: 1.75rem;
  background: var(--p-primary-300);
}

.bienvenue__cercle--sombre {
  right: -2rem;
  bottom: -3rem;
  width: 8rem;
  height: 8rem;
  background: var(--p-primary-600);
}

.bienvenue__cercle--point {
  right: 1.5rem;
  top: 6.5rem;
  width: 0.875rem;
  height: 0.875rem;
  background: var(--p-primary-200);
}

.bienvenue__bonjour {
  position: relative;
  display: flex;
  flex-direction: column;
  margin: 0 !important;
  max-width: calc(100% - 6.5rem);
  font-size: 2rem !important;
  font-weight: 700;
  line-height: 1.15;
  color: inherit;
}

.bienvenue__salut {
  font-size: 1.25rem;
  font-weight: 300;
}

.bienvenue__date {
  position: relative;
  margin: 0.5rem 0 0;
  max-width: calc(100% - 3rem);
  font-size: 1rem;
  line-height: 1.45;
  color: var(--p-primary-200);
}

.bienvenue__date::first-letter {
  text-transform: uppercase;
}

.bienvenue__infos {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
  margin: auto 0 0;
  padding-top: 1.5rem;
  max-width: calc(100% - 5rem);
}

.bienvenue__infos dt {
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--p-primary-200);
}

.bienvenue__infos dd {
  margin: 0.125rem 0 0;
  font-size: 1.125rem;
  font-weight: 600;
  line-height: 1.35;
}
</style>
