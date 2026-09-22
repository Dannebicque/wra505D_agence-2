<script setup lang="ts">
import type { DonneesEmploiDuTemps } from '~~/types/domain'

defineProps<{ donnees: DonneesEmploiDuTemps }>()
</script>

<template>
  <div>
    <p class="jour">{{ donnees.todayLabel }}</p>

    <ul v-if="donnees.items.length">
      <li v-for="(cours, index) in donnees.items" :key="index">
        <span class="heure">{{ cours.heure }}</span>
        <span>{{ cours.cours }}</span>
      </li>
    </ul>
    <p v-else>Aucun cours aujourd'hui.</p>
  </div>
</template>

<style scoped>
.jour {
  margin: 0 0 0.75rem;
  color: var(--couleur-texte-attenue);
}

ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

li {
  display: flex;
  gap: 0.75rem;
}

.heure {
  font-variant-numeric: tabular-nums;
  font-weight: 500;
  white-space: nowrap;
}

p {
  margin: 0;
}
</style>
