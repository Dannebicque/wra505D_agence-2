<script setup>
import {computed, onMounted, ref} from 'vue';
import {useUsersStore} from '@stores';
import {getEtudiantService} from '@requests';
import {formatDelai, minutesEntre} from '@/utils/prochainCours.js';
import {evenementDuJour, libelleJour} from '@/utils/accueil.js';

// La journée lue par l'accueil (useJournee) : la carte n'en garde que l'essentiel, elle est d'abord
// là pour l'identité de l'IUT.
const props = defineProps({
  journee: {type: Object, required: true},
});

const userStore = useUsersStore();

// La date de naissance n'est que sur la fiche de l'étudiant : elle ne sert qu'à l'anniversaire.
const naissance = ref(null);
onMounted(async () => {
  try {
    naissance.value = (await getEtudiantService(userStore.user.id)).date_naissance ?? null;
  } catch {
    naissance.value = null;
  }
});

// Un message tiré au chargement parmi ceux du jour (vacances, évaluation, bon courage…), pour que
// la carte surprenne à chaque visite ; le tirage reste le même pendant la visite.
const tirage = Math.random();
const evenement = computed(() => (props.journee.cours.value === null ? null : evenementDuJour({
  aujourdhui: props.journee.maintenant.value,
  prenom: userStore.user?.prenom,
  naissance: naissance.value,
  coursDuJour: props.journee.coursDuJour.value,
  semaine: props.journee.semaine.value,
  semaines: props.journee.semaines.value,
  semaineFormation: props.journee.semaineFormation.value,
  vacances: props.journee.vacances.value,
  evaluations: props.journee.evaluations.value,
}, tirage)));

const heure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
const lieu = (seance) => seance.salle || 'lieu non précisé';

const semaine = computed(() => {
  const numero = props.journee.semaineFormation.value;
  return numero ? `${numero}${numero === 1 ? 're' : 'e'} semaine de formation` : null;
});

// La semaine en barres, une par jour : la hauteur dit les heures de cours, aujourd'hui en jaune.
const jours = computed(() => {
  const semaine = props.journee.semaine.value;
  const plusLong = Math.max(60, ...semaine.map(jour => jour.minutes));
  const aujourdhui = props.journee.maintenant.value.toDateString();
  return semaine.map(jour => ({
    ...jour,
    initiale: jour.date.toLocaleDateString('fr-FR', {weekday: 'narrow'}),
    nom: jour.date.toLocaleDateString('fr-FR', {weekday: 'long'}),
    hauteur: `${Math.max(6, Math.round(jour.minutes / plusLong * 100))}%`,
    etat: jour.date.toDateString() === aujourdhui ? 'aujourdhui' : (jour.date < props.journee.maintenant.value ? 'passe' : 'a-venir'),
  }));
});

const totalSemaine = computed(() => props.journee.semaine.value.reduce((total, jour) => total + jour.minutes, 0));

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
      <span v-if="semaine" class="bienvenue__formation"><br>{{ semaine }}</span>
    </p>
    <p
        v-if="evenement"
        :class="['bienvenue__evenement', `bienvenue__evenement--${evenement.cle}`]"
    >
      <span class="bienvenue__evenement-icone" aria-hidden="true"><i :class="evenement.icone"></i></span>
      <span class="bienvenue__evenement-texte">{{ evenement.texte }}</span>
    </p>

    <div class="bienvenue__milieu">
      <figure v-if="journee.cours.value && totalSemaine > 0" class="bienvenue__semaine">
        <ol class="bienvenue__jours">
          <li v-for="jour in jours" :key="jour.nom" :class="['bienvenue__jour', `bienvenue__jour--${jour.etat}`]">
            <span class="bienvenue__barre" aria-hidden="true"><span :style="{ height: jour.hauteur }"></span></span>
            <span class="bienvenue__initiale" aria-hidden="true">{{ jour.initiale }}</span>
            <span class="sr-only">{{ jour.nom }} : {{ jour.minutes ? formatDelai(jour.minutes) : 'pas de cours' }}{{ jour.etat === 'aujourdhui' ? ', aujourd\'hui' : '' }}</span>
          </li>
        </ol>
        <figcaption class="bienvenue__legende">Cette semaine · {{ formatDelai(totalSemaine) }} de cours</figcaption>
      </figure>

    </div>

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
  margin: 0;
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

/* Le milieu de la carte : la semaine, centrée entre l'en-tête et les repères du moment. Lui seul
   a des marges automatiques, pour que l'espace libre se partage également dessus et dessous. */
.bienvenue__milieu {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  margin: auto 0;
  padding: 1.5rem 0;
}

.bienvenue__semaine {
  width: 100%;
  max-width: 17rem;
  margin: 0;
}

.bienvenue__jours {
  display: flex;
  justify-content: center;
  gap: 0.625rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.bienvenue__legende {
  margin-top: 0.75rem;
  text-align: center;
  font-size: 0.9375rem;
  color: var(--p-primary-200);
}

/* L'événement du jour, rattaché à l'en-tête : une capsule sur un voile clair, toujours sur une
   ligne (les messages font 31 caractères au plus) ; un prénom trop long s'abrège plutôt que de
   déformer la capsule. Icône violette sur l'accent : 5,29:1. */
.bienvenue__evenement {
  position: relative;
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: 0.5rem;
  max-width: calc(100% - 2rem);
  margin: 0.875rem 0 0;
  padding: 0.25rem 0.875rem 0.25rem 0.25rem;
  border-radius: 999px;
  font-size: 0.9375rem;
  background: color-mix(in srgb, #FFFFFF 12%, transparent);
}

.bienvenue__evenement-texte {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bienvenue__evenement-icone {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  font-size: 0.875rem;
  background: var(--accent-color);
  color: var(--p-primary-500);
}

/* Le jour de son anniversaire, la capsule passe au jaune plein. */
.bienvenue__evenement--anniversaire {
  font-weight: 700;
  background: var(--accent-color);
  color: var(--p-primary-500);
}

.bienvenue__evenement--anniversaire .bienvenue__evenement-icone {
  background: var(--p-primary-500);
  color: var(--accent-color);
}

.bienvenue__jour {
  display: flex;
  flex: 0 1 2.25rem;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
}

.bienvenue__barre {
  display: flex;
  align-items: flex-end;
  width: 100%;
  max-width: 2.25rem;
  height: 7rem;
}

.bienvenue__barre span {
  width: 100%;
  border-radius: 999px;
  background: var(--p-primary-200);
}

/* Les barres disent une quantité : chacune garde 3:1 au moins sur le violet. */
.bienvenue__jour--passe .bienvenue__barre span {
  background: var(--p-primary-400);
}

.bienvenue__jour--aujourdhui .bienvenue__barre span {
  background: var(--accent-color);
}

.bienvenue__initiale {
  font-size: 0.875rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--p-primary-200);
}

.bienvenue__jour--aujourdhui .bienvenue__initiale {
  color: var(--accent-color);
}

/* Sur un téléphone, la carte se resserre pour que la journée tienne à l'écran dès l'arrivée : un
   bonjour sur une ligne, sans la semaine, les deux repères côte à côte. */
@media (max-width: 1023px) {
  .bienvenue {
    min-height: 0;
    padding: 0.875rem 1rem;
  }

  .bienvenue__bonjour {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 0 0.375rem;
    align-items: baseline;
    font-size: 1.5rem !important;
  }

  .bienvenue__date {
    margin-top: 0.125rem;
    font-size: 0.9375rem;
  }

  /* Le téléphone reste léger : ni semaine de formation, ni message du jour, ni charge de la
     semaine. */
  .bienvenue__formation,
  .bienvenue__evenement,
  .bienvenue__milieu {
    display: none;
  }

  .bienvenue__infos {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 0.25rem 1.5rem;
    padding-top: 0.625rem;
    max-width: none;
  }

  .bienvenue__infos dd {
    font-size: 1rem;
  }

  .bienvenue__cercle--accent {
    width: 5.5rem;
    height: 5.5rem;
    right: -1.75rem;
    top: -1.75rem;
  }

  .bienvenue__cercle--clair {
    right: 4.25rem;
    top: 1.25rem;
    width: 1.125rem;
    height: 1.125rem;
  }

  .bienvenue__cercle--point,
  .bienvenue__cercle--sombre {
    display: none;
  }
}
</style>
