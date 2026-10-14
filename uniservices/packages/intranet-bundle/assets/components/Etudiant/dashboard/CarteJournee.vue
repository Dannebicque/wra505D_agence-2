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
    <!-- Le bandeau dit où en est la journée ; la liste dessous porte les cours, sans doublon (TB-15). -->
    <div class="journee__bandeau">
      <span class="journee__cercle" aria-hidden="true"></span>

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
    </div>

    <div class="journee__corps">
      <ol v-if="coursDuJour.length" class="journee__liste" aria-label="Cours d'aujourd'hui">
        <li
            v-for="seance in coursDuJour"
            :key="seance.id ?? `${seance.debut}-${seance.codeModule}`"
            :class="['journee__cours', `journee__cours--${etatCours(seance, maintenant).etat}`]"
            :style="styleSeance(seance)"
        >
          <time class="journee__heure" :datetime="dateAttr(seance.start)">{{ heure(seance.start) }}</time>
          <span class="journee__carte">
            <span class="journee__type">{{ LIBELLES_TYPE[typeSeance(seance)] }}</span>
            <strong class="journee__code">{{ seance.codeModule }}</strong>
            <span class="journee__libelle">{{ seance.libModule }}</span>
            <span v-if="seance.salle" class="journee__salle">· {{ seance.salle }}</span>
            <span v-if="etatCours(seance, maintenant).etat === 'en-cours'" class="journee__maintenant">en cours</span>
            <span v-else-if="estProchain(seance)" class="journee__maintenant">dans {{ formatDelai(minutesEntre(maintenant, seance.start)) }}</span>
            <span v-else-if="etatCours(seance, maintenant).etat === 'passe'" class="sr-only">, terminé</span>
          </span>
        </li>
      </ol>
      <RouterLink to="/intranet/agenda" class="journee__lien">
        Voir la semaine
        <span class="journee__fleche" aria-hidden="true"><i class="pi pi-arrow-right"></i></span>
      </RouterLink>
    </div>
  </section>
</template>

<style scoped>
.journee {
  display: flex;
  flex-direction: column;
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 4px 12px -4px rgb(0 0 0 / 0.06);
  overflow: hidden;
}

/* Le violet de l'IUT en plein, quel que soit le thème : le texte blanc y garde 9,95:1. Un seul
   cercle, celui du logo, rangé dans l'angle pour ne jamais passer sous le texte. */
.journee__bandeau {
  position: relative;
  padding: 1rem 1.25rem 1.125rem;
  background: var(--p-primary-500);
  color: #FFFFFF;
  overflow: hidden;
}

.journee__cercle {
  position: absolute;
  right: -1.5rem;
  top: -1.5rem;
  width: 4.5rem;
  height: 4.5rem;
  border-radius: 999px;
  background: var(--accent-color);
  pointer-events: none;
}

/* Trois niveaux : le bonjour, l'état de la journée, la date (TB-13). */
.journee__bonjour {
  position: relative;
  margin: 0 !important;
  max-width: calc(100% - 3.5rem);
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
  margin: 0.625rem 0 0;
  font-size: 1.125rem;
  line-height: 1.4;
}

.journee__etat strong {
  font-weight: 700;
}

.journee__corps {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem 1.25rem 1.125rem;
}

.journee__liste {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.journee__cours {
  display: grid;
  grid-template-columns: 3rem minmax(0, 1fr);
  align-items: center;
  gap: 0.5rem;
}

.journee__heure {
  font-size: 0.9375rem;
  font-variant-numeric: tabular-nums;
  color: var(--p-text-muted-color);
}

.journee__carte {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.375rem;
  min-width: 0;
  padding: 0.4375rem 0.75rem;
  border-radius: 14px;
  font-size: 1rem;
  background: var(--seance-fond);
}

.journee__type {
  padding: 0 0.5rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.25rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  background: var(--seance-pastille);
  color: var(--seance-pastille-texte);
}

.journee__code {
  font-weight: 600;
  color: var(--seance-couleur);
}

.journee__libelle {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.journee__salle {
  color: var(--p-text-muted-color);
}

/* Le cours en cours se repère à un liseré fin et à son mot, pas à la couleur seule. */
.journee__cours--en-cours .journee__carte {
  box-shadow: inset 0 0 0 1.5px var(--seance-couleur);
}

.journee__maintenant {
  margin-left: auto;
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--seance-couleur);
}

.journee__cours--passe .journee__carte {
  background: var(--p-content-hover-background);
}

.journee__cours--passe .journee__type {
  background: var(--p-text-muted-color);
  color: var(--p-content-background);
}

.journee__cours--passe .journee__code {
  color: var(--p-text-muted-color);
}

.journee__lien {
  align-self: flex-end;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  margin-top: auto;
  padding: 0 0.25rem 0 0.75rem;
  border-radius: 999px;
  font-size: 1rem;
  font-weight: 600;
  color: var(--p-seance-cm-color);
  transition: background-color 150ms ease;
}

.journee__lien:hover {
  background: var(--p-seance-cm-background);
}

.journee__lien:focus-visible {
  outline: 2px solid var(--p-seance-cm-color);
  outline-offset: 2px;
}

/* Une pastille pâle autour de la flèche, aux couleurs du bloc : présente sans peser. */
.journee__fleche {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 0.75rem;
  background: var(--p-seance-cm-background);
  transition: transform 150ms ease;
}

.journee__lien:hover .journee__fleche {
  transform: translateX(2px);
}

@media (prefers-reduced-motion: reduce) {
  .journee__lien,
  .journee__fleche {
    transition: none;
  }
}
</style>
