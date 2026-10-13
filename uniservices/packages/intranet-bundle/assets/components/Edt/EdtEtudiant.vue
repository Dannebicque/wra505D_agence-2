<script setup>
import { VueCal } from 'vue-cal'
import 'vue-cal/style'

import {computed, nextTick, onBeforeUnmount, onMounted, ref, watch} from 'vue'
import CoursCarte from './CoursCarte.vue'
import {getMonEmploiDuTempsService} from "@requests";
import {getISOWeekNumber} from "@helpers/date";
import {annonceAgenda, joursDeLaSemaine, libelleCours, libelleSemaine, titrePeriode, typeSeance} from '@/utils/agenda.js';
import {formatDelai, minutesEntre, parseEdtDate} from '@/utils/prochainCours.js';
import {ErrorView} from "@components";

// Bornes de la hauteur d'une heure : 40 px fait tenir 8 h – 19 h sur un écran de 800 px de haut ;
// au-delà de 80 px, la semaine s'étire pour rien.
const HAUTEUR_HEURE_MIN = 40;
const HAUTEUR_HEURE_MAX = 80;
const MARGE_BAS = 28;

const cadre = ref(null);
const vuecalRef = ref(null)
const hasError = ref(false);
const annonce = ref('');
const semaineChargee = ref(null);
const semaineFormation = ref(null);
// La plage horaire de l'année de l'étudiant, jamais celle de la semaine : la grille ne change
// pas de hauteur d'une semaine à l'autre.
const plage = ref({debut: '08:00', fin: '20:00'});
const events = ref([]);
const hauteurHeure = ref(56);
const maintenant = ref(new Date());
const horloge = setInterval(() => { maintenant.value = new Date(); }, 60000);

const vues = [
  { id: 'day', libelle: 'Jour' },
  { id: 'week', libelle: 'Semaine' },
];
// Cinq colonnes ne tiennent pas sur un téléphone : il ouvre la journée.
const vueAffichee = ref(window.matchMedia('(max-width: 767px)').matches ? 'day' : 'week');

const minutes = (heure) => {
  const [h, m] = heure.split(':').map(Number);
  return h * 60 + m;
};

const isoDate = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');

const lundiDe = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate() - ((date.getDay() + 6) % 7));

const estAujourdhui = (date) => isoDate(new Date(date)) === isoDate(maintenant.value);

// Le bouton « Aujourd'hui » ne sert que si aujourd'hui n'est pas déjà à l'écran.
const aujourdhuiAffiche = (view) => {
  const jour = isoDate(maintenant.value);
  return isoDate(view.firstCellDate) <= jour && jour <= isoDate(view.lastCellDate);
};

// Samedi et dimanche ne s'affichent que la semaine où ils portent un cours.
const joursMasques = computed(() => {
  if (!semaineChargee.value) {
    return ['sat', 'sun'];
  }
  const jours = joursDeLaSemaine(new Date(`${semaineChargee.value}T00:00:00`), events.value).map(jour => jour.getDay());
  return [[6, 'sat'], [0, 'sun']].filter(([jour]) => !jours.includes(jour)).map(([, nom]) => nom);
});

// Toute la plage tient à l'écran, sans défiler, tant qu'une heure garde sa hauteur minimale.
const ajusterHauteur = () => {
  const corps = cadre.value?.querySelector('.vuecal__body');
  if (!corps) {
    return;
  }
  const defilement = corps.closest('.layout-main-container')?.scrollTop ?? window.scrollY;
  const disponible = window.innerHeight - (corps.getBoundingClientRect().top + defilement) - MARGE_BAS;
  const heures = Math.max(1, (minutes(plage.value.fin) - minutes(plage.value.debut)) / 60);
  hauteurHeure.value = Math.min(HAUTEUR_HEURE_MAX, Math.max(HAUTEUR_HEURE_MIN, Math.floor(disponible / heures)));
};

let redimensionnement = null;
const surRedimensionnement = () => {
  cancelAnimationFrame(redimensionnement);
  redimensionnement = requestAnimationFrame(ajusterHauteur);
};
onMounted(() => window.addEventListener('resize', surRedimensionnement));
onBeforeUnmount(() => {
  clearInterval(horloge);
  window.removeEventListener('resize', surRedimensionnement);
});

const chargerSemaine = async (lundi) => {
  const semaine = isoDate(lundi);
  if (semaine === semaineChargee.value) {
    return;
  }
  try {
    const reponse = await getMonEmploiDuTempsService(semaine);
    plage.value = reponse.plage;
    semaineFormation.value = reponse.semaineFormation;
    events.value = reponse.cours.map(cours => ({
      ...cours,
      start: parseEdtDate(cours.debut),
      end: parseEdtDate(cours.fin),
      location: cours.salle,
      title: [cours.codeModule, cours.libModule].filter(Boolean).join(' - '),
    }));
    semaineChargee.value = semaine;
    hasError.value = false;
    await nextTick();
    ajusterHauteur();
  } catch (error) {
    hasError.value = true;
  }
};

watch(() => vuecalRef.value?.view?.start, async (debut) => {
  if (debut) {
    await chargerSemaine(lundiDe(new Date(debut)));
    annoncerPeriode();
  }
}, { immediate: true });

const annoncerPeriode = () => {
  const view = vuecalRef.value?.view;
  if (!view) {
    return;
  }
  annonce.value = annonceAgenda({
    vue: view.id,
    debut: view.firstCellDate,
    fin: view.lastCellDate,
    cours: events.value,
  });
};

// 6 px de gouttière, au-dessus et au-dessous de chaque carte.
const hauteurCarte = (cours) => ((cours.end - cours.start) / 3600000) * hauteurHeure.value - 6;

const heure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});

const selectedEvent = ref(null)
const visible = ref(false)

const openDialog = ({ event }) => {
  selectedEvent.value = event
  visible.value = true
}

const LIBELLES_TYPE = {cm: 'CM', td: 'TD', tp: 'TP', sae: 'SAÉ', evaluation: 'Évaluation'};
const detail = computed(() => {
  const cours = selectedEvent.value;
  if (!cours) {
    return null;
  }
  const type = typeSeance(cours);
  return {
    type,
    groupe: cours.groupe && cours.groupe !== cours.type ? cours.groupe : null,
    jour: titrePeriode({vue: 'day', debut: cours.start}),
    horaire: `${heure(cours.start)} – ${heure(cours.end)}`,
    duree: formatDelai(minutesEntre(cours.start, cours.end)),
  };
});
</script>

<template>

  <Dialog
      v-model:visible="visible"
      modal
      dismissable-mask
      :draggable="false"
      aria-labelledby="edt-detail-titre"
      class="edt-detail"
      :style="{
        width: '26rem',
        '--seance-fond': `var(--p-seance-${detail?.type}-background)`,
        '--seance-couleur': `var(--p-seance-${detail?.type}-color)`,
        '--seance-pastille': `var(--p-seance-${detail?.type}-pill-background)`,
        '--seance-pastille-texte': `var(--p-seance-${detail?.type}-pill-color)`,
      }"
      :breakpoints="{ '575px': '92vw' }"
  >
    <template #header>
      <div v-if="detail" class="edt-detail__entete">
        <p class="edt-detail__etiquettes">
          <span class="edt-detail__pastille">{{ detail.type === 'autre' ? (selectedEvent.type || 'Cours') : LIBELLES_TYPE[detail.type] }}</span>
          <span v-if="detail.groupe">{{ detail.groupe }}</span>
        </p>
        <h2 id="edt-detail-titre" class="edt-detail__titre">
          <strong>{{ selectedEvent.codeModule }}</strong> {{ selectedEvent.libModule }}
        </h2>
      </div>
    </template>
    <!-- Le focus arrive sur le contenu, pas sur la croix : elle ne s'entoure plus d'un anneau à
         chaque ouverture à la souris, et le clavier la trouve d'une tabulation. -->
    <dl v-if="detail" class="edt-detail__liste" tabindex="-1" autofocus>
      <div>
        <dt><i class="pi pi-calendar" aria-hidden="true"></i><span class="sr-only">Date</span></dt>
        <dd>{{ detail.jour }}</dd>
      </div>
      <div>
        <dt><i class="pi pi-clock" aria-hidden="true"></i><span class="sr-only">Horaire</span></dt>
        <dd>{{ detail.horaire }} <span class="text-muted-color">· {{ detail.duree }}</span></dd>
      </div>
      <div v-if="selectedEvent.location">
        <dt><i class="pi pi-map-marker" aria-hidden="true"></i><span class="sr-only">Salle</span></dt>
        <dd>{{ selectedEvent.location }}</dd>
      </div>
      <div>
        <dt><i class="pi pi-user" aria-hidden="true"></i><span class="sr-only">Enseignant</span></dt>
        <dd>{{ selectedEvent.enseignant || 'Enseignant non renseigné' }}</dd>
      </div>
    </dl>
  </Dialog>

  <p role="status" class="sr-only">{{ annonce }}</p>

  <ErrorView v-if="hasError" message="Une erreur est survenue lors du chargement de l'emploi du temps. Veuillez réessayer plus tard." />
  <div v-else ref="cadre" class="edt-cadre">
    <vue-cal
        ref="vuecalRef"
        locale="fr"
        :hide-weekdays="joursMasques"
        time
        :time-from="minutes(plage.debut)"
        :time-to="minutes(plage.fin)"
        :time-step="60"
        :time-cell-height="hauteurHeure"
        :views="['day', 'week']"
        v-model:view="vueAffichee"
        :theme="false"
        diy
        :events="events"
        :style="{ '--hauteur-heure': `${hauteurHeure}px`, '--libelle-fin': `'${plage.fin}'` }"
        @event-click="openDialog">

      <template #header="{ view }">
        <div class="edt-barre">
          <div class="edt-barre__periode">
            <div class="edt-barre__fleches">
              <Button
                  icon="pi pi-chevron-left"
                  text
                  rounded
                  :aria-label="view.id === 'day' ? 'Jour précédent' : 'Semaine précédente'"
                  @click="view.previous"
                  class="min-w-[44px]! min-h-[44px]!"
              />
              <Button
                  icon="pi pi-chevron-right"
                  text
                  rounded
                  :aria-label="view.id === 'day' ? 'Jour suivant' : 'Semaine suivante'"
                  @click="view.next"
                  class="min-w-[44px]! min-h-[44px]!"
              />
            </div>
            <div class="min-w-0">
              <h2 class="edt-barre__titre">{{ titrePeriode({ vue: view.id, debut: view.firstCellDate, fin: view.lastCellDate }) }}</h2>
              <p class="edt-barre__semaine">{{ libelleSemaine(getISOWeekNumber(view.firstCellDate), semaineFormation) }}</p>
            </div>
          </div>

          <div class="edt-barre__actions">
            <Button v-if="!aujourdhuiAffiche(view)" label="Aujourd'hui" outlined class="min-h-[44px]" @click="view.goToToday()" />
            <SelectButton
                v-model="vueAffichee"
                :options="vues"
                option-label="libelle"
                option-value="id"
                :allow-empty="false"
                aria-label="Affichage"
            />
          </div>
        </div>
      </template>

      <template #weekday-heading="{ date }">
        <span class="edt-jour__nom">{{ new Date(date).toLocaleDateString('fr-FR', { weekday: 'short' }) }}</span>
        <span :class="['edt-jour__numero', { 'edt-jour__numero--aujourdhui': estAujourdhui(date) }]">{{ new Date(date).getDate() }}</span>
        <span v-if="estAujourdhui(date)" class="sr-only">aujourd'hui</span>
      </template>

      <template #event="{ event }">
        <CoursCarte :cours="event" :hauteur="hauteurCarte(event)" :maintenant="maintenant" />
        <!-- Sans gestionnaire propre : son clic remonte jusqu'à vue-cal, qui émet event-click. La souris
             et le clavier ouvrent ainsi le détail par le même chemin. -->
        <button type="button" class="edt-cours" :aria-label="libelleCours(event)"></button>
      </template>
    </vue-cal>
  </div>
</template>

<style scoped>
@reference "../../assets/tailwind.css";

/* Le cadre .card de la page n'a pas de marge intérieure : l'agenda prend la sienne. */
.edt-cadre {
  @apply px-3 pt-3 pb-1 md:px-6 md:pt-5;
}

/* vue-cal réserve une barre « toute la journée » qu'un emploi du temps n'a pas. */
.edt-cadre :deep(.vuecal) {
  --vuecal-weekday-bar-size: 3.5rem;
  --vuecal-all-day-bar-size: 0px;
  --vuecal-schedules-bar-size: 0px;
}

/* La vue jour n'a pas d'en-tête de jour, mais vue-cal décale quand même la colonne des heures. */
.edt-cadre :deep(.vuecal--day-view) {
  --vuecal-weekday-bar-size: 0px;
}

.edt-barre {
  @apply flex flex-wrap items-center justify-between gap-x-6 gap-y-3 pt-2 pb-5;
}

.edt-barre__periode {
  @apply flex items-center gap-3 min-w-0;
}

.edt-barre__fleches {
  @apply flex items-center rounded-full bg-emphasis;
}

.edt-barre__titre {
  @apply m-0 text-xl font-bold leading-tight;
}

.edt-barre__semaine {
  @apply m-0 mt-0.5 text-sm text-muted-color;
}

.edt-barre__actions {
  @apply flex items-center gap-2;
}

.edt-jour__nom {
  @apply text-xs font-semibold uppercase tracking-wider text-muted-color;
}

.edt-jour__numero {
  @apply flex items-center justify-center w-9 h-9 rounded-full text-lg font-bold;
}

.edt-jour__numero--aujourdhui {
  @apply bg-primary text-primary-contrast;
}

:deep(.vuecal__weekday) {
  @apply flex flex-col items-center justify-center gap-0.5;
}

:deep(.vuecal__weekday--today) .edt-jour__nom {
  @apply text-primary;
}

/* Une marge en haut et en bas : la première et la dernière heure se lisent sur leur ligne. */
:deep(.vuecal__body),
:deep(.vuecal__time-column-inner) {
  margin-block: 0.75rem;
}

/* Les heures, posées sur les lignes, sont hors du flux : la colonne prend sa largeur ici. */
:deep(.vuecal__time-column) {
  width: 3.25rem;
  flex-shrink: 0;
}

:deep(.vuecal__time-column-inner) {
  @apply relative;
}

:deep(.vuecal__time-cell) {
  @apply relative text-muted-color text-xs;
  label {
    @apply absolute right-3 top-0 -translate-y-1/2 leading-none;
  }
}

/* vue-cal n'écrit que le début de chaque heure : la fin de la plage s'ajoute sous la grille. */
:deep(.vuecal__time-column-inner)::after {
  @apply absolute right-3 bottom-0 translate-y-1/2 text-xs text-muted-color leading-none;
  content: var(--libelle-fin);
}

/* Un trait léger à chaque heure, rien d'autre : la grille s'efface derrière les cours. */
:deep(.vuecal__cell) {
  background-image: linear-gradient(to bottom, color-mix(in srgb, var(--p-content-border-color) 70%, transparent) 1px, transparent 1px);
  background-size: 100% var(--hauteur-heure);
}

:deep(.vuecal__body) {
  border-bottom: 1px solid color-mix(in srgb, var(--p-content-border-color) 70%, transparent);
}

:deep(.vuecal__cell--today) {
  @apply rounded-xl;
  background-color: color-mix(in srgb, var(--p-primary-color) 5%, transparent);
}

/* Une gouttière autour de chaque cours : deux cours voisins ne se touchent jamais. */
:deep(.vuecal__event) {
  @apply bg-transparent;
  padding: 3px 5px;
  &:hover .cours-carte {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px -6px rgb(0 0 0 / 0.25);
  }
}

:deep(.vuecal__event-details) {
  @apply h-full;
}

.edt-cours {
  @apply absolute cursor-pointer;
  inset: 3px 5px;
  border-radius: 12px;
  &:focus-visible {
    outline: 3px solid var(--p-primary-color);
    outline-offset: 1px;
  }
}

:deep(.vuecal__now-line) {
  @apply border-primary;
  border-top-width: 2px;
  color: var(--p-primary-color);
  &::before {
    @apply absolute bg-primary rounded-full;
    content: '';
    width: 0.625rem;
    height: 0.625rem;
    left: -0.3125rem;
    top: -0.375rem;
  }
  /* Son heure se posait sur le texte du cours en cours ; la carte dit déjà « En cours ». */
  span {
    @apply hidden;
  }
}
</style>

<style>
/* Le détail d'un cours est téléporté hors du composant : ses styles ne peuvent pas être scopés. */
.edt-detail.p-dialog {
  border: 0;
  border-radius: 20px;
  overflow: hidden;
}

.edt-detail .p-dialog-header {
  align-items: flex-start;
  padding: 1.25rem 1.25rem 1.25rem 1.5rem;
  background: var(--seance-fond);
}

.edt-detail .p-dialog-close-button:focus:not(:focus-visible) {
  outline: none;
  box-shadow: none;
}

.edt-detail__etiquettes {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--seance-couleur);
}

.edt-detail__pastille {
  padding: 0 0.5rem;
  border-radius: 999px;
  font-size: 0.75rem;
  line-height: 1.375rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  color: var(--seance-pastille-texte);
  background: var(--seance-pastille);
}

.edt-detail__titre {
  margin: 0.625rem 0 0;
  font-size: 1.25rem;
  font-weight: 500;
  line-height: 1.3;
}

.edt-detail .p-dialog-content {
  padding: 1.25rem 1.5rem 1.5rem;
}

.edt-detail__liste {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
  margin: 0;
  outline: none;
}

.edt-detail__liste > div {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.edt-detail__liste dt {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 10px;
  color: var(--seance-couleur);
  background: var(--seance-fond);
}

.edt-detail__liste dd {
  margin: 0;
}
</style>
