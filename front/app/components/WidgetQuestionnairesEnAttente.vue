<script setup lang="ts">
import type { DonneesQuestionnairesEnAttente } from '~~/types/domain'

defineProps<{ donnees: DonneesQuestionnairesEnAttente }>()
</script>

<template>
  <ul v-if="donnees.items.length">
    <li v-for="(questionnaire, index) in donnees.items" :key="index">{{ questionnaire }}</li>
  </ul>
  <p v-else>Aucun questionnaire en attente.</p>
</template>

<style scoped>
ul {
  margin: 0;
  padding-left: 1.25rem;
}

p {
  margin: 0;
}
</style>
