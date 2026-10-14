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
    <ul class="list-none m-0 p-0 grid grid-cols-1 @xl:grid-cols-2 gap-2">
      <li
          v-for="departement in departements"
          :key="departement.id"
          :class="['contact', { 'contact--sien': departement.id === data.departementEtudiantId }]"
      >
        <div class="flex items-center gap-3">
          <span class="contact__icone" aria-hidden="true">
            <i class="pi pi-building" aria-hidden="true"/>
          </span>
          <div class="flex flex-col min-w-0 break-words">
            <span class="font-semibold">{{ departement.libelle }}</span>
            <span v-if="departement.id === data.departementEtudiantId" class="contact__sien">Votre département</span>
          </div>
        </div>
        <div v-if="departement.telephone || departement.siteWeb" class="flex flex-wrap gap-2">
          <a
              v-if="departement.telephone"
              :href="telephoneHref(departement.telephone)"
              class="contact__action touch-target"
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
              class="contact__action touch-target"
          >
            <i class="pi pi-external-link" aria-hidden="true"/>
            Site web<span class="sr-only"> du département {{ departement.libelle }}, nouvel onglet</span>
          </a>
        </div>
        <p v-else class="m-0!">Coordonnées non renseignées</p>
      </li>
    </ul>
  </div>
  <p v-else class="m-0!">Aucun département à afficher.</p>
</template>

<style scoped>
.contact {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  padding: 0.75rem;
  border-radius: 14px;
  background: var(--p-content-hover-background);
}

.contact--sien {
  background: var(--p-seance-cm-background);
}

.contact__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border-radius: 999px;
  background: var(--p-content-background);
  color: var(--p-seance-cm-color);
}

.contact__sien {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--p-seance-cm-color);
}

.contact__action {
  gap: 0.375rem;
  padding: 0.25rem 0.875rem;
  border-radius: 999px;
  font-size: 1rem;
  background: var(--p-content-background);
  color: var(--p-text-color);
  border: 1px solid var(--ui-border-color);
}

.contact__action:hover {
  border-color: var(--p-primary-color);
}

.contact__action:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}
</style>
