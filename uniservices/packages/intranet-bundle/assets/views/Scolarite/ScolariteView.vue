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
  <!-- Comme l'agenda : le titre reste pour les lecteurs d'écran, les onglets disent déjà la page,
       et le semestre se choisit dans la carte, au même niveau qu'eux. -->
  <section class="scolarite col-span-12 mt-10 md:mt-0">
    <h1 class="sr-only">Notes et absences</h1>

    <div v-if="chargement" class="scolarite__chargement" aria-hidden="true">
      <div class="scolarite__barre">
        <Skeleton width="15rem" height="3.25rem" border-radius="999px"/>
        <Skeleton width="13rem" height="2.75rem" border-radius="999px"/>
      </div>
      <div class="scolarite__squelette">
        <Skeleton height="8.5rem" border-radius="18px" class="scolarite__squelette-phare"/>
        <Skeleton v-for="rang in 3" :key="rang" height="8.5rem" border-radius="14px"/>
        <Skeleton height="16rem" border-radius="14px" class="scolarite__squelette-graphique"/>
        <Skeleton height="16rem" border-radius="14px" class="scolarite__squelette-graphique"/>
      </div>
    </div>

    <div v-else-if="erreur" class="scolarite__message" role="alert">
      <p class="m-0!">Vos notes et absences n'ont pas pu être chargées.</p>
      <Button label="Réessayer" icon="pi pi-refresh" class="min-h-[44px]!" @click="charger"/>
    </div>

    <p v-else-if="!semestre" class="scolarite__message">Aucune inscription n'est enregistrée pour l'instant.</p>

    <div v-else>
      <Tabs v-model:value="onglet" class="scolarite__onglets">
        <div class="scolarite__barre">
          <TabList>
            <Tab value="notes" class="scolarite__onglet">
              <i class="pi pi-chart-bar" aria-hidden="true"></i> Notes
            </Tab>
            <Tab value="absences" class="scolarite__onglet">
              <i class="pi pi-calendar-times" aria-hidden="true"></i> Absences
              <span v-if="aJustifier" class="scolarite__compte">{{ aJustifier }}<span class="sr-only"> à justifier</span></span>
            </Tab>
          </TabList>
          <SelecteurSemestre v-model="idSemestre" :semestres="semestres"/>
        </div>
        <!-- Seul l'onglet ouvert est monté : en changer, ou changer de semestre, rejoue l'entrée des
             chiffres et des graphiques. -->
        <TabPanels>
          <TabPanel value="notes">
            <Transition name="onglet" appear>
              <NotesSemestre v-if="onglet === 'notes'" :key="semestre.id" :semestre="semestre" @voir-absences="onglet = 'absences'"/>
            </Transition>
          </TabPanel>
          <TabPanel value="absences">
            <Transition name="onglet" appear>
              <AbsencesSemestre v-if="onglet === 'absences'" :key="semestre.id" :semestre="semestre" @justifier="absenceAJustifier = $event"/>
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

.scolarite__message {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1rem;
  margin: 0;
}

/* La barre de la page, une carte comme celles de l'accueil : les onglets en pilule à gauche, comme
   Jour / Semaine dans l'agenda, et le semestre à droite. */
.scolarite__barre {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem 1rem;
  padding: 0.625rem 0.75rem 0.625rem 0.625rem;
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 4px 12px -4px rgb(0 0 0 / 0.06);
}

.scolarite__onglets,
.scolarite__onglets :deep(.p-tablist),
.scolarite__onglets :deep(.p-tablist-content),
.scolarite__onglets :deep(.p-tabpanels) {
  background: transparent;
}

.scolarite__onglets :deep(.p-tablist-tab-list) {
  gap: 0.25rem;
  padding: 0.25rem;
  border: none;
  border-radius: 999px;
  background: var(--p-content-hover-background);
}

.scolarite__onglets :deep(.p-tablist-active-bar) {
  display: none;
}

/* L'onglet ouvert se détache en pastille blanche, texte violet : on sait où l'on est. */
.scolarite__onglet {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  padding: 0 1.125rem;
  border: none !important;
  border-radius: 999px;
  font-size: 1rem;
  transition: background-color 150ms ease, color 150ms ease, box-shadow 150ms ease;
}

.scolarite__onglet[data-p-active="true"] {
  background: var(--p-content-background);
  color: var(--p-primary-color);
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.12);
}

.scolarite__onglet:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.scolarite__chargement {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.scolarite__squelette {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.scolarite__squelette-graphique {
  grid-column: span 2;
}

@media (max-width: 1199px) {
  .scolarite__squelette {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .scolarite__squelette-phare,
  .scolarite__squelette-graphique {
    grid-column: 1 / -1;
  }
}

/* Sur téléphone, le semestre passe sous les onglets, sur toute la largeur. */
@media (max-width: 639px) {
  .scolarite__barre :deep(.p-tablist),
  .scolarite__barre :deep(.p-tablist-tab-list) {
    width: 100%;
  }

  .scolarite__onglet {
    flex: 1;
    justify-content: center;
  }

  .scolarite__barre :deep(.selecteur),
  .scolarite__barre :deep(.selecteur__liste) {
    width: 100%;
  }
}

.scolarite__onglets :deep(.p-tabpanels) {
  padding: 1.25rem 0 0;
}

.scolarite__onglets :deep(.p-tabpanel:focus-visible) {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 4px;
  border-radius: 18px;
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
