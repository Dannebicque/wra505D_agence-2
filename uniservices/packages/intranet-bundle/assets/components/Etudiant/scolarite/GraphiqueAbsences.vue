<script setup>
import {computed} from 'vue';
import {absencesParMatiere} from '@/utils/scolarite.js';
import {hachures, policeGraphique, useThemeGraphique} from '@/composables/useThemeGraphique.js';
import GraphiqueAccessible from './GraphiqueAccessible.vue';

const props = defineProps({
  absences: {type: Array, required: true},
});

const {couleurs, animation} = useThemeGraphique();

// Le rouge, hachuré, pour ce qui demande une action : lisible aussi sans la couleur.
const ETATS = [
  {cle: 'aJustifier', libelle: 'À justifier', resume: () => 'à justifier', couleur: 'rouge', hachure: true},
  {cle: 'enAttente', libelle: 'En attente', resume: () => 'en attente', couleur: 'ocre', hachure: false},
  {cle: 'justifiees', libelle: 'Justifiées', resume: (nombre) => (nombre > 1 ? 'justifiées' : 'justifiée'), couleur: 'vert', hachure: false},
];

const matieres = computed(() => absencesParMatiere(props.absences));

const resume = computed(() => matieres.value
  .map(matiere => `${matiere.matiere} : ${ETATS.filter(etat => matiere[etat.cle]).map(etat => `${matiere[etat.cle]} ${etat.resume(matiere[etat.cle])}`).join(', ')}`)
  .join(' ; '));

const donnees = computed(() => ({
  labels: matieres.value.map(matiere => matiere.matiere),
  datasets: ETATS.map(etat => {
    const couleur = couleurs.value[etat.couleur];
    return {
      label: etat.libelle,
      data: matieres.value.map(matiere => matiere[etat.cle]),
      backgroundColor: etat.hachure ? hachures(couleur) : couleur,
      borderColor: couleur,
      borderWidth: 1,
      borderRadius: 6,
      borderSkipped: false,
      maxBarThickness: 22,
    };
  }),
}));

// Une échelle d'au moins quatre absences : une absence isolée ne remplit pas toute la largeur.
const maximum = computed(() => Math.max(4, ...matieres.value.map(matiere => matiere.aJustifier + matiere.enAttente + matiere.justifiees)));

const options = computed(() => ({
  indexAxis: 'y',
  maintainAspectRatio: false,
  animation: animation ? {duration: 400} : false,
  scales: {
    x: {
      stacked: true,
      min: 0,
      suggestedMax: maximum.value,
      ticks: {stepSize: 1, precision: 0, color: couleurs.value.attenue, font: policeGraphique},
      grid: {color: couleurs.value.grille},
      border: {display: false},
    },
    y: {
      stacked: true,
      ticks: {color: couleurs.value.texte, font: policeGraphique},
      grid: {display: false},
      border: {display: false},
    },
  },
  plugins: {
    legend: {
      position: 'bottom',
      onClick: null,
      labels: {color: couleurs.value.texte, font: policeGraphique, boxWidth: 14, boxHeight: 14, padding: 16},
    },
    tooltip: {
      filter: (element) => element.raw > 0,
      callbacks: {label: (element) => `${element.dataset.label} : ${element.raw}`},
    },
  },
}));

const hauteur = computed(() => `${matieres.value.length * 2.75 + 5}rem`);
</script>

<template>
  <GraphiqueAccessible v-if="matieres.length" titre="Absences par matière" :resume="resume" :niveau="3">
    <Chart type="bar" :data="donnees" :options="options" :style="{height: hauteur}"/>
    <template #tableau>
      <table>
        <caption class="sr-only">Absences par matière</caption>
        <thead>
          <tr>
            <th scope="col">Matière</th>
            <th v-for="etat in ETATS" :key="etat.cle" scope="col" class="nombre">{{ etat.libelle }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="matiere in matieres" :key="matiere.matiere">
            <th scope="row">{{ matiere.matiere }}</th>
            <td v-for="etat in ETATS" :key="etat.cle" class="nombre">{{ matiere[etat.cle] }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </GraphiqueAccessible>
</template>
