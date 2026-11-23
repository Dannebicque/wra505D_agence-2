<script setup>
import {formatDate} from '@/utils/scolarite.js';
import {tailleFichier} from '@/utils/ficheMatiere.js';

defineProps({
  documents: {type: Array, required: true},
});

const details = (document) => [
  document.modifieLe ? `Mis à jour le ${formatDate(document.modifieLe)}` : null,
  tailleFichier(document.taille),
].filter(Boolean).join(' · ');
</script>

<template>
  <p v-if="!documents.length" class="m-0!">Aucun document n'est encore rattaché à cette matière.</p>
  <!-- Chaque document ouvre sa fiche dans Documents, avec son aperçu et son téléchargement. -->
  <ul v-else class="documents">
    <li v-for="document in documents" :key="document.id">
      <router-link :to="{path: '/documents', query: {document: document.id}}" class="document">
        <span class="document__icone" aria-hidden="true"><i class="pi pi-file"></i></span>
        <span class="document__texte">
          <span class="document__titre">{{ document.titre }}</span>
          <span class="document__details">{{ details(document) }}</span>
        </span>
        <i class="pi pi-angle-right document__fleche" aria-hidden="true"></i>
      </router-link>
    </li>
  </ul>
</template>

<style scoped>
.documents {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.document {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  min-height: 44px;
  padding: 0.75rem 1rem;
  border-radius: 14px;
  color: var(--p-text-color);
  background: var(--p-content-hover-background);
  text-decoration: none;
  transition: background-color 150ms ease;
}

.document:hover {
  background: var(--p-notification-document-background);
}

.document:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.document__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border-radius: 999px;
  background: var(--p-notification-document-background);
  color: var(--p-notification-document-color);
}

.document__texte {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.document__titre {
  font-weight: 600;
  overflow-wrap: anywhere;
}

.document__details {
  font-size: 1rem;
  color: var(--p-text-muted-color);
}

.document__fleche {
  color: var(--p-text-muted-color);
}
</style>
