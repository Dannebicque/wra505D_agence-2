<script setup>
import {computed} from 'vue';
import {formatDate, formatNote, prochaineEvaluation, syntheseSemestre} from '@/utils/scolarite.js';

const props = defineProps({
  semestre: {type: Object, required: true},
});
const emit = defineEmits(['voir-absences']);

const chiffres = computed(() => syntheseSemestre(props.semestre));
const prochaine = computed(() => prochaineEvaluation(props.semestre));
const points = (valeur) => `${formatNote(valeur)} pt${valeur > 1 ? 's' : ''}`;
</script>

<template>
  <ul class="synthese">
    <li :class="['tuile', chiffres.uesNotees && chiffres.uesValidees < chiffres.uesNotees ? 'tuile--alerte' : 'tuile--bonne']">
      <span class="tuile__libelle">UE à 10 ou plus</span>
      <span class="tuile__valeur">{{ chiffres.uesValidees }}<span class="tuile__sur"> sur {{ chiffres.uesNotees }}</span></span>
      <span class="tuile__detail">{{ chiffres.totalUes - chiffres.uesNotees ? `${chiffres.totalUes - chiffres.uesNotees} sans note` : 'toutes notées' }}</span>
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
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
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

.tuile__lien {
  align-self: flex-start;
  margin: 0.25rem 0 0 -0.75rem;
}
</style>
