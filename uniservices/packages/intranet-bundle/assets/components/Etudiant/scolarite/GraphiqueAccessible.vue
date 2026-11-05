<script setup>
import {ref, useId} from 'vue';

// Un graphique ne se lit ni au clavier ni au lecteur d'écran : le même contenu existe en tableau,
// un bouton passe de l'un à l'autre, et le canvas porte un résumé en une phrase.
defineProps({
  titre: {type: String, required: true},
  resume: {type: String, required: true},
  niveau: {type: Number, default: 2},
});

const enTableau = ref(false);
const id = useId();
</script>

<template>
  <section class="graphique" :aria-labelledby="`${id}-titre`">
    <div class="graphique__entete">
      <component :is="`h${niveau}`" :id="`${id}-titre`" class="graphique__titre">{{ titre }}</component>
      <Button
          :label="enTableau ? 'Voir le graphique' : 'Voir en tableau'"
          :icon="enTableau ? 'pi pi-chart-bar' : 'pi pi-table'"
          text
          size="small"
          class="min-h-[44px]!"
          :aria-controls="`${id}-contenu`"
          @click="enTableau = !enTableau"
      />
    </div>
    <slot name="filtres"/>
    <div :id="`${id}-contenu`">
      <slot v-if="enTableau" name="tableau"/>
      <div v-else role="img" :aria-label="resume" class="graphique__zone">
        <slot/>
      </div>
    </div>
  </section>
</template>

<style scoped>
.graphique {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 0;
  padding: 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 14px;
}

.graphique__entete {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.graphique__titre {
  margin: 0 !important;
  font-size: 1.0625rem !important;
  font-weight: 600;
}

.graphique__zone {
  position: relative;
}

.graphique :deep(table) {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9375rem;
}

.graphique :deep(th),
.graphique :deep(td) {
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid var(--p-content-border-color);
  text-align: left;
}

.graphique :deep(td.nombre),
.graphique :deep(th.nombre) {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
</style>
