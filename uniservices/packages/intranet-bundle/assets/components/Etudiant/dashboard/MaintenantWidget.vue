<script setup>
import {computed, onMounted, onUnmounted, ref} from 'vue';
import {formatDelai, getSituationDuJour, minutesEntre} from '@/utils/prochainCours.js';

// Les cours du jour, lus par l'agenda de la même carte ; null tant qu'il ne les a pas.
const props = defineProps({
  cours: {type: Array, default: null},
});

const now = ref(new Date());
let horloge = null;

const situation = computed(() => getSituationDuJour(props.cours ?? [], now.value));

const formatHeure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});

const formatDateTimeAttr = (date) => {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

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
  <!-- Une journée sans cours est dite une seule fois, par l'agenda en dessous. -->
  <div v-if="cours === null" aria-hidden="true">
    <Skeleton width="60%" class="mb-2"/>
    <Skeleton width="40%"/>
  </div>

  <div v-else-if="situation.aDesCours" class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div v-if="situation.enCours">
      <h3 class="m-0! text-sm! font-normal!">En cours</h3>
      <p class="m-0! text-lg font-semibold">
        {{ situation.enCours.codeModule }} - {{ situation.enCours.libModule }}
      </p>
      <p class="m-0! flex items-center gap-1">
        <i class="pi pi-map-marker" aria-hidden="true"/>
        <span class="sr-only">Lieu :</span>
        {{ situation.enCours.salle || 'Lieu non précisé' }}
      </p>
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
      <p class="m-0! flex items-center gap-1">
        <i class="pi pi-map-marker" aria-hidden="true"/>
        <span class="sr-only">Lieu :</span>
        {{ situation.prochain.salle || 'Lieu non précisé' }}
      </p>
      <p class="m-0!">
        À <time :datetime="formatDateTimeAttr(situation.prochain.debut)">{{ formatHeure(situation.prochain.debut) }}</time>,
        dans {{ formatDelai(minutesEntre(now, situation.prochain.debut)) }}
      </p>
    </div>

    <p v-else class="m-0! font-semibold self-center">
      {{ situation.enCours ? 'Pas d\'autre cours ensuite aujourd\'hui.' : 'Plus de cours aujourd\'hui.' }}
    </p>
  </div>
</template>
