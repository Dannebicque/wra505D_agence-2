<script setup>
import {computed, onMounted, onUnmounted, ref, watch} from 'vue';
import {useAnneeUnivStore, useUsersStore} from '@stores';
import {getEdtEventsService, getEtudiantScolariteSemestresService} from '@requests';
import {formatDelai, getSituationDuJour, minutesEntre} from '@/utils/prochainCours.js';

const userStore = useUsersStore();
const anneeUnivStore = useAnneeUnivStore();

const events = ref([]);
const isLoading = ref(true);
const hasError = ref(false);
const now = ref(new Date());
let horloge = null;

const situation = computed(() => getSituationDuJour(events.value, now.value));

const formatHeure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});

const formatDateTimeAttr = (date) => {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const jourCourant = () => formatDateTimeAttr(new Date()).slice(0, 10);

const getCoursDuJour = async (anneeUniversitaire) => {
  const scolariteSemestres = await getEtudiantScolariteSemestresService({
    etudiant: userStore.user.id,
    anneeUniversitaire,
  });
  const groupeIds = [...new Set(scolariteSemestres.flatMap(ss => (ss.groupes || []).map(g => g.id)))];

  // Le filtre groupe de l'API ne supporte qu'un identifiant par requête.
  const parGroupe = await Promise.all(
      groupeIds.map(groupe => getEdtEventsService({groupe, anneeUniversitaire, day: jourCourant()}))
  );
  return parGroupe.flat().filter(Boolean);
};

// L'année universitaire peut arriver après le montage : sans elle, on afficherait à tort
// une journée sans cours.
watch(() => anneeUnivStore.selectedAnneeUniv?.id, async (anneeUniversitaire) => {
  if (!anneeUniversitaire) {
    return;
  }
  isLoading.value = true;
  hasError.value = false;
  try {
    events.value = await getCoursDuJour(anneeUniversitaire);
  } catch {
    hasError.value = true;
  } finally {
    isLoading.value = false;
  }
}, {immediate: true});

onMounted(() => {
  horloge = setInterval(() => {
    now.value = new Date();
  }, 30000);
});

onUnmounted(() => {
  clearInterval(horloge);
});
</script>

<template>
  <section aria-labelledby="maintenant-titre" class="card mb-4! p-4! lg:p-6!">
    <h2 id="maintenant-titre" class="text-xl! font-semibold mb-3! flex items-center gap-2">
      <i class="pi pi-clock text-primary-500" aria-hidden="true"/>
      Maintenant
    </h2>

    <div v-if="isLoading">
      <Skeleton width="60%" class="mb-2"/>
      <Skeleton width="40%"/>
    </div>

    <p v-else-if="hasError" class="m-0!">
      L'emploi du temps est indisponible pour le moment.
    </p>

    <p v-else-if="!situation.aDesCours" class="m-0! font-semibold">
      Aucun cours aujourd'hui.
    </p>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-if="situation.enCours">
        <h3 class="m-0! text-sm! font-normal!">En cours</h3>
        <p class="m-0! text-lg font-semibold">
          {{ situation.enCours.codeModule }} - {{ situation.enCours.libModule }}
        </p>
        <p class="m-0!">Salle {{ situation.enCours.salle || 'non précisée' }}</p>
        <p class="m-0!">
          Jusqu'à <time :datetime="formatDateTimeAttr(situation.enCours.fin)">{{ formatHeure(situation.enCours.fin) }}</time>,
          encore {{ formatDelai(minutesEntre(now, situation.enCours.fin)) }}
        </p>
      </div>

      <div v-if="situation.prochain">
        <h3 class="m-0! text-sm! font-normal!">Prochain cours</h3>
        <p class="m-0! text-lg font-semibold">
          {{ situation.prochain.codeModule }} - {{ situation.prochain.libModule }}
        </p>
        <p class="m-0!">Salle {{ situation.prochain.salle || 'non précisée' }}</p>
        <p class="m-0!">
          À <time :datetime="formatDateTimeAttr(situation.prochain.debut)">{{ formatHeure(situation.prochain.debut) }}</time>,
          dans {{ formatDelai(minutesEntre(now, situation.prochain.debut)) }}
        </p>
      </div>

      <p v-else class="m-0! font-semibold self-center">
        {{ situation.enCours ? 'Pas d\'autre cours ensuite aujourd\'hui.' : 'Plus de cours aujourd\'hui.' }}
      </p>
    </div>
  </section>
</template>
