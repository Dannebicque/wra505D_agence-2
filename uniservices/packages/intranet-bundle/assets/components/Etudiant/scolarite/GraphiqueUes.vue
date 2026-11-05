<script setup>
import {computed} from 'vue';
import {formatNote, statutUe} from '@/utils/scolarite.js';
import {animationEntree, dessinerPastille, pluginSeuil, pluginZones, policeGraphique, useThemeGraphique} from '@/composables/useThemeGraphique.js';
import GraphiqueAccessible from './GraphiqueAccessible.vue';
import PastilleNote from './PastilleNote.vue';

const props = defineProps({
  ues: {type: Array, required: true},
});

const {couleurs, animation} = useThemeGraphique();

const valide = (ue) => ue.moyenne >= 10;
const ton = (ue) => (ue.moyenne === null ? null : (valide(ue) ? 'bonne' : 'faible'));

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
  datasets: [{
    label: 'Moyenne',
    data: props.ues.map(ue => ue.moyenne),
    backgroundColor: props.ues.map(ue => (valide(ue) ? couleurs.value.vert : couleurs.value.rouge)),
    borderRadius: 999,
    borderSkipped: false,
    barPercentage: 0.5,
    maxBarThickness: 20,
  }],
}));

const options = computed(() => ({
  indexAxis: 'y',
  maintainAspectRatio: false,
  animation: animationEntree(animation, 120),
  layout: {padding: {top: 20, right: 56}},
  scales: {
    x: {
      min: 0,
      max: 20,
      ticks: {stepSize: 5, color: couleurs.value.attenue, font: policeGraphique},
      grid: {display: false},
      border: {display: false},
    },
    y: {
      ticks: {color: couleurs.value.texte, font: {...policeGraphique, weight: 500}},
      grid: {display: false},
      border: {display: false},
    },
  },
  plugins: {
    legend: {display: false},
    tooltip: {
      padding: 10,
      cornerRadius: 10,
      callbacks: {
        title: (elements) => props.ues[elements[0].dataIndex].libelle,
        label: (element) => `Moyenne : ${formatNote(element.raw)} / 20`,
        afterLabel: (element) => statutUe(props.ues[element.dataIndex]).texte,
      },
    },
  },
}));

// La moyenne dans une pastille au bout de la barre, qui suit la barre pendant son entrée.
const valeurs = computed(() => ({
  id: 'valeurs',
  afterDatasetsDraw(chart) {
    const barres = chart.getDatasetMeta(0).data;
    chart.ctx.save();
    props.ues.forEach((ue, index) => {
      if (ue.moyenne === null || !barres[index]) {
        return;
      }
      dessinerPastille(chart.ctx, formatNote(ue.moyenne), barres[index].x + 6, barres[index].y,
        valide(ue) ? couleurs.value.vertFond : couleurs.value.rougeFond,
        valide(ue) ? couleurs.value.vert : couleurs.value.rouge);
    });
    chart.ctx.restore();
  },
}));

const plugins = computed(() => [pluginZones('x', couleurs.value), pluginSeuil('x', couleurs.value.texte), valeurs.value]);
const hauteur = computed(() => `${props.ues.length * 3.5 + 3}rem`);
</script>

<template>
  <GraphiqueAccessible :rang="4" titre="Moyennes par UE" icone="pi pi-chart-bar" teinte="cm" :resume="resume">
    <Chart type="bar" :data="donnees" :options="options" :plugins="plugins" class="ues" :style="{minHeight: hauteur}"/>
    <template #tableau>
      <table>
        <caption class="sr-only">Moyennes par UE</caption>
        <thead>
          <tr>
            <th scope="col">UE</th>
            <th scope="col" class="nombre">Moyenne</th>
            <th scope="col">Où en est l'UE</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ue in ues" :key="ue.libelle">
            <th scope="row">{{ ue.libelle }}</th>
            <td class="nombre"><PastilleNote :ton="ton(ue)">{{ formatNote(ue.moyenne) }}</PastilleNote></td>
            <td>
              <span :class="['statut', `statut--${ton(ue) ?? 'vide'}`]">
                <i :class="valide(ue) ? 'pi pi-check-circle' : (ue.moyenne === null ? 'pi pi-minus-circle' : 'pi pi-exclamation-circle')" aria-hidden="true"></i>
                {{ statutUe(ue).texte }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </GraphiqueAccessible>
</template>

<style scoped>
.ues {
  flex: 1;
}

.statut {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.125rem 0.625rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 600;
  white-space: nowrap;
  background: var(--p-seance-autre-background);
  color: var(--p-seance-autre-color);
}

.statut--bonne {
  background: var(--p-seance-tp-background);
  color: var(--p-seance-tp-color);
}

.statut--faible {
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}
</style>
