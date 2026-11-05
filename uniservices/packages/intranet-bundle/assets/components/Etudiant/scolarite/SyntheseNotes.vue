<script setup>
import {computed} from 'vue';
import {formatNote, statutUe, syntheseSemestre} from '@/utils/scolarite.js';
import {useCompteur} from '@/composables/useCompteur.js';

const props = defineProps({
  semestre: {type: Object, required: true},
});
const emit = defineEmits(['voir-absences']);

const chiffres = computed(() => syntheseSemestre(props.semestre));
const sousDix = computed(() => props.semestre.ues.filter(ue => ue.moyenne !== null && ue.moyenne !== undefined && ue.moyenne < 10));
const alerteAbsences = computed(() => chiffres.value.pointsPerdus > 0 || chiffres.value.pointsEnSuspens > 0);
const uesValidees = useCompteur(() => chiffres.value.uesValidees);
const notesPubliees = useCompteur(() => chiffres.value.notesPubliees);
const pointsPerdus = useCompteur(() => chiffres.value.pointsPerdus);
const points = (valeur) => `${formatNote(valeur)} pt${valeur > 1 ? 's' : ''}`;
</script>

<template>
  <ul class="synthese">
    <!-- La donnée qui décide du semestre, seule sur le violet de l'IUT, comme la carte de l'accueil. -->
    <li class="tuile tuile--phare apparition" style="--rang: 0">
      <span class="phare__cercle phare__cercle--accent" aria-hidden="true"></span>
      <span class="phare__cercle phare__cercle--clair" aria-hidden="true"></span>
      <span class="phare__cercle phare__cercle--sombre" aria-hidden="true"></span>
      <span class="tuile__libelle">UE à 10 ou plus</span>
      <span class="tuile__valeur">
        <span aria-hidden="true">{{ Math.round(uesValidees) }}</span><span class="sr-only">{{ chiffres.uesValidees }}</span><span class="tuile__sur"> sur {{ chiffres.uesNotees }}</span>
      </span>
      <!-- Une case par UE : pleine à 10 ou plus, cerclée de jaune en dessous, vide sans note. -->
      <span class="phare__ues" aria-hidden="true">
        <span v-for="ue in semestre.ues" :key="ue.libelle" :class="['phare__ue', `phare__ue--${statutUe(ue).ton ?? 'vide'}`]"></span>
      </span>
      <span class="tuile__detail">{{ chiffres.totalUes - chiffres.uesNotees ? `${chiffres.totalUes - chiffres.uesNotees} UE sans note` : 'Toutes les UE sont notées' }}</span>
      <span v-if="sousDix.length" class="phare__alerte">
        <i class="pi pi-exclamation-circle" aria-hidden="true"></i>
        Sous 10 : {{ sousDix.map(ue => ue.libelle).join(', ') }}
      </span>
    </li>
    <li class="tuile apparition" style="--rang: 1; --tuile-fond: var(--p-seance-cm-background); --tuile-couleur: var(--p-seance-cm-color)">
      <span class="tuile__entete"><span class="tuile__icone" aria-hidden="true"><i class="pi pi-file-check"></i></span>Notes publiées</span>
      <span class="tuile__valeur"><span aria-hidden="true">{{ Math.round(notesPubliees) }}</span><span class="sr-only">{{ chiffres.notesPubliees }}</span></span>
      <span class="tuile__detail">ce semestre</span>
    </li>
    <li
        class="tuile apparition"
        :style="{'--rang': 2, '--tuile-fond': `var(--p-seance-${alerteAbsences ? 'evaluation' : 'autre'}-background)`, '--tuile-couleur': `var(--p-seance-${alerteAbsences ? 'evaluation' : 'autre'}-color)`}"
    >
      <span class="tuile__entete"><span class="tuile__icone" aria-hidden="true"><i class="pi pi-calendar-times"></i></span>Perdus en absences</span>
      <span class="tuile__valeur"><span aria-hidden="true">{{ points(pointsPerdus) }}</span><span class="sr-only">{{ points(chiffres.pointsPerdus) }}</span></span>
      <span class="tuile__detail">
        <template v-if="chiffres.pointsEnSuspens">{{ points(chiffres.pointsEnSuspens) }} dès la prochaine note</template>
        <template v-else>aucune pénalité en attente</template>
      </span>
      <!-- Le lien des blocs de l'accueil, mais un bouton : il ouvre l'onglet voisin, sans quitter la page. -->
      <button v-if="chiffres.absencesPenalisantes || chiffres.absencesEnAttente" type="button" class="tuile__lien" @click="emit('voir-absences')">
        Voir les absences
        <span class="tuile__fleche" aria-hidden="true"><i class="pi pi-arrow-right"></i></span>
      </button>
    </li>
  </ul>
</template>

<style scoped>
.synthese {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) repeat(2, minmax(0, 1fr));
  gap: 1.25rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Les blocs de l'accueil : carte blanche, ombre douce, pastille d'icône teintée au type de donnée. */
.tuile {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 1.25rem 1.375rem;
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 4px 12px -4px rgb(0 0 0 / 0.06);
}

.tuile__entete {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin-bottom: 0.375rem;
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--p-text-color);
}

.tuile__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 0.9375rem;
  background: var(--tuile-fond);
  color: var(--tuile-couleur);
}

.tuile__libelle {
  font-size: 0.875rem;
}

.tuile__valeur {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.2;
  color: var(--p-text-color);
  font-variant-numeric: tabular-nums;
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
.tuile__entete {
  overflow-wrap: anywhere;
}

/* Le violet en plein dans les deux thèmes : texte blanc 9,95:1, violet clair (primary-200) pour le
   secondaire, comme sur la carte de l'accueil. Les cercles du logo restent hors du texte. */
.tuile--phare {
  position: relative;
  justify-content: center;
  padding: 1.125rem 1.375rem;
  overflow: hidden;
  box-shadow: none;
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

.phare__ues {
  display: flex;
  gap: 0.375rem;
  margin: 0.375rem 0 0.25rem;
}

/* Chaque case garde 3:1 au moins sur le violet : primary-200 plein, ou un contour jaune. */
.phare__ue {
  width: 2rem;
  height: 0.5rem;
  border-radius: 999px;
  border: 2px solid var(--p-primary-300);
}

.phare__ue--bonne {
  border-color: var(--p-primary-200);
  background: var(--p-primary-200);
}

.phare__ue--faible {
  border-color: var(--accent-color);
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
  align-self: flex-end;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  margin-top: auto;
  padding: 0 0.25rem 0 0.75rem;
  border: none;
  border-radius: 999px;
  font: inherit;
  font-weight: 600;
  color: var(--p-primary-color);
  background: transparent;
  cursor: pointer;
  transition: background-color 150ms ease;
}

.tuile__lien:hover {
  background: var(--p-content-hover-background);
}

.tuile__lien:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.tuile__fleche {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  font-size: 0.75rem;
  background: var(--p-content-hover-background);
  transition: transform 150ms ease;
}

.tuile__lien:hover .tuile__fleche {
  transform: translateX(2px);
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

/* Les blocs arrivent l'un après l'autre, du haut vers le bas, à chaque affichage. */
.apparition {
  animation: apparition 420ms cubic-bezier(0.22, 1, 0.36, 1) both;
  animation-delay: calc(var(--rang, 0) * 70ms);
}

@keyframes apparition {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .apparition {
    animation: none;
  }

  .tuile__lien,
  .tuile__fleche {
    transition: none;
  }
}
</style>
