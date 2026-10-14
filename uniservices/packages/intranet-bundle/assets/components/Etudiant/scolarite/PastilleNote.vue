<script setup>
// La pastille de l'accueil : teinte pâle et texte foncé du même type, selon le seuil (sous 10, de
// 10 à 14, à partir de 14). Un statut (absence justifiée, à venir) se lit en atténué, sans pastille.
defineProps({
  ton: {type: String, default: null},
  petite: {type: Boolean, default: false},
});
</script>

<template>
  <span :class="['pastille', `pastille--${ton ?? 'statut'}`, { 'pastille--petite': petite }]"><slot/></span>
</template>

<style scoped>
.pastille {
  display: inline-block;
  padding: 0 0.625rem;
  border-radius: 999px;
  font-weight: 600;
  line-height: 1.75rem;
  white-space: nowrap;
}

.pastille--petite {
  font-size: 0.875rem;
  line-height: 1.5rem;
}

.pastille--faible {
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.pastille--moyenne {
  background: var(--p-seance-cm-background);
  color: var(--p-seance-cm-color);
}

.pastille--bonne {
  background: var(--p-seance-tp-background);
  color: var(--p-seance-tp-color);
}

.pastille--statut {
  padding: 0;
  font-weight: 400;
  white-space: normal;
  color: var(--p-text-muted-color);
}
</style>
