<script setup>
import {computed, onBeforeUnmount, ref} from 'vue';
import {evolutionUe, formatDate, formatNote} from '@/utils/scolarite.js';
import {animationEntree, pluginSeuil, pluginZones, policeGraphique, useThemeGraphique} from '@/composables/useThemeGraphique.js';
import GraphiqueAccessible from './GraphiqueAccessible.vue';
import PastilleNote from './PastilleNote.vue';

const props = defineProps({
  ues: {type: Array, required: true},
});

const {couleurs, animation} = useThemeGraphique();

// Sur téléphone, la légende ne garde que « UE 1.1 » : les libellés entiers l'étalaient sur trois
// lignes.
const ecranEtroit = window.matchMedia('(max-width: 639px)');
const etroit = ref(ecranEtroit.matches);
const suivreLargeur = (evenement) => {
  etroit.value = evenement.matches;
};
ecranEtroit.addEventListener('change', suivreLargeur);
onBeforeUnmount(() => ecranEtroit.removeEventListener('change', suivreLargeur));
const libelleCourt = (libelle) => libelle.match(/^UE\s+\S+/)?.[0] ?? libelle;

// Chaque UE a sa couleur, sa forme de point et son trait : on les distingue sans la couleur.
const STYLES = [
  {couleur: 'violet', point: 'circle', trait: []},
  {couleur: 'rose', point: 'rectRot', trait: [6, 4]},
  {couleur: 'vert', point: 'triangle', trait: [2, 3]},
  {couleur: 'ocre', point: 'rect', trait: [10, 3, 2, 3]},
  {couleur: 'gris', point: 'star', trait: [1, 4]},
];

const TOUTES = 'toutes';
const choix = ref(TOUTES);

const courbes = computed(() => props.ues
  .map((ue, index) => ({ue, style: STYLES[index % STYLES.length], points: evolutionUe(ue)}))
  .filter(courbe => courbe.points.length));
const affichees = computed(() => courbes.value.filter(courbe => choix.value === TOUTES || courbe.ue.libelle === choix.value));
const options = computed(() => [
  {label: 'Toutes les UE', value: TOUTES},
  ...courbes.value.map(courbe => ({label: courbe.ue.libelle, value: courbe.ue.libelle})),
]);
const dates = computed(() => [...new Set(affichees.value.flatMap(courbe => courbe.points.map(point => point.date)))].sort());
const jourMois = (date) => formatDate(date).slice(0, 5);
const dateLongue = (date) => new Date(date).toLocaleDateString('fr-FR', {weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC'});

// Les notes publiées à cette date dans les UE affichées ; une matière commune à deux UE ne compte
// qu'une fois.
const nouvellesDu = (date) => [...new Set(affichees.value.flatMap(courbe => (courbe.points.find(point => point.date === date)?.nouvelles ?? [])
  .map(note => `${note.matiere} · ${note.libelle}`)))];

const moyenneDu = (courbe, date) => courbe.points.find(point => point.date === date)?.moyenne ?? null;
const dateCourte = (date) => new Date(date).toLocaleDateString('fr-FR', {weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC'});

const resume = computed(() => affichees.value
  .map(courbe => {
    const premier = courbe.points[0];
    const dernier = courbe.points.at(-1);
    return `${courbe.ue.libelle} : de ${formatNote(premier.moyenne)} le ${formatDate(premier.date)} à ${formatNote(dernier.moyenne)} le ${formatDate(dernier.date)}`;
  })
  .join(' ; '));

const donnees = computed(() => ({
  labels: dates.value.map(jourMois),
  datasets: affichees.value.map(({ue, style, points}) => {
    const couleur = couleurs.value[style.couleur];
    return {
      label: etroit.value ? libelleCourt(ue.libelle) : ue.libelle,
      data: dates.value.map(date => points.find(point => point.date === date)?.moyenne ?? null),
      spanGaps: true,
      borderColor: couleur,
      backgroundColor: couleur,
      borderDash: style.trait,
      borderWidth: 2.5,
      pointStyle: style.point,
      pointRadius: 5,
      pointHoverRadius: 7,
      tension: 0.25,
    };
  }),
}));

const optionsGraphique = computed(() => ({
  maintainAspectRatio: false,
  animation: animationEntree(animation),
  layout: {padding: {right: 40}},
  interaction: {mode: 'index', intersect: false},
  scales: {
    y: {
      min: 0,
      max: 20,
      ticks: {stepSize: 5, color: couleurs.value.attenue, font: policeGraphique},
      grid: {display: false},
      border: {display: false},
    },
    x: {
      ticks: {color: couleurs.value.attenue, font: policeGraphique, maxRotation: 0, autoSkipPadding: 12},
      grid: {display: false},
      border: {display: false},
    },
  },
  plugins: {
    legend: {
      display: affichees.value.length > 1,
      position: 'bottom',
      onClick: null,
      labels: {usePointStyle: true, boxWidth: 9, boxHeight: 9, color: couleurs.value.texte, font: policeGraphique, padding: 18},
    },
    // La bulle d'une date dit quelles notes viennent d'être publiées : on comprend pourquoi la
    // courbe monte ou descend.
    tooltip: {
      filter: (element) => element.raw !== null,
      padding: 10,
      callbacks: {
        title: (elements) => dateLongue(dates.value[elements[0].dataIndex]),
        label: (element) => `${element.dataset.label} : ${formatNote(element.raw)}`,
        footer: (elements) => {
          const nouvelles = nouvellesDu(dates.value[elements[0].dataIndex]);
          return nouvelles.length ? ['Nouvelle note :', ...nouvelles] : [];
        },
      },
    },
  },
}));

// La dernière moyenne écrite au bout de chaque courbe : la valeur d'aujourd'hui se lit sans survoler.
const valeursFinales = computed(() => ({
  id: 'valeursFinales',
  afterDatasetsDraw(chart) {
    const {ctx} = chart;
    ctx.save();
    ctx.font = '600 14px Roboto, sans-serif';
    ctx.textBaseline = 'middle';
    chart.data.datasets.forEach((dataset, index) => {
      const dernier = dataset.data.findLastIndex(valeur => valeur !== null);
      const point = chart.getDatasetMeta(index).data[dernier];
      if (!point) {
        return;
      }
      ctx.fillStyle = dataset.borderColor;
      ctx.fillText(formatNote(dataset.data[dernier]), point.x + 9, point.y);
    });
    ctx.restore();
  },
}));

const plugins = computed(() => [pluginZones('y', couleurs.value), pluginSeuil('y', couleurs.value.texte), valeursFinales.value]);
</script>

<template>
  <GraphiqueAccessible v-if="courbes.length" :rang="5" titre="Évolution des moyennes" icone="pi pi-chart-line" teinte="sae" :resume="resume">
    <!-- Les UE en pilules sur grand écran ; sur téléphone, où elles s'étalaient sur deux lignes, une
         liste déroulante. Une seule des deux est affichée. -->
    <template v-if="courbes.length > 1" #filtres>
      <SelectButton
          v-model="choix"
          :options="options"
          option-label="label"
          option-value="value"
          :allow-empty="false"
          aria-label="UE affichées"
          class="filtre"
      />
      <Select
          v-model="choix"
          :options="options"
          option-label="label"
          option-value="value"
          aria-label="UE affichées"
          class="filtre-liste"
      />
    </template>
    <Chart type="line" :data="donnees" :options="optionsGraphique" :plugins="plugins" class="evolution"/>
    <p class="aide">
      <span class="texte-long">Moyenne de l'UE après chaque note publiée. Pointillé : seuil de validation, 10. Chaque point montre la note qui l'a fait bouger, au survol ou dans le tableau.</span>
      <span class="texte-court">Moyenne après chaque note. Le détail est dans le tableau.</span>
    </p>
    <template #tableau>
      <table>
        <caption class="sr-only">Évolution des moyennes</caption>
        <thead>
          <tr>
            <th scope="col">Date</th>
            <th v-for="courbe in affichees" :key="courbe.ue.libelle" scope="col" class="nombre">{{ courbe.ue.libelle }}</th>
            <th scope="col">Nouvelle note</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="date in dates" :key="date">
            <th scope="row"><time :datetime="date" class="date">{{ dateCourte(date) }}</time></th>
            <td v-for="courbe in affichees" :key="courbe.ue.libelle" class="nombre">
              <PastilleNote v-if="moyenneDu(courbe, date) !== null" :ton="moyenneDu(courbe, date) >= 10 ? 'bonne' : 'faible'" petite>{{ formatNote(moyenneDu(courbe, date)) }}</PastilleNote>
              <span v-else class="vide">–</span>
            </td>
            <td>
              <span class="nouvelles">
                <span v-for="note in nouvellesDu(date)" :key="note" class="nouvelle">{{ note }}</span>
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </GraphiqueAccessible>
</template>

<style scoped>
.evolution {
  height: 20rem;
}

/* Les UE en pilule, comme Jour et Semaine dans l'agenda. */
.filtre {
  flex-wrap: wrap;
  align-self: flex-start;
  gap: 0.25rem;
  padding: 0.25rem;
  border-radius: 24px;
  background: var(--p-content-hover-background);
}

.filtre :deep(.p-togglebutton),
.filtre :deep(.p-togglebutton-content) {
  border: none;
  border-radius: 999px !important;
}

.filtre :deep(.p-togglebutton) {
  min-height: 44px;
  padding: 0.25rem;
  background: transparent;
}

.filtre :deep(.p-togglebutton-checked .p-togglebutton-content) {
  background: var(--p-pastille-active-background);
  color: var(--p-pastille-active-color);
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.12);
}

.filtre :deep(.p-togglebutton:focus-visible) {
  outline: 2px solid var(--p-primary-color);
  outline-offset: -2px;
  box-shadow: none;
}

.filtre-liste {
  display: none;
  min-height: 48px;
  align-items: center;
  border: none;
  border-radius: 999px;
  background: var(--p-content-hover-background);
  box-shadow: none;
}

.filtre-liste :deep(.p-select-label) {
  padding-left: 1rem;
  font-weight: 600;
}

.filtre-liste:focus-within {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

@media (max-width: 639px) {
  .filtre {
    display: none;
  }

  .filtre-liste {
    display: flex;
  }
}

.date {
  white-space: nowrap;
}

.vide {
  color: var(--p-text-muted-color);
}

.nouvelles {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.nouvelle {
  padding: 0.125rem 0.625rem;
  border-radius: 999px;
  font-size: 1rem;
  white-space: nowrap;
  background: var(--p-seance-sae-background);
  color: var(--p-seance-sae-color);
}

.aide {
  margin: 0.75rem 0 0;
  font-size: 1rem;
  color: var(--p-text-muted-color);
}
/* Sur téléphone, les explications passent en version courte : une seule des deux est affichée, et
   donc lue par un lecteur d'écran. */
.texte-court {
  display: none;
}

@media (max-width: 639px) {
  .texte-long {
    display: none;
  }

  .texte-court {
    display: inline;
  }
}
</style>
