<script setup>
import {computed} from 'vue';
import {evolutionNotes, formatDate, formatNote, matieresNotees, syntheseSemestre} from '@/utils/scolarite.js';
import {tonNote} from '@/utils/accueil.js';
import {useCouleursTheme} from './useCouleursTheme.js';

const props = defineProps({
  semestre: {type: Object, required: true},
});

const couleurs = useCouleursTheme();
const chiffres = computed(() => syntheseSemestre(props.semestre));
const ues = computed(() => props.semestre.ues.filter(ue => ue.moyenne !== null));
const matieres = computed(() => matieresNotees(props.semestre));
const evolution = computed(() => evolutionNotes(props.semestre));

const couleurNote = (note) => couleurs.value[tonNote(note) ?? 'moyenne'];

// Le seuil de 10, en pointillés : c'est lui qui valide une UE.
const seuil = {
  id: 'seuil',
  afterDatasetsDraw(chart, _args, options) {
    const {ctx, chartArea, scales} = chart;
    const echelle = options.axe === 'y' ? scales.y : scales.x;
    const position = echelle.getPixelForValue(10);
    ctx.save();
    ctx.strokeStyle = options.couleur;
    ctx.lineWidth = 1.5;
    ctx.setLineDash([5, 4]);
    ctx.beginPath();
    if (options.axe === 'y') {
      ctx.moveTo(chartArea.left, position);
      ctx.lineTo(chartArea.right, position);
    } else {
      ctx.moveTo(position, chartArea.top);
      ctx.lineTo(position, chartArea.bottom);
    }
    ctx.stroke();
    ctx.restore();
  },
};

const echelleNote = () => ({
  min: 0,
  max: 20,
  ticks: {stepSize: 5, color: couleurs.value.attenue},
  grid: {color: couleurs.value.grille},
});

const optionsBarres = computed(() => ({
  indexAxis: 'y',
  maintainAspectRatio: false,
  animation: false,
  plugins: {
    legend: {display: false},
    tooltip: {callbacks: {label: (contexte) => `${contexte.dataset.label} : ${formatNote(contexte.raw)}`}},
    seuil: {couleur: couleurs.value.attenue, axe: 'x'},
  },
  scales: {
    x: {...echelleNote(), stacked: true},
    y: {stacked: true, ticks: {color: couleurs.value.texte}, grid: {display: false}},
  },
}));

// La part perdue en absences se pose au bout de la barre, en clair : on voit ce que l'on aurait eu.
const barres = (lignes, libelle) => ({
  labels: lignes.map(libelle),
  datasets: [
    {
      label: 'Moyenne',
      data: lignes.map(ligne => ligne.moyenne),
      backgroundColor: lignes.map(ligne => couleurNote(ligne.moyenne)),
      borderRadius: 6,
      barThickness: 18,
    },
    {
      label: 'Perdu en absences',
      data: lignes.map(ligne => Math.max(0, Math.round((ligne.sansPenalite - ligne.moyenne) * 100) / 100)),
      backgroundColor: couleurs.value.faibleFond,
      borderColor: couleurs.value.faible,
      borderWidth: 1,
      borderRadius: 6,
      barThickness: 18,
    },
  ],
});

const donneesUes = computed(() => barres(
  ues.value.map(ue => ({libelle: ue.libelle, moyenne: ue.moyenne, sansPenalite: ue.moyenneSansPenalite ?? ue.moyenne})),
  ligne => ligne.libelle,
));

const donneesMatieres = computed(() => barres(
  matieres.value.map(matiere => ({code: matiere.code, moyenne: matiere.moyennePenalisee, sansPenalite: matiere.moyenne})),
  ligne => ligne.code,
));

const donneesEvolution = computed(() => ({
  labels: evolution.value.map(note => (note.date ? new Date(note.date).toLocaleDateString('fr-FR', {day: 'numeric', month: 'short', timeZone: 'UTC'}) : '')),
  datasets: [
    {
      type: 'line',
      label: 'Moyenne des notes publiées',
      data: evolution.value.map(note => note.moyenneCumulee),
      borderColor: couleurs.value.primaire,
      backgroundColor: couleurs.value.primaire,
      borderWidth: 2.5,
      pointRadius: 0,
    },
    {
      type: 'line',
      label: 'Note',
      data: evolution.value.map(note => note.valeur),
      showLine: false,
      pointRadius: 5,
      pointHoverRadius: 7,
      pointBackgroundColor: evolution.value.map(note => couleurNote(note.valeur)),
      pointBorderColor: evolution.value.map(note => couleurNote(note.valeur)),
    },
  ],
}));

const optionsEvolution = computed(() => ({
  maintainAspectRatio: false,
  animation: false,
  plugins: {
    legend: {display: false},
    tooltip: {
      callbacks: {
        title: (elements) => {
          const note = evolution.value[elements[0].dataIndex];
          return `${note.matiere.code} · ${note.libelle}`;
        },
        label: (contexte) => `${contexte.dataset.label} : ${formatNote(contexte.raw)}`,
      },
    },
    seuil: {couleur: couleurs.value.attenue, axe: 'y'},
  },
  scales: {
    y: echelleNote(),
    x: {ticks: {color: couleurs.value.attenue, maxRotation: 0, autoSkip: true}, grid: {display: false}},
  },
}));

const hauteur = (nombre) => `${Math.max(120, nombre * 34 + 40)}px`;
</script>

<template>
  <div class="synthese">
    <ul class="synthese__chiffres">
      <li :class="['chiffre', chiffres.uesNotees && chiffres.uesValidees === chiffres.uesNotees ? 'chiffre--bonne' : 'chiffre--neutre']">
        <span class="chiffre__valeur">{{ chiffres.uesValidees }} / {{ chiffres.uesNotees }}</span>
        <span class="chiffre__libelle">UE à 10 ou plus</span>
        <span class="chiffre__detail">{{ chiffres.totalUes - chiffres.uesNotees ? `${chiffres.totalUes - chiffres.uesNotees} sans note encore` : 'toutes notées' }}</span>
      </li>
      <li :class="['chiffre', chiffres.pointsPerdus ? 'chiffre--faible' : 'chiffre--neutre']">
        <span class="chiffre__valeur">{{ chiffres.pointsPerdus ? `−${formatNote(chiffres.pointsPerdus)}` : '0' }} pt</span>
        <span class="chiffre__libelle">perdus en absences</span>
        <span class="chiffre__detail">
          <template v-if="chiffres.pointsEnSuspens">−{{ formatNote(chiffres.pointsEnSuspens) }} pt de plus dès la première note des matières concernées. </template>
          <template v-if="chiffres.absencesEnAttente">{{ chiffres.absencesEnAttente }} absence{{ chiffres.absencesEnAttente > 1 ? 's' : '' }} levable{{ chiffres.absencesEnAttente > 1 ? 's' : '' }} si le justificatif est accepté.</template>
          <template v-else-if="!chiffres.pointsEnSuspens">{{ chiffres.absencesPenalisantes }} absence{{ chiffres.absencesPenalisantes > 1 ? 's' : '' }} non justifiée{{ chiffres.absencesPenalisantes > 1 ? 's' : '' }}</template>
        </span>
      </li>
      <li class="chiffre chiffre--neutre">
        <span class="chiffre__valeur">{{ chiffres.notesPubliees }}</span>
        <span class="chiffre__libelle">note{{ chiffres.notesPubliees > 1 ? 's' : '' }} publiée{{ chiffres.notesPubliees > 1 ? 's' : '' }}</span>
        <span class="chiffre__detail">{{ semestre.penalite.active ? `−${formatNote(semestre.penalite.points)} pt par absence non justifiée` : 'pas de pénalité d\'absence ce semestre' }}</span>
      </li>
    </ul>

    <p v-if="!chiffres.notesPubliees" class="synthese__vide">Aucune note publiée pour ce semestre : les graphiques apparaîtront avec la première.</p>

    <template v-else>
      <figure class="graphique">
        <figcaption class="graphique__titre">Moyenne par UE <span class="graphique__aide">seuil de validation à 10</span></figcaption>
        <div aria-hidden="true" :style="{ height: hauteur(ues.length) }">
          <Chart type="bar" :data="donneesUes" :options="optionsBarres" :plugins="[seuil]" class="h-full"/>
        </div>
        <table class="sr-only">
          <caption>Moyenne par UE</caption>
          <thead><tr><th scope="col">UE</th><th scope="col">Moyenne</th><th scope="col">Sans pénalité</th></tr></thead>
          <tbody>
            <tr v-for="ue in ues" :key="ue.libelle"><th scope="row">{{ ue.libelle }}</th><td>{{ formatNote(ue.moyenne) }}</td><td>{{ formatNote(ue.moyenneSansPenalite) }}</td></tr>
          </tbody>
        </table>
      </figure>

      <figure class="graphique">
        <figcaption class="graphique__titre">Évolution <span class="graphique__aide">chaque note, et la moyenne de toutes jusque-là</span></figcaption>
        <div aria-hidden="true" class="graphique__zone">
          <Chart type="line" :data="donneesEvolution" :options="optionsEvolution" :plugins="[seuil]" class="h-full"/>
        </div>
        <table class="sr-only">
          <caption>Notes publiées dans l'ordre</caption>
          <thead><tr><th scope="col">Date</th><th scope="col">Évaluation</th><th scope="col">Note</th><th scope="col">Moyenne jusque-là</th></tr></thead>
          <tbody>
            <tr v-for="note in evolution" :key="`${note.matiere.code}-${note.libelle}-${note.date}`">
              <td>{{ formatDate(note.date) }}</td><th scope="row">{{ note.matiere.code }} {{ note.libelle }}</th><td>{{ formatNote(note.valeur) }}</td><td>{{ formatNote(note.moyenneCumulee) }}</td>
            </tr>
          </tbody>
        </table>
      </figure>

      <figure class="graphique">
        <figcaption class="graphique__titre">Moyenne par matière <span class="graphique__aide">la part en clair est perdue en absences</span></figcaption>
        <div aria-hidden="true" :style="{ height: hauteur(matieres.length) }">
          <Chart type="bar" :data="donneesMatieres" :options="optionsBarres" :plugins="[seuil]" class="h-full"/>
        </div>
        <table class="sr-only">
          <caption>Moyenne par matière</caption>
          <thead><tr><th scope="col">Matière</th><th scope="col">Moyenne</th><th scope="col">Pénalité</th></tr></thead>
          <tbody>
            <tr v-for="matiere in matieres" :key="matiere.code">
              <th scope="row">{{ matiere.code }} {{ matiere.libelle }}</th><td>{{ formatNote(matiere.moyennePenalisee) }}</td><td>{{ matiere.penalite ? `−${formatNote(matiere.penalite)}` : 'aucune' }}</td>
            </tr>
          </tbody>
        </table>
      </figure>
    </template>
  </div>
</template>

<style scoped>
.synthese {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.synthese__chiffres {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Une tuile par chiffre, teintée par son sens comme les pastilles de note de l'accueil. */
.chiffre {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.875rem 1rem;
  border-radius: 14px;
  background: var(--chiffre-fond);
  color: var(--chiffre-couleur);
}

.chiffre--neutre {
  --chiffre-fond: var(--p-seance-autre-background);
  --chiffre-couleur: var(--p-text-color);
}

.chiffre--faible {
  --chiffre-fond: var(--p-seance-evaluation-background);
  --chiffre-couleur: var(--p-seance-evaluation-color);
}

.chiffre--bonne {
  --chiffre-fond: var(--p-seance-tp-background);
  --chiffre-couleur: var(--p-seance-tp-color);
}

.chiffre__valeur {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.1;
}

.chiffre__libelle {
  font-weight: 600;
}

.chiffre__detail {
  font-size: 0.875rem;
}

/* Sur téléphone, les trois chiffres tiennent sur une ligne : le détail reste dans les vues. */
@media (max-width: 639px) {
  .synthese__chiffres {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
  }

  .chiffre {
    padding: 0.625rem;
  }

  .chiffre__valeur {
    font-size: 1.25rem;
  }

  .chiffre__libelle {
    font-size: 0.8125rem;
  }

  .chiffre__detail {
    display: none;
  }
}

.synthese__vide {
  margin: 0;
  color: var(--p-text-muted-color);
}

.graphique {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 0;
}

.graphique__titre {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.25rem 0.75rem;
  font-weight: 600;
}

.graphique__aide {
  font-size: 0.875rem;
  font-weight: 400;
  color: var(--p-text-muted-color);
}

.graphique__zone {
  height: 220px;
}
</style>
