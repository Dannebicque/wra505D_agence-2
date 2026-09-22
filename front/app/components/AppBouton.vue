<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'button' | 'submit'
    variante?: 'primaire' | 'secondaire'
    pleineLargeur?: boolean
  }>(),
  { type: 'button', variante: 'primaire', pleineLargeur: false },
)
</script>

<template>
  <!--
    Le bouton n'est jamais desactive pendant la saisie : un bouton inerte ne dit
    pas ce qui manque. Les erreurs sont annoncees apres la soumission.
  -->
  <button :type="type" :class="[variante, { large: pleineLargeur }]">
    <slot />
  </button>
</template>

<style scoped>
button {
  min-height: var(--cible-tactile);
  padding: 0 1rem;
  border: 1px solid transparent;
  border-radius: var(--rayon);
  font: inherit;
  font-weight: 500;
  cursor: pointer;
}

button[aria-busy='true'] {
  cursor: progress;
}

.primaire {
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  border-color: var(--couleur-sur-primaire);
}

.secondaire {
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  border-color: var(--couleur-bordure);
}

.large {
  width: 100%;
}
</style>
