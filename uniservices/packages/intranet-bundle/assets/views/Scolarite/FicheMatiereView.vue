<script setup>
import {computed, ref, watch} from 'vue';
import {useRoute} from 'vue-router';
import {getMaFicheMatiereService} from '@requests';
import {formatNote, statutUe} from '@/utils/scolarite.js';
import NotesMatiere from '@/components/Etudiant/matiere/NotesMatiere.vue';
import ProchainsCoursMatiere from '@/components/Etudiant/matiere/ProchainsCoursMatiere.vue';
import AbsencesMatiere from '@/components/Etudiant/matiere/AbsencesMatiere.vue';
import DocumentsMatiere from '@/components/Etudiant/matiere/DocumentsMatiere.vue';

const route = useRoute();

const fiche = ref(null);
const chargement = ref(true);
const erreur = ref(null);
const maintenant = ref(new Date());

const matiere = computed(() => fiche.value?.matiere ?? null);
const moyenne = computed(() => fiche.value?.notes.moyennePenalisee ?? null);
const ton = computed(() => statutUe({moyenne: moyenne.value}));

const charger = async () => {
  chargement.value = fiche.value === null;
  erreur.value = null;
  try {
    fiche.value = await getMaFicheMatiereService(route.params.id);
    maintenant.value = new Date();
  } catch (error) {
    fiche.value = null;
    erreur.value = error?.response?.status === 404 ? 'introuvable' : 'chargement';
  } finally {
    chargement.value = false;
  }
};

// Le lien d'une fiche vers une autre garde la même page : seul l'identifiant change.
watch(() => route.params.id, () => {
  fiche.value = null;
  charger();
}, {immediate: true});

const BLOCS = [
  {cle: 'cours', titre: 'Prochains cours', icone: 'pi pi-calendar', type: 'cm'},
  {cle: 'notes', titre: 'Notes', icone: 'pi pi-chart-bar', type: 'tp'},
  {cle: 'absences', titre: 'Absences', icone: 'pi pi-calendar-times', type: 'evaluation'},
  {cle: 'documents', titre: 'Documents', icone: 'pi pi-folder', type: 'td'},
];
</script>

<template>
  <section class="fiche col-span-12 mt-16 md:mt-0">
    <div v-if="chargement" class="fiche__chargement" aria-hidden="true">
      <Skeleton height="10rem" border-radius="18px"/>
      <div class="fiche__grille">
        <Skeleton v-for="rang in 4" :key="rang" height="14rem" border-radius="18px"/>
      </div>
    </div>

    <div v-else-if="erreur" class="fiche__message" role="alert">
      <h1 class="fiche__message-titre">{{ erreur === 'introuvable' ? 'Matière introuvable' : 'Fiche matière' }}</h1>
      <p v-if="erreur === 'introuvable'" class="m-0!">Cette matière ne fait pas partie de votre année en cours.</p>
      <p v-else class="m-0!">La fiche de cette matière n'a pas pu être chargée.</p>
      <div class="fiche__actions">
        <Button v-if="erreur === 'chargement'" label="Réessayer" icon="pi pi-refresh" class="min-h-[44px]!" @click="charger"/>
        <router-link to="/intranet/scolarite" class="fiche__retour">Voir mes notes et absences</router-link>
      </div>
    </div>

    <template v-else-if="fiche">
      <!-- La matière sur le violet de l'IUT, comme la carte phare de Notes et absences : ce qui la
           situe à gauche, sa moyenne à droite. -->
      <header class="entete apparition" style="--rang: 0">
        <span class="entete__cercle entete__cercle--accent" aria-hidden="true"></span>
        <span class="entete__cercle entete__cercle--clair" aria-hidden="true"></span>
        <span class="entete__cercle entete__cercle--sombre" aria-hidden="true"></span>
        <div class="entete__identite">
          <p class="entete__situation">
            <span class="entete__type">{{ matiere.type }}</span>
            {{ matiere.semestre }} · {{ (matiere.anneeUniversitaire ?? '').replace('/', '-') }}
          </p>
          <h1 class="entete__titre"><span class="entete__code">{{ matiere.code }}</span> {{ matiere.libelle }}</h1>
          <ul class="entete__ues" aria-label="UE de la matière">
            <li v-for="ue in matiere.ues" :key="ue.numero">{{ ue.libelle }} · coef. {{ formatNote(ue.coefficient) }} · {{ ue.poids }} %</li>
          </ul>
        </div>
        <p class="entete__moyenne">
          <span class="entete__libelle">Moyenne provisoire</span>
          <span class="entete__valeur">{{ formatNote(moyenne) }}<span v-if="moyenne !== null" class="entete__sur"> / 20</span></span>
          <span class="entete__statut">{{ ton.texte }}</span>
        </p>
      </header>

      <div class="fiche__grille">
        <section
            v-for="(bloc, rang) in BLOCS"
            :key="bloc.cle"
            class="bloc apparition"
            :style="{
              '--rang': rang + 1,
              '--bloc-fond': `var(--p-seance-${bloc.type}-background)`,
              '--bloc-couleur': `var(--p-seance-${bloc.type}-color)`,
            }"
            :aria-labelledby="`fiche-${bloc.cle}`"
        >
          <h2 :id="`fiche-${bloc.cle}`" class="bloc__titre">
            <span class="bloc__icone" aria-hidden="true"><i :class="bloc.icone"></i></span>
            {{ bloc.titre }}
          </h2>
          <ProchainsCoursMatiere v-if="bloc.cle === 'cours'" :cours="fiche.prochainsCours" :maintenant="maintenant"/>
          <NotesMatiere v-else-if="bloc.cle === 'notes'" :notes="fiche.notes"/>
          <AbsencesMatiere v-else-if="bloc.cle === 'absences'" :absences="fiche.absences"/>
          <DocumentsMatiere v-else :documents="fiche.documents"/>
        </section>
      </div>
    </template>
  </section>
</template>

<style scoped>
.fiche,
.fiche__chargement {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.fiche__grille {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.25rem;
  align-items: start;
}

.fiche__message {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.25rem;
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 4px 12px -4px rgb(0 0 0 / 0.06);
}

.fiche__message-titre {
  margin: 0 !important;
  font-size: 1.25rem !important;
  font-weight: 600;
}

.fiche__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
}

.fiche__retour {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  font-weight: 600;
  color: var(--p-primary-color);
}

.fiche__retour:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
  border-radius: 6px;
}

/* Le violet en plein dans les deux thèmes : texte blanc 9,95:1, violet clair (primary-200) pour le
   secondaire, comme la carte phare. Les cercles du logo restent hors du texte. */
.entete {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1.5rem 1.75rem;
  overflow: hidden;
  border-radius: 18px;
  background: var(--p-primary-500);
  color: var(--p-seance-cm-pill-color);
}

.entete > :not(.entete__cercle) {
  position: relative;
}

.entete__identite {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.entete__situation {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.625rem;
  margin: 0;
  color: var(--p-primary-200);
}

/* La capsule jaune, texte violet dessus : 5,29:1. */
.entete__type {
  padding: 0 0.625rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.5rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  background: var(--accent-color);
  color: var(--p-primary-500);
}

.entete__titre {
  margin: 0 !important;
  font-size: 1.75rem !important;
  font-weight: 500;
  line-height: 1.25;
  color: inherit;
  overflow-wrap: anywhere;
}

.entete__code {
  font-weight: 700;
}

.entete__ues {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 1.25rem;
  margin: 0.25rem 0 0;
  padding: 0;
  list-style: none;
  color: var(--p-primary-200);
}

.entete__moyenne {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
  margin: 0 5rem 0 0;
  text-align: right;
}

.entete__libelle,
.entete__statut,
.entete__sur {
  font-size: 1rem;
  color: var(--p-primary-200);
}

.entete__sur {
  font-weight: 500;
}

.entete__valeur {
  font-size: 2.75rem;
  font-weight: 700;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}

.entete__cercle {
  position: absolute;
  border-radius: 999px;
  pointer-events: none;
}

.entete__cercle--accent {
  right: -2.25rem;
  top: -2.25rem;
  width: 6.5rem;
  height: 6.5rem;
  background: var(--accent-color);
}

.entete__cercle--clair {
  right: 3.25rem;
  top: 2.75rem;
  width: 1.25rem;
  height: 1.25rem;
  background: var(--p-primary-300);
}

.entete__cercle--sombre {
  right: -1.5rem;
  bottom: -2.75rem;
  width: 6rem;
  height: 6rem;
  background: var(--p-primary-600);
}

/* Les blocs de l'accueil : carte blanche, ombre douce, pastille d'icône teintée au type de donnée. */
.bloc {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.25rem 1.375rem;
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 4px 12px -4px rgb(0 0 0 / 0.06);
}

.bloc__titre {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin: 0 !important;
  font-size: 1.0625rem !important;
  font-weight: 600;
  color: var(--p-text-color);
}

.bloc__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 1.125rem;
  background: var(--bloc-fond);
  color: var(--bloc-couleur);
}

@media (max-width: 1199px) {
  .fiche__grille {
    grid-template-columns: minmax(0, 1fr);
  }
}

/* Sur téléphone, la moyenne passe sous le nom de la matière. */
@media (max-width: 639px) {
  .entete {
    flex-direction: column;
    align-items: flex-start;
    padding: 1.25rem;
  }

  .entete__titre {
    font-size: 1.375rem !important;
  }

  .entete__moyenne {
    align-items: flex-start;
    margin: 0;
    text-align: left;
  }

  .fiche__grille {
    gap: 0.75rem;
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
}
</style>
