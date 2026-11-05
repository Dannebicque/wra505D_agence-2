<script setup>
import {computed} from 'vue';
import {formatNote, statutUe} from '@/utils/scolarite.js';
import {hachures, pluginSeuil, policeGraphique, useThemeGraphique} from '@/composables/useThemeGraphique.js';
import GraphiqueAccessible from './GraphiqueAccessible.vue';

const props = defineProps({
  ues: {type: Array, required: true},
});

const {couleurs, animation} = useThemeGraphique();

const perte = (ue) => (ue.moyenne !== null && ue.moyenneSansPenalite !== null && ue.moyenneSansPenalite > ue.moyenne
  ? Math.round((ue.moyenneSansPenalite - ue.moyenne) * 100) / 100
  : 0);
const couleurUe = (ue) => (ue.moyenne >= 10 ? couleurs.value.vert : couleurs.value.rouge);

const resume = computed(() => props.ues
  .map(ue => `${ue.libelle} : ${ue.moyenne === null ? 'pas encore de note' : `${formatNote(ue.moyenne)}, ${statutUe(ue).texte}`}`)
  .join(' ; '));

// « UE 1.1 » au-dessus de « Comprendre » : sur téléphone, le libellé entier ne tient pas à gauche.
const libelleSurDeuxLignes = (libelle) => {
  const morceaux = libelle.match(/^(UE\s+\S+)\s+(.+)$/);
  return morceaux ? [morceaux[1], morceaux[2]] : libelle;
};

const donnees = computed(() => ({
  labels: props.ues.map(ue => libelleSurDeuxLignes(ue.libelle)),
  datasets: [
    {
      label: 'Moyenne',
      data: props.ues.map(ue => ue.moyenne),
      backgroundColor: props.ues.map(couleurUe),
      borderRadius: 6,
      borderSkipped: false,
      barPercentage: 0.7,
    },
    {
      label: 'Perdu en absences',
      data: props.ues.map(perte),
      backgroundColor: hachures(couleurs.value.rouge),
      borderColor: couleurs.value.rouge,
      borderWidth: 1,
      borderRadius: 6,
      borderSkipped: false,
      barPercentage: 0.7,
    },
  ],
}));

const options = computed(() => ({
  indexAxis: 'y',
  maintainAspectRatio: false,
  animation: animation ? {duration: 400} : false,
  layout: {padding: {top: 18, right: 44}},
  scales: {
    x: {
      stacked: true,
      min: 0,
      max: 20,
      ticks: {stepSize: 5, color: couleurs.value.attenue, font: policeGraphique},
      grid: {color: couleurs.value.grille},
      border: {display: false},
    },
    y: {
      stacked: true,
      ticks: {color: couleurs.value.texte, font: {...policeGraphique, weight: 500}},
      grid: {display: false},
      border: {display: false},
    },
  },
  plugins: {
    legend: {display: false},
    tooltip: {
      filter: (element) => element.raw > 0,
      callbacks: {title: (elements) => props.ues[elements[0].dataIndex].libelle, label: (element) => `${element.dataset.label} : ${formatNote(element.raw)}`},
    },
  },
}));

// La moyenne écrite au bout de la barre : la valeur se lit sans survoler.
const valeurs = computed(() => ({
  id: 'valeurs',
  afterDatasetsDraw(chart) {
    const {ctx} = chart;
    const barres = chart.getDatasetMeta(0).data;
    ctx.save();
    ctx.font = '600 13px Roboto, sans-serif';
    ctx.textBaseline = 'middle';
    props.ues.forEach((ue, index) => {
      if (ue.moyenne === null || !barres[index]) {
        return;
      }
      const fin = chart.scales.x.getPixelForValue(ue.moyenne + perte(ue));
      const texte = formatNote(ue.moyenne);
      // Un fond sous le chiffre : le trait du seuil peut passer juste derrière.
      ctx.fillStyle = couleurs.value.fond;
      ctx.fillRect(fin + 3, barres[index].y - 9, ctx.measureText(texte).width + 6, 18);
      ctx.fillStyle = couleurUe(ue);
      ctx.fillText(texte, fin + 6, barres[index].y);
    });
    ctx.restore();
  },
}));

const plugins = computed(() => [pluginSeuil('x', couleurs.value.texte), valeurs.value]);
const hauteur = computed(() => `${props.ues.length * 3.25 + 3}rem`);
</script>

<template>
  <GraphiqueAccessible titre="Moyennes par UE" :resume="resume">
    <Chart type="bar" :data="donnees" :options="options" :plugins="plugins" :style="{height: hauteur}"/>
    <p v-if="ues.some(ue => perte(ue) > 0)" class="legende">
      <span class="legende__hachures" aria-hidden="true"></span> Part perdue en absences non justifiées
    </p>
    <template #tableau>
      <table>
        <caption class="sr-only">Moyennes par UE</caption>
        <thead>
          <tr><th scope="col">UE</th><th scope="col" class="nombre">Moyenne</th><th scope="col" class="nombre">Sans pénalité</th><th scope="col">Où en est l'UE</th></tr>
        </thead>
        <tbody>
          <tr v-for="ue in ues" :key="ue.libelle">
            <th scope="row">{{ ue.libelle }}</th>
            <td class="nombre">{{ formatNote(ue.moyenne) }}</td>
            <td class="nombre">{{ formatNote(ue.moyenneSansPenalite) }}</td>
            <td>{{ statutUe(ue).texte }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </GraphiqueAccessible>
</template>

<style scoped>
.legende {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.5rem 0 0;
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
}

.legende__hachures {
  width: 1.25rem;
  height: 0.75rem;
  border: 1px solid var(--p-seance-evaluation-color);
  border-radius: 3px;
  background: repeating-linear-gradient(-45deg, var(--p-seance-evaluation-color) 0 2px, transparent 2px 5px);
}
</style>
