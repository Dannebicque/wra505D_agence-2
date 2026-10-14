<script setup>
import {computed, onMounted, onUnmounted, ref} from 'vue';
import {useUsersStore} from '@stores';
import {getMonEmploiDuTempsService} from '@requests';
import {formatDelai, getSituationDuJour, minutesEntre, parseEdtDate} from '@/utils/prochainCours.js';
import {etatCours, typeSeance} from '@/utils/agenda.js';
import {libelleJour, premierCoursApres} from '@/utils/accueil.js';

const LIBELLES_TYPE = {cm: 'CM', td: 'TD', tp: 'TP', sae: 'SAÉ', evaluation: 'Éval.', autre: 'Cours'};

const userStore = useUsersStore();
const cours = ref(null);
const suivant = ref(null);
const hasError = ref(false);
const maintenant = ref(new Date());
let horloge = null;

const isoDate = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
const lundiDe = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate() - ((date.getDay() + 6) % 7));
const heure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
const dateAttr = (date) => `${isoDate(date)}T${heure(date)}`;

const lireSemaine = async (lundi) => (await getMonEmploiDuTempsService(isoDate(lundi))).cours;

onMounted(async () => {
  horloge = setInterval(() => {
    maintenant.value = new Date();
  }, 30000);

  const lundi = lundiDe(maintenant.value);
  try {
    cours.value = await lireSemaine(lundi);
  } catch {
    hasError.value = true;
    return;
  }
  // Le vendredi soir, le prochain cours est dans la semaine suivante : une seconde lecture,
  // seulement dans ce cas.
  suivant.value = premierCoursApres(cours.value, maintenant.value);
  if (!suivant.value) {
    try {
      const lundiSuivant = new Date(lundi);
      lundiSuivant.setDate(lundi.getDate() + 7);
      suivant.value = premierCoursApres(await lireSemaine(lundiSuivant), maintenant.value);
    } catch {
      suivant.value = null;
    }
  }
});

onUnmounted(() => {
  clearInterval(horloge);
});

const coursDuJour = computed(() => (cours.value ?? [])
    .map(seance => ({...seance, start: parseEdtDate(seance.debut), end: parseEdtDate(seance.fin)}))
    .filter(seance => seance.start && isoDate(seance.start) === isoDate(maintenant.value))
    .sort((a, b) => a.start - b.start));

const situation = computed(() => getSituationDuJour(coursDuJour.value, maintenant.value));

const intitule = (seance) => [seance.codeModule, seance.libModule].filter(Boolean).join(' ');

// Le prochain cours n'est signalé dans la liste que s'il n'y en a pas en cours : un seul repère.
const estProchain = (seance) => !situation.value.enCours
    && situation.value.prochain?.debut.getTime() === seance.start.getTime()
    && situation.value.prochain.codeModule === seance.codeModule;

const styleSeance = (seance) => {
  const type = typeSeance(seance);
  return {
    '--seance-fond': `var(--p-seance-${type}-background)`,
    '--seance-couleur': `var(--p-seance-${type}-color)`,
    '--seance-pastille': `var(--p-seance-${type}-pill-background)`,
    '--seance-pastille-texte': `var(--p-seance-${type}-pill-color)`,
  };
};
</script>

<template>
  <section class="journee" aria-labelledby="accueil-bonjour">
    <!-- Le bandeau dit où en est la journée ; les cartes à côté portent les cours, sans doublon. -->
    <div class="journee__bandeau">
      <span class="journee__cercle journee__cercle--accent" aria-hidden="true"></span>
      <span class="journee__cercle journee__cercle--clair" aria-hidden="true"></span>

      <h2 id="accueil-bonjour" class="journee__bonjour">
        <span class="font-light">Bonjour,</span> {{ userStore.user?.prenom }}
      </h2>
      <p class="journee__date">{{ maintenant.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) }}</p>

      <p v-if="hasError" class="journee__etat">L'emploi du temps est indisponible pour le moment.</p>
      <Skeleton v-else-if="cours === null" width="70%" height="1.5rem" class="mt-2 opacity-40" aria-hidden="true"/>
      <p v-else-if="situation.enCours" class="journee__etat">
        En cours jusqu'à <time :datetime="dateAttr(situation.enCours.fin)">{{ heure(situation.enCours.fin) }}</time>,
        encore {{ formatDelai(minutesEntre(maintenant, situation.enCours.fin)) }}<template v-if="situation.enCours.salle">,
        en <strong>{{ situation.enCours.salle }}</strong></template>.
      </p>
      <p v-else-if="situation.prochain" class="journee__etat">
        Prochain cours à <time :datetime="dateAttr(situation.prochain.debut)">{{ heure(situation.prochain.debut) }}</time>,
        dans {{ formatDelai(minutesEntre(maintenant, situation.prochain.debut)) }}<template v-if="situation.prochain.salle">,
        en <strong>{{ situation.prochain.salle }}</strong></template>.
      </p>
      <p v-else class="journee__etat">
        {{ situation.aDesCours ? 'Journée terminée.' : 'Pas de cours aujourd\'hui.' }}
        <template v-if="suivant">
          <span class="capitalize">{{ libelleJour(suivant.debut, maintenant) }}</span> à
          <time :datetime="dateAttr(suivant.debut)">{{ heure(suivant.debut) }}</time> :
          {{ intitule(suivant) }}<template v-if="suivant.salle"> en <strong>{{ suivant.salle }}</strong></template>.
        </template>
        <template v-else>Aucun cours prévu d'ici la semaine prochaine.</template>
      </p>

      <RouterLink to="/intranet/agenda" class="journee__semaine">
        Voir la semaine
        <span class="journee__fleche" aria-hidden="true"><i class="pi pi-arrow-right"></i></span>
      </RouterLink>
    </div>

    <!-- Les cours du jour côte à côte, comme une rangée de l'emploi du temps : le temps se lit de
         gauche à droite, et la couleur y garde son seul sens, le type de séance. -->
    <ol v-if="coursDuJour.length" class="journee__cours" aria-label="Cours d'aujourd'hui">
      <li
          v-for="seance in coursDuJour"
          :key="seance.id ?? `${seance.debut}-${seance.codeModule}`"
          :class="['cours', `cours--${etatCours(seance, maintenant).etat}`]"
          :style="styleSeance(seance)"
      >
        <p class="cours__horaire">
          <time :datetime="dateAttr(seance.start)">{{ heure(seance.start) }}</time>
          –
          <time :datetime="dateAttr(seance.end)">{{ heure(seance.end) }}</time>
        </p>
        <p class="cours__titre">
          <span class="cours__type">{{ LIBELLES_TYPE[typeSeance(seance)] }}</span>
          <span class="cours__code">{{ seance.codeModule }}</span>
        </p>
        <p class="cours__libelle">{{ seance.libModule }}</p>
        <p v-if="seance.salle" class="cours__salle">
          <i class="pi pi-map-marker" aria-hidden="true"></i>
          {{ seance.salle }}
        </p>
        <p v-if="etatCours(seance, maintenant).etat === 'en-cours'" class="cours__etat">En cours</p>
        <p v-else-if="estProchain(seance)" class="cours__etat">Dans {{ formatDelai(minutesEntre(maintenant, seance.start)) }}</p>
        <p v-else-if="etatCours(seance, maintenant).etat === 'passe'" class="sr-only">Terminé</p>
      </li>
    </ol>
  </section>
</template>

<style scoped>
.journee {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 4px 12px -4px rgb(0 0 0 / 0.06);
  overflow: hidden;
}

@media (min-width: 1024px) {
  .journee {
    grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr);
  }
}

/* Le violet de l'IUT en plein, quel que soit le thème : le texte blanc y garde 9,95:1. Les
   cercles reprennent ceux du logo, rangés dans l'angle pour ne jamais passer sous le texte. */
.journee__bandeau {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 1.125rem 1.25rem 1rem;
  background: var(--p-primary-500);
  color: #FFFFFF;
  overflow: hidden;
}

.journee__cercle {
  position: absolute;
  border-radius: 999px;
  pointer-events: none;
}

.journee__cercle--accent {
  right: -1.75rem;
  top: -1.75rem;
  width: 5.5rem;
  height: 5.5rem;
  background: var(--accent-color);
}

.journee__cercle--clair {
  right: 4.25rem;
  top: 0.625rem;
  width: 1.25rem;
  height: 1.25rem;
  background: var(--p-primary-300);
}

/* Trois niveaux : le bonjour, l'état de la journée, la date. */
.journee__bonjour {
  position: relative;
  margin: 0 !important;
  max-width: calc(100% - 5rem);
  font-size: 1.5rem !important;
  font-weight: 700;
  line-height: 1.25;
  color: inherit;
}

.journee__date {
  position: relative;
  margin: 0.125rem 0 0;
  font-size: 0.9375rem;
  color: var(--p-primary-200);
}

.journee__date::first-letter {
  text-transform: uppercase;
}

.journee__etat {
  position: relative;
  margin: 0.75rem 0 0;
  font-size: 1.125rem;
  line-height: 1.4;
}

.journee__etat strong {
  font-weight: 700;
}

/* Blanc sur le violet, flèche violette sur l'accent (5,29:1). */
.journee__semaine {
  position: relative;
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  margin-top: auto;
  padding-top: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
  color: #FFFFFF;
}

.journee__semaine:hover {
  text-decoration: underline;
}

.journee__semaine:focus-visible {
  outline: 2px solid #FFFFFF;
  outline-offset: 2px;
  border-radius: 6px;
}

.journee__fleche {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  font-size: 0.75rem;
  background: var(--accent-color);
  color: var(--p-primary-500);
  transition: transform 150ms ease;
}

.journee__semaine:hover .journee__fleche {
  transform: translateX(2px);
}

@media (prefers-reduced-motion: reduce) {
  .journee__fleche {
    transition: none;
  }
}

.journee__cours {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
  align-content: center;
  gap: 0.625rem;
  margin: 0;
  padding: 1rem 1.25rem;
  list-style: none;
}

.cours {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
  padding: 0.75rem 0.875rem;
  border-radius: 18px;
  /* Le cercle du logo dans l'angle, comme sur les cartes de l'emploi du temps : teinté à 14 % de
     la pastille, il laisse au texte 4,91:1 au pire. */
  background:
      radial-gradient(circle 2.25rem at calc(100% - 0.625rem) calc(100% - 0.625rem), color-mix(in srgb, var(--seance-pastille) 14%, var(--seance-fond)) calc(100% - 0.5px), transparent 100%),
      var(--seance-fond);
}

.cours p {
  margin: 0;
}

.cours__horaire {
  font-size: 0.9375rem;
  font-variant-numeric: tabular-nums;
}

.cours__titre {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-top: 0.25rem !important;
}

.cours__type {
  padding: 0 0.5rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.375rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  background: var(--seance-pastille);
  color: var(--seance-pastille-texte);
}

.cours__code {
  font-weight: 700;
  color: var(--seance-couleur);
}

.cours__libelle {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.35;
}

.cours__salle {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.9375rem;
}

.cours__salle i {
  font-size: 0.875rem;
  color: var(--seance-couleur);
}

.cours__etat {
  margin-top: auto !important;
  padding-top: 0.25rem;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--seance-couleur);
}

/* Le cours en cours se repère à un liseré et à son mot, pas à la couleur seule. */
.cours--en-cours {
  box-shadow: inset 0 0 0 2px var(--seance-couleur);
}

.cours--passe {
  background: var(--p-content-hover-background);
}

.cours--passe .cours__type {
  background: var(--p-text-muted-color);
  color: var(--p-content-background);
}

.cours--passe .cours__code,
.cours--passe .cours__salle i {
  color: var(--p-text-muted-color);
}
</style>
