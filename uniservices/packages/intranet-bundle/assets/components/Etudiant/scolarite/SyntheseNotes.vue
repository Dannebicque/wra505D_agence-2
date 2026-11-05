<script setup>
import {computed} from 'vue';
import {formatDate, formatNote, prochaineEvaluation, syntheseSemestre} from '@/utils/scolarite.js';

const props = defineProps({
  semestre: {type: Object, required: true},
});
const emit = defineEmits(['voir-absences']);

const chiffres = computed(() => syntheseSemestre(props.semestre));
const prochaine = computed(() => prochaineEvaluation(props.semestre));
const sousDix = computed(() => props.semestre.ues.filter(ue => ue.moyenne !== null && ue.moyenne !== undefined && ue.moyenne < 10));
const points = (valeur) => `${formatNote(valeur)} pt${valeur > 1 ? 's' : ''}`;
</script>

<template>
  <ul class="synthese">
    <!-- La donnée qui décide du semestre, seule sur le violet de l'IUT, comme la carte de l'accueil. -->
    <li class="tuile tuile--phare">
      <span class="phare__cercle phare__cercle--accent" aria-hidden="true"></span>
      <span class="phare__cercle phare__cercle--clair" aria-hidden="true"></span>
      <span class="phare__cercle phare__cercle--sombre" aria-hidden="true"></span>
      <span class="tuile__libelle">UE à 10 ou plus</span>
      <span class="tuile__valeur">{{ chiffres.uesValidees }}<span class="tuile__sur"> sur {{ chiffres.uesNotees }}</span></span>
      <span class="tuile__detail">{{ chiffres.totalUes - chiffres.uesNotees ? `${chiffres.totalUes - chiffres.uesNotees} UE sans note` : 'Toutes les UE sont notées' }}</span>
      <span v-if="sousDix.length" class="phare__alerte">
        <i class="pi pi-exclamation-circle" aria-hidden="true"></i>
        Sous 10 : {{ sousDix.map(ue => ue.libelle).join(', ') }}
      </span>
    </li>
    <li class="tuile">
      <span class="tuile__libelle">Notes publiées</span>
      <span class="tuile__valeur">{{ chiffres.notesPubliees }}</span>
      <span class="tuile__detail">ce semestre</span>
    </li>
    <li class="tuile">
      <span class="tuile__libelle">Prochaine évaluation</span>
      <template v-if="prochaine">
        <span class="tuile__valeur tuile__valeur--texte"><time :datetime="prochaine.date">{{ formatDate(prochaine.date) }}</time></span>
        <span class="tuile__detail">{{ prochaine.matiere }} · {{ prochaine.libelle }}</span>
      </template>
      <span v-else class="tuile__detail">Aucune prévue</span>
    </li>
    <li :class="['tuile', chiffres.pointsPerdus || chiffres.pointsEnSuspens ? 'tuile--alerte' : '']">
      <span class="tuile__libelle">Perdus en absences</span>
      <span class="tuile__valeur">{{ points(chiffres.pointsPerdus) }}</span>
      <span class="tuile__detail">
        <template v-if="chiffres.pointsEnSuspens">{{ points(chiffres.pointsEnSuspens) }} dès la prochaine note</template>
        <template v-else>aucune pénalité en attente</template>
      </span>
      <Button
          v-if="chiffres.absencesPenalisantes || chiffres.absencesEnAttente"
          label="Voir les absences"
          icon="pi pi-arrow-right"
          icon-pos="right"
          text
          size="small"
          class="tuile__lien min-h-[44px]!"
          @click="emit('voir-absences')"
      />
    </li>
  </ul>
</template>

<style scoped>
.synthese {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.tuile {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.875rem 1rem;
  border: 1px solid var(--p-content-border-color);
  border-left: 4px solid var(--p-seance-autre-color);
  border-radius: 14px;
}

.tuile--bonne {
  border-left-color: var(--p-seance-tp-color);
}

.tuile--alerte {
  border-left-color: var(--p-seance-evaluation-color);
}

.tuile__libelle {
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
}

.tuile__valeur {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.2;
  color: var(--p-text-color);
  font-variant-numeric: tabular-nums;
}

.tuile__valeur--texte {
  font-size: 1.25rem;
}

.tuile__sur {
  font-size: 1rem;
  font-weight: 500;
  color: var(--p-text-muted-color);
}

.tuile__detail {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.tuile__detail,
.tuile__libelle {
  overflow-wrap: anywhere;
}

/* Le violet en plein dans les deux thèmes : texte blanc 9,95:1, violet clair (primary-200) pour le
   secondaire, comme sur la carte de l'accueil. Les cercles du logo restent hors du texte. */
.tuile--phare {
  position: relative;
  justify-content: center;
  padding: 1.125rem 1.375rem;
  overflow: hidden;
  border: none;
  border-radius: 18px;
  background: var(--p-primary-500);
  color: #FFFFFF;
}

.tuile--phare > :not(.phare__cercle) {
  position: relative;
  max-width: calc(100% - 4.5rem);
}

.tuile--phare .tuile__libelle,
.tuile--phare .tuile__detail,
.tuile--phare .tuile__sur {
  color: var(--p-primary-200);
}

.tuile--phare .tuile__valeur {
  font-size: 2.5rem;
  color: inherit;
}

.phare__cercle {
  position: absolute;
  border-radius: 999px;
  pointer-events: none;
}

.phare__cercle--accent {
  right: -2.25rem;
  top: -2.25rem;
  width: 6.5rem;
  height: 6.5rem;
  background: var(--accent-color);
}

.phare__cercle--clair {
  right: 3.25rem;
  top: 2.75rem;
  width: 1.25rem;
  height: 1.25rem;
  background: var(--p-primary-300);
}

.phare__cercle--sombre {
  right: -1.5rem;
  bottom: -2.75rem;
  width: 6rem;
  height: 6rem;
  background: var(--p-primary-600);
}

/* L'alerte en capsule jaune, texte violet dessus : 5,29:1. */
.phare__alerte {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: 0.375rem;
  margin-top: 0.625rem;
  padding: 0.25rem 0.75rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 600;
  background: var(--accent-color);
  color: var(--p-primary-500);
}

.tuile__lien {
  align-self: flex-start;
  margin: 0.25rem 0 0 -0.75rem;
}

/* En dessous du bureau large, deux colonnes : la carte violette prend toute la première ligne. */
@media (max-width: 1199px) {
  .synthese {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .tuile--phare {
    grid-column: 1 / -1;
  }
}

@media (max-width: 479px) {
  .synthese .tuile:last-child {
    grid-column: 1 / -1;
  }
}
</style>
