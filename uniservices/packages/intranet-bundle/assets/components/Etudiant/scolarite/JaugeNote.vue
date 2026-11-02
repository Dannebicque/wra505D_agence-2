<script setup>
import {computed} from 'vue';
import {tonNote} from '@/utils/accueil.js';

// Une moyenne sur 20, le seuil de 10 marqué, et la part perdue en absences hachurée au bout :
// on lit d'un coup d'œil si l'on est au-dessus, et ce que les absences ont coûté. Décorative : la
// valeur est écrite à côté.
const props = defineProps({
  moyenne: {type: Number, default: null},
  sansPenalite: {type: Number, default: null},
});

const pourcent = (valeur) => `${Math.min(100, Math.max(0, valeur / 20 * 100))}%`;
const ton = computed(() => tonNote(props.moyenne) ?? 'vide');
const perte = computed(() => (props.moyenne !== null && props.sansPenalite !== null && props.sansPenalite > props.moyenne
  ? props.sansPenalite - props.moyenne
  : 0));
</script>

<template>
  <span :class="['jauge', `jauge--${ton}`]" aria-hidden="true">
    <span v-if="moyenne !== null" class="jauge__valeur" :style="{ width: pourcent(moyenne) }"></span>
    <span v-if="perte" class="jauge__perte" :style="{ left: pourcent(moyenne), width: pourcent(perte) }"></span>
    <span class="jauge__seuil"></span>
  </span>
</template>

<style scoped>
.jauge {
  position: relative;
  display: block;
  height: 0.5rem;
  border-radius: 999px;
  background: var(--p-content-hover-background);
}

.jauge__valeur {
  position: absolute;
  inset: 0 auto 0 0;
  border-radius: 999px;
  background: var(--jauge-couleur);
  transition: width 400ms cubic-bezier(0.22, 1, 0.36, 1);
}

.jauge__perte {
  position: absolute;
  top: 0;
  bottom: 0;
  border-radius: 0 999px 999px 0;
  background: repeating-linear-gradient(135deg, var(--jauge-couleur) 0 2px, transparent 2px 5px);
  opacity: 0.55;
}

/* Le seuil de 10 dépasse la barre : un repère qui se voit même quand elle est vide. */
.jauge__seuil {
  position: absolute;
  left: 50%;
  top: -0.25rem;
  bottom: -0.25rem;
  width: 2px;
  margin-left: -1px;
  border-radius: 1px;
  background: var(--p-text-muted-color);
}

.jauge--faible {
  --jauge-couleur: var(--p-seance-evaluation-color);
}

.jauge--moyenne {
  --jauge-couleur: var(--p-seance-cm-color);
}

.jauge--bonne {
  --jauge-couleur: var(--p-seance-tp-color);
}

@media (prefers-reduced-motion: reduce) {
  .jauge__valeur {
    transition: none;
  }
}
</style>
