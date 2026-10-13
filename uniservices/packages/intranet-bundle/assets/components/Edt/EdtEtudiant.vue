<script setup>
import { VueCal } from 'vue-cal'
import 'vue-cal/style'

import {computed, onBeforeUnmount, ref, watch} from 'vue'
import CoursCarte from './CoursCarte.vue'
import {getMonEmploiDuTempsService} from "@requests";
import {getISOWeekNumber} from "@helpers/date";
import {annonceAgenda, joursDeLaSemaine, libelleCours, libelleSemaine, titrePeriode, typeSeance} from '@/utils/agenda.js';
import {parseEdtDate} from '@/utils/prochainCours.js';
import {ErrorView} from "@components";

// Une heure de cours fait toujours 64 px : 1 h 30 tient en carte complète, 45 min sur une ligne.
const HAUTEUR_HEURE = 64;

const vuecalRef = ref(null)
const hasError = ref(false);
const annonce = ref('');
const semaineChargee = ref(null);
const semaineFormation = ref(null);
// La plage horaire de l'année de l'étudiant, jamais celle de la semaine : la grille ne change
// pas de hauteur d'une semaine à l'autre.
const plage = ref({debut: '08:00', fin: '20:00'});
const events = ref([]);
const maintenant = ref(new Date());
const horloge = setInterval(() => { maintenant.value = new Date(); }, 60000);
onBeforeUnmount(() => clearInterval(horloge));

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

// Samedi et dimanche ne s'affichent que la semaine où ils portent un cours.
const joursMasques = computed(() => {
  if (!semaineChargee.value) {
    return ['sat', 'sun'];
  }
  const jours = joursDeLaSemaine(new Date(`${semaineChargee.value}T00:00:00`), events.value).map(jour => jour.getDay());
  return [[6, 'sat'], [0, 'sun']].filter(([jour]) => !jours.includes(jour)).map(([, nom]) => nom);
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
      class: `seance-${typeSeance(cours)}`,
    }));
    semaineChargee.value = semaine;
    hasError.value = false;
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

const hauteur = (cours) => ((cours.end - cours.start) / 3600000) * HAUTEUR_HEURE;

const selectedEvent = ref(null)
const visible = ref(false)

const openDialog = ({ event }) => {
  selectedEvent.value = event
  visible.value = true
}
</script>

<template>

  <Dialog v-model:visible="visible" :header="selectedEvent?.title" class="!border-2 !border-primary-500" :style="{ width: '25vw' }" :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
    <div class="flex flex-col gap-2">
      <div>
        {{selectedEvent.start.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}} •
        {{selectedEvent.start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}} -
        {{selectedEvent.end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}}
      </div>
      <div class="flex items-center gap-2">
        <Badge v-if="selectedEvent.evaluation" severity="danger" class="uppercase">Évaluation</Badge>
        <span class="detail-type" :style="{ color: `var(--p-seance-${typeSeance(selectedEvent)}-color)` }">{{ selectedEvent.type }}</span>
      </div>
      <div class="flex flex-col gap-1">
        <div v-if="selectedEvent.groupe">
          <strong>Groupe :</strong> {{ selectedEvent.groupe }}
        </div>
        <div v-if="selectedEvent.location">
          <strong>Salle :</strong> {{ selectedEvent.location }}
        </div>
        <div>
          <strong>Intervenant :</strong> {{ selectedEvent.enseignant || 'Inconnu' }}
        </div>
      </div>
    </div>
  </Dialog>

  <p role="status" class="sr-only">{{ annonce }}</p>

  <ErrorView v-if="hasError" message="Une erreur est survenue lors du chargement de l'emploi du temps. Veuillez réessayer plus tard." />
  <vue-cal
      v-else
      ref="vuecalRef"
      locale="fr"
      :hide-weekdays="joursMasques"
      time
      :time-from="minutes(plage.debut)"
      :time-to="minutes(plage.fin)"
      :time-step="60"
      :time-cell-height="HAUTEUR_HEURE"
      :views="['day', 'week']"
      v-model:view="vueAffichee"
      :theme="false"
      diy
      :events="events"
      :style="{ '--hauteur-heure': `${HAUTEUR_HEURE}px` }"
      @event-click="openDialog">

    <!-- En-tête personnalisé -->
    <template #header="{ view }">
      <div class="p-3 md:p-6 flex flex-col gap-3 md:gap-4">
        <div class="flex justify-between md:justify-center items-center gap-2 md:gap-12">
          <Button
              icon="pi pi-chevron-circle-left"
              :aria-label="view.id === 'day' ? 'Jour précédent' : 'Semaine précédente'"
              @click="view.previous"
              class="p-button-text min-w-[44px]! min-h-[44px]!"
          />
          <div class="flex flex-col items-center text-center">
            <h2 class="text-lg! md:text-xl! font-bold m-0!">{{ titrePeriode({ vue: view.id, debut: view.firstCellDate, fin: view.lastCellDate }) }}</h2>
            <span class="text-sm md:text-base text-muted-color">{{ libelleSemaine(getISOWeekNumber(view.firstCellDate), semaineFormation) }}</span>
          </div>
          <Button
              icon="pi pi-chevron-circle-right"
              :aria-label="view.id === 'day' ? 'Jour suivant' : 'Semaine suivante'"
              @click="view.next"
              class="p-button-text min-w-[44px]! min-h-[44px]!"
          />
        </div>

        <div class="flex flex-wrap justify-between items-center gap-2">
          <SelectButton
              v-model="vueAffichee"
              :options="vues"
              option-label="libelle"
              option-value="id"
              :allow-empty="false"
              aria-label="Affichage"
          />
          <Button label="Aujourd'hui" outlined class="min-h-[44px]" @click="view.goToToday()" />
        </div>
      </div>
    </template>

    <template #weekday-heading="{ label, id, date }">
      <div :class="id">{{ label }}</div>
      <strong>{{ new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) }}</strong>
    </template>

    <template #event="{ event }">
      <CoursCarte :cours="event" :hauteur="hauteur(event)" :maintenant="maintenant" />
      <!-- Sans gestionnaire propre : son clic remonte jusqu'à vue-cal, qui émet event-click. La souris
           et le clavier ouvrent ainsi le détail par le même chemin. -->
      <button type="button" class="edt-cours" :aria-label="libelleCours(event)"></button>
    </template>
  </vue-cal>
</template>

<style scoped>
@reference "../../assets/tailwind.css";

:deep(.vuecal__event) {
  @apply p-0 bg-transparent;
  /* Un cours qui se termine pile sur l'heure ne doit pas toucher le suivant. */
  padding: 1px;
  &:hover .cours-carte {
    box-shadow: 0 0 0 2px var(--p-primary-color);
  }
}

:deep(.vuecal__event-details) {
  @apply h-full;
}

.edt-cours {
  @apply absolute inset-0 cursor-pointer;
  border-radius: 6px;
  &:focus-visible {
    outline: 3px solid var(--p-primary-color);
    outline-offset: -3px;
  }
}

.detail-type {
  @apply font-bold uppercase;
}

/* Une ligne à chaque heure, une ligne pointillée à la demi-heure. */
:deep(.vuecal__cell) {
  @apply border-l border-surface;
  background-image:
      linear-gradient(to bottom, var(--p-content-border-color) 1px, transparent 1px),
      linear-gradient(to bottom, transparent calc(50% - 0.5px), color-mix(in srgb, var(--p-content-border-color) 50%, transparent) calc(50% - 0.5px), color-mix(in srgb, var(--p-content-border-color) 50%, transparent) calc(50% + 0.5px), transparent calc(50% + 0.5px));
  background-size: 100% var(--hauteur-heure);
}

:deep(.vuecal__cell--today) {
  background-color: color-mix(in srgb, var(--p-primary-color) 6%, transparent);
}

:deep(.vuecal__weekday) {
  @apply py-3 flex flex-col items-center uppercase text-sm;
}

:deep(.vuecal__weekday--today) {
  @apply text-primary;
}

:deep(.vuecal__time-cell) {
  @apply text-muted-color text-xs pr-2 text-right;
}

:deep(.vuecal__now-line) {
  @apply border-primary;
  border-top-width: 2px;
  color: var(--p-primary-color);
  /* Son heure se posait sur le texte du cours en cours ; la carte dit déjà « En cours ». */
  span {
    @apply hidden;
  }
}

:deep(.vuecal__scrollable--day-view .vuecal__time-column) {
  @apply p-0;
}
</style>
