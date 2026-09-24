<script setup>
import {computed} from 'vue';
import {ordonnerDepartements} from '@/utils/contacts.js';

const props = defineProps({
  data: {
    type: Object,
    default: () => ({items: [], departementEtudiantId: null}),
  },
});

const departements = computed(() => ordonnerDepartements(props.data?.items || [], props.data?.departementEtudiantId));

const telephoneHref = (telephone) => `tel:${telephone.replace(/[^\d+]/g, '')}`;
</script>

<template>
  <ul v-if="departements.length > 0" class="list-none m-0 p-0 flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
    <li
        v-for="departement in departements"
        :key="departement.id"
        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2"
    >
      <p class="m-0! font-semibold flex items-center gap-2">
        {{ departement.libelle }}
        <span v-if="departement.id === data.departementEtudiantId" class="text-sm font-normal">
          (votre département)
        </span>
      </p>
      <div class="flex flex-wrap items-center gap-x-4">
        <a v-if="departement.telephone" :href="telephoneHref(departement.telephone)" class="underline touch-target gap-1">
          <i class="pi pi-phone" aria-hidden="true"/>
          <span class="sr-only">Téléphone du département {{ departement.libelle }} :</span>
          {{ departement.telephone }}
        </a>
        <a v-if="departement.siteWeb" :href="departement.siteWeb" target="_blank" rel="noopener" class="underline touch-target gap-1">
          <i class="pi pi-external-link" aria-hidden="true"/>
          Site web<span class="sr-only"> du département {{ departement.libelle }}, nouvel onglet</span>
        </a>
        <span v-if="!departement.telephone && !departement.siteWeb" class="text-sm">
          Coordonnées non renseignées
        </span>
      </div>
    </li>
  </ul>
  <p v-else class="m-0!">Aucun département à afficher.</p>
</template>
