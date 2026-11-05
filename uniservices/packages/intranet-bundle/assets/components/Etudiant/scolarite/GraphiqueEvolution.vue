<script setup>
import {computed, ref} from 'vue';
import {evolutionUe, formatDate, formatNote} from '@/utils/scolarite.js';
import {pluginSeuil, policeGraphique, useThemeGraphique} from '@/composables/useThemeGraphique.js';
import GraphiqueAccessible from './GraphiqueAccessible.vue';

const props = defineProps({
  ues: {type: Array, required: true},
});

const {couleurs, animation} = useThemeGraphique();

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
      label: ue.libelle,
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
  animation: animation ? {duration: 400} : false,
  interaction: {mode: 'nearest', intersect: false},
  scales: {
    y: {
      min: 0,
      max: 20,
      ticks: {stepSize: 5, color: couleurs.value.attenue, font: policeGraphique},
      grid: {color: couleurs.value.grille},
      border: {display: false},
    },
    x: {
      ticks: {color: couleurs.value.attenue, font: policeGraphique, maxRotation: 0, autoSkipPadding: 12},
      grid: {display: false},
      border: {color: couleurs.value.grille},
    },
  },
  plugins: {
    legend: {
      display: affichees.value.length > 1,
      position: 'bottom',
      onClick: null,
      labels: {usePointStyle: true, pointStyleWidth: 12, color: couleurs.value.texte, font: policeGraphique, padding: 16},
    },
    tooltip: {
      callbacks: {label: (element) => `${element.dataset.label} : ${formatNote(element.raw)}`},
    },
  },
}));

const plugins = computed(() => [pluginSeuil('y', couleurs.value.texte)]);
</script>

<template>
  <GraphiqueAccessible v-if="courbes.length" titre="Évolution des moyennes" :resume="resume">
    <template #filtres>
      <SelectButton
          v-if="courbes.length > 1"
          v-model="choix"
          :options="options"
          option-label="label"
          option-value="value"
          :allow-empty="false"
          aria-label="UE affichées"
          class="filtre"
      />
    </template>
    <Chart type="line" :data="donnees" :options="optionsGraphique" :plugins="plugins" class="evolution"/>
    <p class="aide">Moyenne de l'UE après chaque note publiée, pénalités d'absence comprises. Pointillé : seuil de validation, 10.</p>
    <template #tableau>
      <table>
        <caption class="sr-only">Évolution des moyennes</caption>
        <thead>
          <tr><th scope="col">Date</th><th v-for="courbe in affichees" :key="courbe.ue.libelle" scope="col" class="nombre">{{ courbe.ue.libelle }}</th></tr>
        </thead>
        <tbody>
          <tr v-for="date in dates" :key="date">
            <th scope="row"><time :datetime="date">{{ formatDate(date) }}</time></th>
            <td v-for="courbe in affichees" :key="courbe.ue.libelle" class="nombre">
              {{ formatNote(courbe.points.find(point => point.date === date)?.moyenne) }}
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </GraphiqueAccessible>
</template>

<style scoped>
.evolution {
  height: 18rem;
}

.filtre {
  flex-wrap: wrap;
}

.filtre :deep(.p-togglebutton) {
  min-height: 44px;
}

.aide {
  margin: 0.5rem 0 0;
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
}
</style>
