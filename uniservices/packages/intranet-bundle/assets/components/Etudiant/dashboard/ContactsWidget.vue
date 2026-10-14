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
  <div v-if="departements.length > 0" class="contacts @container">
    <ul class="list-none m-0 p-0 grid grid-cols-1 @xl:grid-cols-2 gap-2">
      <li
          v-for="departement in departements"
          :key="departement.id"
          class="contact"
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
              class="contact__action"
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
              class="contact__action"
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
/* Les cartes des départements se partagent toute la hauteur du bloc, qui suit celle de sa rangée. */
.contacts {
  display: flex;
  flex: 1;
}

.contacts > ul {
  flex: 1;
  grid-auto-rows: 1fr;
}

.contact {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.625rem;
  padding: 0.75rem;
  border-radius: 14px;
  border: 1px solid var(--p-content-border-color);
}

.contact__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: 999px;
  background: var(--p-content-hover-background);
  color: var(--p-text-muted-color);
}

.contact__sien {
  font-size: 0.9375rem;
  color: var(--p-text-muted-color);
}

/* Une pastille de 32 px ; la zone de toucher garde 44 px et plus, débordant de 7 px en haut et en bas. */
.contact__action {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  min-height: 2.25rem;
  padding: 0 0.75rem 0 0.25rem;
  border-radius: 999px;
  font-size: 1rem;
  font-variant-numeric: tabular-nums;
  background: var(--p-content-hover-background);
  color: var(--p-text-color);
  transition: background-color 150ms ease, color 150ms ease;
}

/* L'icône dans sa pastille, centrée : elle s'aligne sur le texte quelle que soit la taille. */
.contact__action::before {
  content: '';
  position: absolute;
  inset: -7px 0;
}

.contact__action i {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.625rem;
  height: 1.625rem;
  border-radius: 999px;
  font-size: 0.8125rem;
  line-height: 1;
  background: var(--p-content-background);
  color: var(--p-primary-color);
}

.contact__action:hover {
  background: var(--p-seance-cm-background);
  color: var(--p-seance-cm-color);
}

.contact__action:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
  .contact__action {
    transition: none;
  }
}
</style>
