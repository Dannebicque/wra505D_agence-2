<script setup lang="ts">
defineProps<{ titre: string; colonnes: number }>()
</script>

<template>
  <!--
    Chaque widget est une section titree : la liste des widgets devient une liste
    de reperes navigables au clavier et au lecteur d'ecran.
  -->
  <section class="widget" :style="{ '--colonnes': colonnes }">
    <h3>{{ titre }}</h3>
    <slot />
  </section>
</template>

<style scoped>
.widget {
  grid-column: span min(var(--colonnes), 4);
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  border-radius: var(--rayon);
  padding: 1rem;
}

h3 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
}

@media (max-width: 48rem) {
  .widget {
    grid-column: 1 / -1;
  }
}
</style>
