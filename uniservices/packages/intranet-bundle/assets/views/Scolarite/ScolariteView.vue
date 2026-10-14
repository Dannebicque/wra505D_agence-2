<script setup>
import {computed, onMounted, ref, watch} from 'vue';
import {onBeforeRouteLeave, useRoute, useRouter} from 'vue-router';
import {BlocAccueil} from '@components';
import {getMaScolariteService} from '@requests';
import {libelleSemestre, semestreParDefaut} from '@/utils/scolarite.js';
import SyntheseSemestre from '@/components/Etudiant/scolarite/SyntheseSemestre.vue';
import NotesParUe from '@/components/Etudiant/scolarite/NotesParUe.vue';
import ToutesLesNotes from '@/components/Etudiant/scolarite/ToutesLesNotes.vue';
import AbsencesSemestre from '@/components/Etudiant/scolarite/AbsencesSemestre.vue';

const route = useRoute();
const router = useRouter();

const releve = ref(null);
const chargement = ref(true);
const erreur = ref(false);

// La carte des notes s'ouvre sur la synthèse ; les autres vues se choisissent dans la carte.
const AFFICHAGES = [
  {id: 'synthese', libelle: 'Synthèse'},
  {id: 'ue', libelle: 'Par UE'},
  {id: 'notes', libelle: 'Chronologie'},
];
const affichage = ref(AFFICHAGES.some(({id}) => id === route.query.affichage) ? route.query.affichage : 'synthese');
const idSemestre = ref(null);

const semestres = computed(() => releve.value?.semestres ?? []);
const semestre = computed(() => semestres.value.find(candidat => candidat.id === idSemestre.value) ?? null);
const optionsSemestres = computed(() => semestres.value.map(candidat => ({id: candidat.id, libelle: libelleSemestre(candidat)})));

const charger = async () => {
  chargement.value = true;
  erreur.value = false;
  try {
    releve.value = await getMaScolariteService();
    idSemestre.value = semestreParDefaut(semestres.value, route.query.semestre)?.id ?? null;
  } catch {
    erreur.value = true;
  } finally {
    chargement.value = false;
  }
};
onMounted(charger);

// Semestre et vue vivent dans l'adresse, comme la semaine de l'agenda. Une fois la page quittée,
// réécrire l'adresse annulerait la navigation en cours.
let quittee = false;
onBeforeRouteLeave(() => {
  quittee = true;
});
watch([idSemestre, affichage], ([semestreChoisi, vue]) => {
  if (quittee || semestreChoisi === null) {
    return;
  }
  router.replace({query: {semestre: String(semestreChoisi), ...(vue === 'synthese' ? {} : {affichage: vue})}});
});
</script>

<template>
  <section class="scolarite col-span-12 mt-10 md:mt-0">
    <div class="scolarite__entete">
      <div>
        <h1 class="scolarite__titre">Notes et absences</h1>
        <p class="scolarite__aide">Moyennes provisoires, calculées sur les notes publiées, jusqu'à la sous-commission.</p>
      </div>
      <div v-if="optionsSemestres.length" class="scolarite__semestre">
        <label for="choix-semestre">Semestre</label>
        <Select
            v-model="idSemestre"
            input-id="choix-semestre"
            :options="optionsSemestres"
            option-label="libelle"
            option-value="id"
            class="min-h-[44px] min-w-[12rem]"
        />
      </div>
    </div>

    <div v-if="chargement" class="scolarite__grille" aria-hidden="true">
      <Skeleton height="28rem" border-radius="18px"/>
      <Skeleton height="18rem" border-radius="18px"/>
    </div>

    <div v-else-if="erreur" class="scolarite__message" role="alert">
      <p class="m-0!">Vos notes et absences n'ont pas pu être chargées.</p>
      <Button label="Réessayer" icon="pi pi-refresh" class="min-h-[44px]!" @click="charger"/>
    </div>

    <p v-else-if="!semestre" class="scolarite__message">Aucune inscription n'est enregistrée pour l'instant.</p>

    <div v-else class="scolarite__grille">
      <BlocAccueil titre="Notes" icone="pi pi-chart-bar" teinte="tp" class="scolarite__notes">
        <template #actions>
          <SelectButton
              v-model="affichage"
              :options="AFFICHAGES"
              option-label="libelle"
              option-value="id"
              :allow-empty="false"
              aria-label="Affichage des notes"
              class="scolarite__affichage"
          />
        </template>
        <SyntheseSemestre v-if="affichage === 'synthese'" :semestre="semestre"/>
        <NotesParUe v-else-if="affichage === 'ue'" :semestre="semestre"/>
        <ToutesLesNotes v-else :semestre="semestre"/>
      </BlocAccueil>

      <AbsencesSemestre :semestre="semestre" class="scolarite__absences"/>
    </div>
  </section>
</template>

<style scoped>
.scolarite {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.scolarite__entete {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

.scolarite__titre {
  margin: 0 !important;
  font-size: 1.75rem !important;
  font-weight: 700;
}

.scolarite__aide {
  margin: 0.25rem 0 0;
  color: var(--p-text-muted-color);
}

.scolarite__semestre {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-weight: 600;
}

.scolarite__message {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1rem;
  margin: 0;
}

/* Les notes d'abord, les absences en second : à côté en bureau, dessous sur téléphone. */
.scolarite__grille {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 1.25rem;
  align-items: start;
}

/* Sur téléphone, le menu prend la largeur de la carte, chaque vue sur une ligne. */
.scolarite__affichage {
  display: flex;
  width: 100%;
}

.scolarite__affichage :deep(.p-togglebutton) {
  flex: 1 1 auto;
  white-space: nowrap;
}

@media (max-width: 639px) {
  .scolarite__affichage :deep(.p-togglebutton),
  .scolarite__affichage :deep(.p-togglebutton-content) {
    padding-inline: 0.375rem;
  }
}

@media (min-width: 640px) {
  .scolarite__affichage {
    width: auto;
  }
}

@media (min-width: 1200px) {
  .scolarite__grille {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
  }
}
</style>
