<script setup lang="ts">
import type { DonneesProgressionPortfolio } from '~~/types/domain'

const props = defineProps<{ donnees: DonneesProgressionPortfolio }>()

const pourcentage = computed(() =>
  props.donnees.target === 0
    ? 0
    : Math.round((props.donnees.validated / props.donnees.target) * 100),
)
</script>

<template>
  <div>
    <!--
      progressbar plutot qu'une simple barre coloree : la valeur doit etre lisible
      sans voir la couleur, d'ou aussi le pourcentage en texte.
    -->
    <div
      class="jauge"
      role="progressbar"
      :aria-valuenow="pourcentage"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="`Progression du portfolio : ${pourcentage} pour cent`"
    >
      <div class="remplissage" :style="{ width: `${pourcentage}%` }" />
    </div>
    <p>{{ pourcentage }} % &mdash; {{ donnees.validated }} sur {{ donnees.target }}</p>
  </div>
</template>

<style scoped>
.jauge {
  height: 0.75rem;
  border: 1px solid var(--couleur-bordure);
  border-radius: var(--rayon);
  overflow: hidden;
}

.remplissage {
  height: 100%;
  background: var(--couleur-primaire);
}

p {
  margin: 0.5rem 0 0;
}
</style>
