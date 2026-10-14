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

const apres = computed(() => suivant.value
    && `${libelleJour(suivant.value.debut, maintenant.value)} ${heure(suivant.value.debut)}, ${suivant.value.codeModule}${suivant.value.salle ? ` en ${suivant.value.salle}` : ''}`);

const ensuite = computed(() => {
  const {prochain} = situation.value;
  if (prochain) {
    return `${heure(prochain.debut)}, ${intitule(prochain)}${prochain.salle ? ` en ${prochain.salle}` : ''}`;
  }
  return suivant.value ? `fin de journée · ${apres.value}` : 'fin de journée';
});

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
    <div class="journee__bandeau">
      <span class="journee__cercle journee__cercle--accent" aria-hidden="true"></span>
      <span class="journee__cercle journee__cercle--clair" aria-hidden="true"></span>
      <span class="journee__cercle journee__cercle--sombre" aria-hidden="true"></span>

      <h2 id="accueil-bonjour" class="journee__bonjour">
        <span class="font-light">Bonjour,</span> {{ userStore.user?.prenom }}
      </h2>
      <p class="journee__date">{{ maintenant.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) }}</p>

      <div v-if="hasError" class="journee__moment">
        <p class="journee__titre">L'emploi du temps est indisponible pour le moment.</p>
      </div>
      <div v-else-if="cours === null" class="journee__moment" aria-hidden="true">
        <Skeleton width="6rem" height="1.25rem" border-radius="999px" class="mb-2 opacity-40"/>
        <Skeleton width="70%" height="1.5rem" class="opacity-40"/>
      </div>
      <div v-else-if="situation.enCours" class="journee__moment">
        <p class="journee__etiquette">En cours</p>
        <p class="journee__titre">{{ intitule(situation.enCours) }}</p>
        <p class="journee__detail">
          <i class="pi pi-map-marker" aria-hidden="true"></i>
          <span class="sr-only">Lieu :</span>
          {{ situation.enCours.salle || 'Lieu non précisé' }} · jusqu'à
          <time :datetime="dateAttr(situation.enCours.fin)">{{ heure(situation.enCours.fin) }}</time>,
          encore {{ formatDelai(minutesEntre(maintenant, situation.enCours.fin)) }}
        </p>
        <p class="journee__ensuite">Ensuite : {{ ensuite }}</p>
      </div>
      <div v-else-if="situation.prochain" class="journee__moment">
        <p class="journee__etiquette">Prochain cours</p>
        <p class="journee__titre">{{ intitule(situation.prochain) }}</p>
        <p class="journee__detail">
          <i class="pi pi-map-marker" aria-hidden="true"></i>
          <span class="sr-only">Lieu :</span>
          {{ situation.prochain.salle || 'Lieu non précisé' }} · à
          <time :datetime="dateAttr(situation.prochain.debut)">{{ heure(situation.prochain.debut) }}</time>,
          dans {{ formatDelai(minutesEntre(maintenant, situation.prochain.debut)) }}
        </p>
      </div>
      <div v-else class="journee__moment">
        <p class="journee__etiquette">{{ situation.aDesCours ? 'Journée terminée' : 'Pas de cours aujourd\'hui' }}</p>
        <p class="journee__titre">
          <template v-if="suivant">
            <span class="capitalize">{{ libelleJour(suivant.debut, maintenant) }}</span>{{ ' ' }}
            <time :datetime="dateAttr(suivant.debut)">{{ heure(suivant.debut) }}</time> · {{ intitule(suivant) }}
          </template>
          <template v-else>Aucun cours prévu d'ici la semaine prochaine.</template>
        </p>
        <p v-if="suivant?.salle" class="journee__detail">
          <i class="pi pi-map-marker" aria-hidden="true"></i>
          <span class="sr-only">Lieu :</span>
          {{ suivant.salle }}
        </p>
      </div>
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

/* Le violet de l'IUT en plein, quel que soit le thème : le texte blanc y garde 9,95:1. Les
   cercles reprennent ceux du logo, rangés à droite pour ne jamais passer sous le texte. */
.journee__bandeau {
  position: relative;
  padding: 1.125rem 1.25rem 1.25rem;
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
  right: -2.25rem;
  top: -2.25rem;
  width: 6.5rem;
  height: 6.5rem;
  background: var(--accent-color);
}

.journee__cercle--clair {
  right: 4rem;
  top: 2.5rem;
  width: 1.75rem;
  height: 1.75rem;
  background: var(--p-primary-300);
}

.journee__cercle--sombre {
  right: 1.25rem;
  bottom: -2.5rem;
  width: 5rem;
  height: 5rem;
  background: var(--p-primary-600);
}

.journee__bonjour {
  position: relative;
  margin: 0 !important;
  font-size: 1rem !important;
  font-weight: 700;
  color: inherit;
}

.journee__date {
  position: relative;
  margin: 0 0 0.875rem;
  font-size: 0.875rem;
  color: var(--p-primary-200);
}

.journee__date::first-letter {
  text-transform: uppercase;
}

.journee__moment {
  position: relative;
  max-width: calc(100% - 6rem);
}

.journee__moment p {
  margin: 0;
}

/* Texte violet sur l'accent : 5,29:1. */
.journee__etiquette {
  display: inline-block;
  margin-bottom: 0.375rem !important;
  padding: 0 0.625rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.375rem;
  background: var(--accent-color);
  color: var(--p-primary-500);
}

.journee__titre {
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.3;
}

.journee__detail {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.25rem !important;
  font-size: 0.875rem;
  color: var(--p-primary-100);
}

.journee__ensuite {
  margin-top: 0.75rem !important;
  padding-top: 0.625rem;
  border-top: 1px solid color-mix(in srgb, #FFFFFF 22%, transparent);
  font-size: 0.875rem;
  color: var(--p-primary-200);
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
  font-size: 0.8125rem;
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
  font-size: 0.875rem;
  background: var(--seance-fond);
}

.journee__type {
  padding: 0 0.5rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1.25rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  background: var(--seance-pastille);
  color: var(--seance-pastille-texte);
}

.journee__code {
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
  font-size: 0.75rem;
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
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 44px;
  margin-top: auto;
  padding: 0.25rem 0.25rem 0.25rem 1rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 600;
  background: var(--p-seance-cm-background);
  color: var(--p-seance-cm-color);
  transition: background-color 150ms ease;
}

.journee__lien:hover {
  background: color-mix(in srgb, var(--p-seance-cm-pill-background) 14%, var(--p-seance-cm-background));
}

.journee__lien:focus-visible {
  outline: 2px solid var(--p-seance-cm-color);
  outline-offset: 2px;
}

/* La flèche dans une pastille, comme les types de séance de l'agenda : elle glisse au survol. */
.journee__fleche {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 0.75rem;
  background: var(--p-seance-cm-pill-background);
  color: var(--p-seance-cm-pill-color);
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
