<script setup>
import { VueCal } from 'vue-cal'
import 'vue-cal/style'

import {computed, nextTick, onBeforeUnmount, onMounted, ref, watch} from 'vue'
import {onBeforeRouteLeave, useRoute, useRouter} from 'vue-router'
import CoursCarte from './CoursCarte.vue'
import SelecteurSemaine from './SelecteurSemaine.vue'
import VacancesConfettis from './VacancesConfettis.vue'
import {getMesSemainesService, getMonEmploiDuTempsService} from "@requests";
import {getISOWeekNumber} from "@helpers/date";
import {adresseAgenda, annonceAgenda, joursDeLaSemaine, libelleCours, libelleSemaine, lireAdresseAgenda, titrePeriode, typeSeance} from '@/utils/agenda.js';
import {formatDelai, minutesEntre, parseEdtDate} from '@/utils/prochainCours.js';

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
const semaines = ref([]);
const options = ref(null);
// L'abonnement iCal viendra ici : l'entrée annonce sa place sans rien promettre de cliquable.
const menuOptions = [
  {label: 'Synchroniser avec mon agenda (iCal)', icon: 'pi pi-link', disabled: true, bientot: true},
];
const hauteurHeure = ref(56);
// Position de la grille dans le cadre : vue-cal remplace son corps à chaque semaine, la carte des
// vacances se pose donc sur le cadre, à l'emplacement mesuré de la grille.
const zoneGrille = ref({});
const maintenant = ref(new Date());
const horloge = setInterval(() => { maintenant.value = new Date(); }, 60000);

const route = useRoute();
const router = useRouter();
const adresse = lireAdresseAgenda(route.query);

const vues = [
  { id: 'day', libelle: 'Jour' },
  { id: 'week', libelle: 'Semaine' },
];
// Le choix Jour / Semaine survit à la visite suivante. Le stockage peut être refusé (navigation
// privée) : la semaine reste alors l'ouverture par défaut.
const CLE_VUE = 'agenda.vue';
const vueRetenue = () => {
  try {
    return vues.some(({id}) => id === localStorage.getItem(CLE_VUE)) ? localStorage.getItem(CLE_VUE) : 'week';
  } catch {
    return 'week';
  }
};
const retenirVue = (vue) => {
  try {
    localStorage.setItem(CLE_VUE, vue);
  } catch {
    // Sans stockage, le choix ne vaut que pour cette visite.
  }
};
// Cinq colonnes ne tiennent pas sur un téléphone tenu en long : il ouvre la journée. Tourné en
// large, il retrouve la vue choisie.
// Tenu en long, il n'a pas la place d'une bascule : la vue suit l'orientation seule.
const telephoneEnLong = window.matchMedia('(max-width: 767px) and (orientation: portrait)');
const enLong = ref(telephoneEnLong.matches);
const vueAffichee = ref(enLong.value ? 'day' : (adresse.vue ?? vueRetenue()));
const suivreOrientation = (evenement) => {
  enLong.value = evenement.matches;
  vueAffichee.value = evenement.matches ? 'day' : vueRetenue();
};
const choisirVue = (vue) => {
  vueAffichee.value = vue;
  retenirVue(vue);
};
// Date d'ouverture de vue-cal : celle de l'adresse, puis celle qu'on réessaie de charger.
const dateDeDepart = ref(adresse.date ?? new Date());

const minutes = (heure) => {
  const [h, m] = heure.split(':').map(Number);
  return h * 60 + m;
};

const isoDate = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');

const lundiDe = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate() - ((date.getDay() + 6) % 7));

const estAujourdhui = (date) => isoDate(new Date(date)) === isoDate(maintenant.value);

// Le bouton « Aujourd'hui » ne sert que si aujourd'hui n'est pas déjà à l'écran.
// Sur un téléphone tenu en long, « Aujourd'hui » flotte en bas de l'écran, hors de la barre.
const boutonFlottant = computed(() => {
  const view = vuecalRef.value?.view;
  return enLong.value && Boolean(view?.firstCellDate) && !aujourdhuiAffiche(view);
});

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
  nextTick(mesurerGrille);
};

const mesurerGrille = () => {
  const corps = cadre.value?.querySelector('.vuecal__body');
  if (!corps) {
    return;
  }
  const origine = cadre.value.getBoundingClientRect();
  const grille = corps.getBoundingClientRect();
  zoneGrille.value = {
    top: `${grille.top - origine.top}px`,
    left: `${grille.left - origine.left}px`,
    width: `${grille.width}px`,
    height: `${grille.height}px`,
  };
};

let redimensionnement = null;
const surRedimensionnement = () => {
  cancelAnimationFrame(redimensionnement);
  redimensionnement = requestAnimationFrame(ajusterHauteur);
};
// La charge de chaque semaine de l'année, pour le sélecteur : une seule lecture. Sans elle, le
// titre reste un simple titre.
const chargerSemaines = async () => {
  try {
    semaines.value = (await getMesSemainesService()).semaines;
  } catch (error) {
    semaines.value = [];
  }
};

// Une semaine sans cours, entre deux semaines de cours de l'année : ce sont les vacances.
const vacances = computed(() => events.value.length === 0
  && semaines.value.some(semaine => semaine.lundi === semaineChargee.value && semaine.minutes === 0));

const choisirSemaine = (lundi) => {
  const [annee, mois, jour] = lundi.split('-').map(Number);
  vuecalRef.value?.view.updateViewDate(new Date(annee, mois - 1, jour));
};

onMounted(() => {
  chargerSemaines();
  window.addEventListener('resize', surRedimensionnement);
  telephoneEnLong.addEventListener('change', suivreOrientation);
});
onBeforeUnmount(() => {
  clearInterval(horloge);
  window.removeEventListener('resize', surRedimensionnement);
  telephoneEnLong.removeEventListener('change', suivreOrientation);
});

// Une semaine déjà vue ne se recharge pas, et la suivante est lue d'avance : la flèche « Semaine
// suivante » répond sans attendre le réseau. Une lecture en échec sort du cache pour être retentée.
const cacheSemaines = new Map();
const lireSemaine = (semaine, enArrierePlan = false) => {
  if (!cacheSemaines.has(semaine)) {
    cacheSemaines.set(semaine, getMonEmploiDuTempsService(semaine, enArrierePlan).catch((erreur) => {
      cacheSemaines.delete(semaine);
      throw erreur;
    }));
  }
  return cacheSemaines.get(semaine);
};
const prechargerSemaine = (lundi) => {
  lireSemaine(isoDate(new Date(lundi.getFullYear(), lundi.getMonth(), lundi.getDate() + 7)), true).catch(() => {});
};

const chargerSemaine = async (lundi) => {
  const semaine = isoDate(lundi);
  if (semaine === semaineChargee.value) {
    return;
  }
  try {
    const reponse = await lireSemaine(semaine);
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
    prechargerSemaine(lundi);
    await nextTick();
    ajusterHauteur();
  } catch (error) {
    dateDeDepart.value = vuecalRef.value?.view?.firstCellDate ?? lundi;
    hasError.value = true;
  }
};

const reessayer = () => {
  hasError.value = false;
};

watch(() => vuecalRef.value?.view?.start, async (debut) => {
  if (debut) {
    noterAdresse();
    await chargerSemaine(lundiDe(new Date(debut)));
    annoncerPeriode();
  }
}, { immediate: true });

watch(vueAffichee, () => nextTick(noterAdresse));

// La période affichée vit dans l'adresse : rechargement, retour depuis une autre page et lien
// partagé rouvrent la même semaine. Aujourd'hui n'y est pas écrit, pour qu'un favori de l'agenda
// ouvre toujours la semaine en cours. Remplacer plutôt qu'empiler : chaque clic sur une flèche
// n'ajoute pas une page à l'historique.
// Une fois la page quittée, réécrire son adresse annulerait la navigation en cours : un clic dans
// le menu pendant le chargement de la semaine laisserait l'étudiant sur l'agenda.
let quittee = false;
onBeforeRouteLeave(() => {
  quittee = true;
});
const noterAdresse = () => {
  const view = vuecalRef.value?.view;
  if (quittee || !view?.firstCellDate) {
    return;
  }
  router.replace({
    query: adresseAgenda({
      date: aujourdhuiAffiche(view) ? null : isoDate(view.firstCellDate),
      vue: enLong.value ? null : vueAffichee.value,
    }),
  });
};

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

// Le trait d'une heure occupe le premier pixel de sa case : 4 px au-dessus d'une carte et 3 px
// au-dessous laissent le même vide entre la carte et les deux traits.
const hauteurCarte = (cours) => ((cours.end - cours.start) / 3600000) * hauteurHeure.value - 7;

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
        <dd>{{ detail.horaire }} · {{ detail.duree }}</dd>
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

  <Transition name="edt-flottant">
    <Button
        v-if="boutonFlottant"
        icon="pi pi-calendar"
        rounded
        aria-label="Aujourd'hui"
        class="edt-flottant"
        @click="vuecalRef.view.goToToday()"
    />
  </Transition>

  <div v-if="hasError" class="edt-erreur" role="alert">
    <i class="pi pi-exclamation-triangle edt-erreur__icone" aria-hidden="true"></i>
    <p class="edt-erreur__texte">L'emploi du temps n'a pas pu être chargé.</p>
    <Button label="Réessayer" icon="pi pi-refresh" class="min-h-[44px]!" @click="reessayer" />
  </div>
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
        :view-date="dateDeDepart"
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
            <h2 class="edt-barre__titre">
              <SelecteurSemaine
                  v-if="semaines.length"
                  :titre="titrePeriode({ vue: view.id, debut: view.firstCellDate, fin: view.lastCellDate })"
                  :sous-titre="libelleSemaine(getISOWeekNumber(view.firstCellDate), semaineFormation)"
                  :semaines="semaines"
                  :lundi-affiche="semaineChargee"
                  :lundi-courant="isoDate(lundiDe(maintenant))"
                  @choisir="choisirSemaine"
              />
              <span v-else class="edt-barre__periode-fixe">
                <span class="edt-barre__titre-texte">{{ titrePeriode({ vue: view.id, debut: view.firstCellDate, fin: view.lastCellDate }) }}</span>
                <span class="edt-barre__semaine">{{ libelleSemaine(getISOWeekNumber(view.firstCellDate), semaineFormation) }}</span>
              </span>
            </h2>
          </div>

          <!-- Alignées à droite : la bulle « Aujourd'hui » naît à gauche de Jour / Semaine sans
               déplacer ni les flèches ni la bascule. -->
          <div class="edt-barre__actions">
            <Transition name="edt-bulle">
              <Button
                  v-if="!enLong && !aujourdhuiAffiche(view)"
                  rounded
                  aria-label="Aujourd'hui"
                  class="edt-barre__aujourdhui min-w-[44px]! min-h-[44px]!"
                  @click="view.goToToday()"
              >
                <i class="pi pi-calendar md:hidden" aria-hidden="true"></i>
                <span class="hidden md:inline" aria-hidden="true">Aujourd'hui</span>
              </Button>
            </Transition>
            <SelectButton
                v-if="!enLong"
                :model-value="vueAffichee"
                @update:model-value="choisirVue"
                :options="vues"
                option-label="libelle"
                option-value="id"
                :allow-empty="false"
                aria-label="Affichage"
            />
            <Button
                icon="pi pi-ellipsis-v"
                text
                rounded
                aria-label="Plus d'options"
                aria-haspopup="true"
                aria-controls="edt-options"
                class="edt-barre__options min-w-[44px]! min-h-[44px]!"
                @click="options.toggle($event)"
            />
            <Menu id="edt-options" ref="options" :model="menuOptions" popup class="edt-options">
              <template #item="{ item }">
                <span class="edt-options__entree">
                  <i :class="item.icon" aria-hidden="true"></i>
                  <span>{{ item.label }}</span>
                  <span v-if="item.bientot" class="edt-options__bientot">Bientôt</span>
                </span>
              </template>
            </Menu>
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
    <VacancesConfettis v-if="vacances" :lundi="semaineChargee" :style="zoneGrille" />
  </div>
</template>

<style scoped>
@reference "../../assets/tailwind.css";

/* Le cadre .card de la page n'a pas de marge intérieure : l'agenda prend la sienne. */
.edt-erreur {
  @apply flex flex-col items-center gap-4 px-6 py-16 text-center;
}

.edt-erreur__icone {
  @apply text-3xl! text-muted-color;
}

.edt-erreur__texte {
  @apply m-0 text-color;
}

.edt-cadre {
  @apply relative px-3 pt-3 pb-1 md:px-6 md:pt-5;
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

/* La cible reste de 44 px, mais le survol et le focus ne dessinent que le rond de 32 px du centre,
   aligné sur le bord droit de la grille. */
.edt-barre__options {
  margin-right: -6px;
  padding: 6px !important;
  background-clip: content-box !important;
}

/* Le menu n'a encore que des entrées « Bientôt » : sur téléphone, il ferait déborder la barre. */
@media (max-width: 767px) {
  .edt-barre__options {
    display: none !important;
  }
}

.edt-barre__options:focus-visible {
  outline-offset: -6px;
}

/* Pas d'effet au clic : l'appui garde le rond du survol. */
.edt-barre__options :deep(.p-ink) {
  display: none;
}

.edt-barre__options:active {
  background: var(--p-button-text-primary-hover-background) !important;
  background-clip: content-box !important;
}

.edt-barre__aujourdhui {
  @apply text-sm font-semibold md:px-4! bg-emphasis! text-primary! border-0!;
}

/* La bulle sort de la pilule des flèches en rebondissant, et y rentre. */
.edt-bulle-enter-active {
  transition: opacity 200ms ease, transform 350ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.edt-bulle-leave-active {
  transition: opacity 300ms ease, transform 400ms ease-in;
}

.edt-bulle-enter-from,
.edt-bulle-leave-to {
  opacity: 0;
  transform: translateX(1.5rem) scale(0.4);
}

.edt-barre__aujourdhui {
  transform-origin: right center;
}

/* Le bouton flottant du téléphone, rond comme les cercles du logo, sort du bas de l'écran. En
   violet : le jaune seul ne fait que 1,9:1 sur le fond de la page, l'icône blanche 9,95:1. */
.edt-flottant {
  @apply fixed! z-20 w-14! h-14! shadow-lg;
  right: 1.25rem;
  bottom: 1.25rem;
}

.edt-flottant-enter-active {
  transition: opacity 200ms ease, transform 350ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.edt-flottant-leave-active {
  transition: opacity 300ms ease, transform 400ms ease-in;
}

.edt-flottant-enter-from,
.edt-flottant-leave-to {
  opacity: 0;
  transform: translateY(1.5rem) scale(0.4);
}

@media (prefers-reduced-motion: reduce) {
  .edt-flottant-enter-active,
  .edt-flottant-leave-active,
  .edt-bulle-enter-active,
  .edt-bulle-leave-active {
    transition: opacity 150ms ease;
  }

  .edt-flottant-enter-from,
  .edt-flottant-leave-to,
  .edt-bulle-enter-from,
  .edt-bulle-leave-to {
    transform: none;
  }
}

/* Jour et Semaine en pilule, comme les flèches et les formes du logo. */
.edt-barre__actions :deep(.p-selectbutton) {
  @apply rounded-full bg-emphasis p-1 gap-1;
}

.edt-barre__actions :deep(.p-togglebutton),
.edt-barre__actions :deep(.p-togglebutton-content) {
  @apply rounded-full! border-0;
}

.edt-barre__titre {
  @apply m-0 min-w-0;
}

.edt-barre__periode-fixe {
  @apply flex flex-col;
}

.edt-barre__titre-texte {
  @apply text-lg md:text-xl font-bold leading-tight;
}

.edt-barre__semaine {
  @apply mt-0.5 text-sm font-normal text-muted-color;
}

.edt-barre__actions {
  @apply flex items-center justify-end gap-2 ml-auto;
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
}

/* Chaque heure s'écrit sur sa ligne. vue-cal n'écrit que le début des cases : la fin de la plage
   s'ajoute sous la dernière, au même endroit que les autres heures. */
:deep(.vuecal__time-cell label),
:deep(.vuecal__time-cell:last-child)::after {
  @apply absolute right-3 -translate-y-1/2 leading-none;
}

/* vue-cal donne 8 px de marge à ses libellés, pas à celui de fin : les deux s'alignent sans. */
:deep(.vuecal__time-cell label) {
  @apply top-0 pr-0;
}

:deep(.vuecal__time-cell:last-child)::after {
  top: 100%;
  content: var(--libelle-fin);
}

/* Un trait à chaque heure, un trait bien plus léger à la demi-heure, sans libellé. Un trait fin
   sépare les jours, pour suivre une colonne de haut en bas. */
:deep(.vuecal__cell) {
  background-image:
      linear-gradient(to bottom, var(--p-content-border-color) 1px, transparent 1px),
      linear-gradient(to bottom, transparent calc(50% - 0.5px), color-mix(in srgb, var(--p-content-border-color) 35%, transparent) calc(50% - 0.5px), color-mix(in srgb, var(--p-content-border-color) 35%, transparent) calc(50% + 0.5px), transparent calc(50% + 0.5px));
  background-size: 100% var(--hauteur-heure);
}

:deep(.vuecal__cell + .vuecal__cell) {
  border-left: 1px solid color-mix(in srgb, var(--p-content-border-color) 55%, transparent);
}

:deep(.vuecal__body) {
  @apply relative;
  border-bottom: 1px solid var(--p-content-border-color);
}

:deep(.vuecal__cell--today) {
  background-color: color-mix(in srgb, var(--p-primary-color) 5%, transparent);
}

/* Une gouttière autour de chaque cours : deux cours voisins ne se touchent jamais. */
:deep(.vuecal__event) {
  @apply bg-transparent;
  padding: 4px 4px 3px;
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
  inset: 4px 4px 3px;
  border-radius: 18px;
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
/* Le menu des options est téléporté : mêmes formes arrondies que le reste de l'agenda. */
.edt-options.p-menu {
  border: 0;
  border-radius: 20px;
  padding: 0.375rem;
  box-shadow: 0 16px 40px -16px rgb(0 0 0 / 0.35);
}

.edt-options .p-menu-item-content {
  border-radius: 999px;
}

.edt-options__entree {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-height: 44px;
  padding: 0 0.875rem;
  font-size: 0.875rem;
}

.edt-options__bientot {
  margin-left: 0.5rem;
  padding: 0 0.5rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
}

/* Le détail d'un cours est téléporté hors du composant : ses styles ne peuvent pas être scopés.
   Il reprend la carte : fond de la couleur du type, cercle dans l'angle, pastille. */
.edt-detail.p-dialog {
  border: 0;
  border-radius: 28px;
  overflow: hidden;
  background:
      radial-gradient(circle 7rem at calc(100% - 1.5rem) calc(100% - 1.5rem), color-mix(in srgb, var(--seance-pastille) 14%, var(--seance-fond)) calc(100% - 0.5px), transparent 100%),
      var(--seance-fond);
}

.edt-detail .p-dialog-header,
.edt-detail .p-dialog-content {
  background: transparent;
}

.edt-detail .p-dialog-header {
  align-items: flex-start;
  padding: 1.5rem 1.25rem 0.5rem 1.75rem;
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
  padding: 0 0.625rem;
  border-radius: 999px;
  font-size: 0.75rem;
  line-height: 1.5rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  color: var(--seance-pastille-texte);
  background: var(--seance-pastille);
}

.edt-detail__titre {
  margin: 0.75rem 0 0;
  font-size: 1.25rem;
  font-weight: 500;
  line-height: 1.3;
}

.edt-detail .p-dialog-content {
  padding: 0.75rem 1.75rem 1.75rem;
}

.edt-detail__liste {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
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
  border-radius: 999px;
  color: var(--seance-couleur);
  background: var(--p-content-background);
}

.edt-detail__liste dd {
  margin: 0;
}
</style>
