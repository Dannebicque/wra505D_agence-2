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
  <div v-if="departements.length > 0" class="@container">
    <ul class="list-none m-0 p-0 grid grid-cols-1 @md:grid-cols-2 gap-3">
      <li
          v-for="departement in departements"
          :key="departement.id"
          class="rounded-xl border p-3 flex flex-col gap-3"
          :class="departement.id === data.departementEtudiantId
            ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/30'
            : 'border-surface-200 dark:border-surface-700'"
      >
        <div class="flex items-center gap-3">
          <span class="w-10 h-10 shrink-0 rounded-full hidden @3xs:flex items-center justify-center bg-surface-100 dark:bg-surface-800" aria-hidden="true">
            <i class="pi pi-building text-primary-500"/>
          </span>
          <div class="flex flex-col min-w-0 break-words">
            <span class="font-semibold">{{ departement.libelle }}</span>
            <span v-if="departement.id === data.departementEtudiantId" class="text-sm">Votre département</span>
          </div>
        </div>
        <div v-if="departement.telephone || departement.siteWeb" class="flex flex-wrap gap-2">
          <a
              v-if="departement.telephone"
              :href="telephoneHref(departement.telephone)"
              class="touch-target gap-1.5 rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-3 py-1.5 text-sm focus-visible:outline-2! focus-visible:outline-offset-2! focus-visible:outline-primary-600!"
          >
            <i class="pi pi-phone" aria-hidden="true"/>
            <span class="sr-only">Téléphone du département {{ departement.libelle }} :</span>
            {{ departement.telephone }}
          </a>
          <a
              v-if="departement.siteWeb"
              :href="departement.siteWeb"
              target="_blank"
              rel="noopener"
              class="touch-target gap-1.5 rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-3 py-1.5 text-sm focus-visible:outline-2! focus-visible:outline-offset-2! focus-visible:outline-primary-600!"
          >
            <i class="pi pi-external-link" aria-hidden="true"/>
            Site web<span class="sr-only"> du département {{ departement.libelle }}, nouvel onglet</span>
          </a>
        </div>
        <p v-else class="m-0! text-sm">Coordonnées non renseignées</p>
      </li>
    </ul>
  </div>
  <p v-else class="m-0!">Aucun département à afficher.</p>
</template>
