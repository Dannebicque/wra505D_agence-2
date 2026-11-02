<script setup>
import {computed, nextTick, onMounted, ref, watch} from 'vue';
import {onBeforeRouteLeave, useRoute, useRouter} from 'vue-router';
import {getMaScolariteService} from '@requests';
import {absencesParEtat, semestreParDefaut} from '@/utils/scolarite.js';
import SelecteurSemestre from '@/components/Etudiant/scolarite/SelecteurSemestre.vue';
import NotesSemestre from '@/components/Etudiant/scolarite/NotesSemestre.vue';
import AbsencesSemestre from '@/components/Etudiant/scolarite/AbsencesSemestre.vue';
import JustificatifDrawer from '@/components/Etudiant/scolarite/JustificatifDrawer.vue';

const route = useRoute();
const router = useRouter();

const releve = ref(null);
const chargement = ref(true);
const erreur = ref(false);

const ONGLETS = ['notes', 'absences'];
const onglet = ref(ONGLETS.includes(route.query.onglet) ? route.query.onglet : 'notes');
const idSemestre = ref(null);
const absenceAJustifier = ref(null);

const semestres = computed(() => releve.value?.semestres ?? []);
const semestre = computed(() => semestres.value.find(candidat => candidat.id === idSemestre.value) ?? null);
const aJustifier = computed(() => {
  const groupes = absencesParEtat(semestre.value?.absences.liste ?? []);
  return groupes.aJustifier.length + groupes.refusees.length;
});

const charger = async () => {
  chargement.value = releve.value === null;
  erreur.value = false;
  try {
    releve.value = await getMaScolariteService();
    idSemestre.value = semestreParDefaut(semestres.value, idSemestre.value ?? route.query.semestre)?.id ?? null;
  } catch {
    erreur.value = true;
  } finally {
    chargement.value = false;
  }
};
onMounted(charger);

// Un dépôt fait passer l'absence « en attente » : le relevé se relit, l'onglet reste ouvert, et le
// focus suit l'absence, son bouton « Justifier » ayant disparu.
const apresDepot = async () => {
  absenceAJustifier.value = null;
  await charger();
  await nextTick();
  document.getElementById('absences-enAttente')?.focus();
};

// Semestre et onglet vivent dans l'adresse, comme la semaine de l'agenda. Une fois la page
// quittée, réécrire l'adresse annulerait la navigation en cours.
let quittee = false;
onBeforeRouteLeave(() => {
  quittee = true;
});
watch([idSemestre, onglet], ([semestreChoisi, ongletChoisi]) => {
  if (quittee || semestreChoisi === null) {
    return;
  }
  router.replace({query: {semestre: String(semestreChoisi), ...(ongletChoisi === 'notes' ? {} : {onglet: ongletChoisi})}});
});
</script>

<template>
  <section class="scolarite col-span-12 mt-10 md:mt-0">
    <div class="scolarite__entete">
      <h1 class="scolarite__titre">Notes et absences</h1>
      <SelecteurSemestre v-if="semestres.length" v-model="idSemestre" :semestres="semestres"/>
    </div>

    <div v-if="chargement" class="flex flex-col gap-4" aria-hidden="true">
      <Skeleton height="3rem" border-radius="14px"/>
      <Skeleton height="24rem" border-radius="18px"/>
    </div>

    <div v-else-if="erreur" class="scolarite__message" role="alert">
      <p class="m-0!">Vos notes et absences n'ont pas pu être chargées.</p>
      <Button label="Réessayer" icon="pi pi-refresh" class="min-h-[44px]!" @click="charger"/>
    </div>

    <p v-else-if="!semestre" class="scolarite__message">Aucune inscription n'est enregistrée pour l'instant.</p>

    <div v-else class="scolarite__carte">
      <Tabs v-model:value="onglet">
        <TabList>
          <Tab value="notes" class="scolarite__onglet">
            <i class="pi pi-chart-bar" aria-hidden="true"></i> Notes
          </Tab>
          <Tab value="absences" class="scolarite__onglet">
            <i class="pi pi-calendar-times" aria-hidden="true"></i> Absences
            <span v-if="aJustifier" class="scolarite__compte">{{ aJustifier }}<span class="sr-only"> à justifier</span></span>
          </Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="notes">
            <Transition name="onglet" appear>
              <NotesSemestre :key="semestre.id" :semestre="semestre"/>
            </Transition>
          </TabPanel>
          <TabPanel value="absences">
            <Transition name="onglet" appear>
              <AbsencesSemestre :key="semestre.id" :semestre="semestre" @justifier="absenceAJustifier = $event"/>
            </Transition>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </div>

    <JustificatifDrawer :absence="absenceAJustifier" @fermer="absenceAJustifier = null" @depose="apresDepot"/>
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
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.scolarite__titre {
  margin: 0 !important;
  font-size: 1.75rem !important;
  font-weight: 700;
}

.scolarite__message {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1rem;
  margin: 0;
}

.scolarite__carte {
  padding: 0.5rem 1.25rem 1.25rem;
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 4px 12px -4px rgb(0 0 0 / 0.06);
}

.scolarite__carte :deep(.p-tablist-tab-list) {
  background: transparent;
}

.scolarite__carte :deep(.p-tabpanels) {
  padding: 1.25rem 0 0;
  background: transparent;
}

.scolarite__onglet {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  font-size: 1rem;
}

.scolarite__compte {
  min-width: 1.5rem;
  padding: 0 0.4rem;
  border-radius: 999px;
  font-size: 0.8125rem;
  line-height: 1.5rem;
  text-align: center;
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

/* Changer d'onglet ou de semestre fait entrer le contenu en douceur, sans déplacer la page. */
.onglet-enter-active {
  transition: opacity 220ms ease, transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.onglet-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

@media (prefers-reduced-motion: reduce) {
  .onglet-enter-active {
    transition: none;
  }
}
</style>
