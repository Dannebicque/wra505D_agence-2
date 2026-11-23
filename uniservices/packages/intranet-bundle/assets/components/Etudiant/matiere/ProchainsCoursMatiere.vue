<script setup>
import {computed} from 'vue';
import {prochainCours} from '@/utils/ficheMatiere.js';

const props = defineProps({
  cours: {type: Array, required: true},
  maintenant: {type: Date, required: true},
});

const seances = computed(() => props.cours.map(seance => prochainCours(seance, props.maintenant)));
</script>

<template>
  <p v-if="!seances.length" class="m-0!">Aucun cours de cette matière n'est prévu dans votre emploi du temps.</p>
  <ul v-else class="seances">
    <!-- Chaque créneau reprend la couleur de son type, comme dans l'agenda. -->
    <li
        v-for="seance in seances"
        :key="seance.id"
        class="seance"
        :style="{
          '--seance-fond': `var(--p-seance-${seance.type}-background)`,
          '--seance-couleur': `var(--p-seance-${seance.type}-color)`,
          '--seance-pastille': `var(--p-seance-${seance.type}-pill-background)`,
          '--seance-pastille-texte': `var(--p-seance-${seance.type}-pill-color)`,
        }"
    >
      <span class="seance__quand">
        <span class="seance__jour">{{ seance.jour }}</span>
        <span class="seance__horaire">{{ seance.horaire }}</span>
      </span>
      <span class="seance__detail">
        <span class="seance__etiquettes">
          <span class="seance__pastille">{{ seance.etiquette }}</span>
          <span v-if="seance.groupe">{{ seance.groupe }}</span>
          <span v-if="seance.enCours" class="seance__en-cours">En cours</span>
        </span>
        <span class="seance__lieu">
          <template v-if="seance.salle"><i class="pi pi-map-marker" aria-hidden="true"></i> {{ seance.salle }}</template>
          <template v-if="seance.enseignant"><i class="pi pi-user" aria-hidden="true"></i> {{ seance.enseignant }}</template>
        </span>
      </span>
    </li>
  </ul>
</template>

<style scoped>
.seances {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.seance {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem 1rem;
  border-radius: 14px;
  background: var(--seance-fond);
}

.seance__quand {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  min-width: 10.5rem;
}

.seance__jour {
  font-weight: 600;
  color: var(--p-text-color);
}

.seance__horaire {
  font-size: 1rem;
  color: var(--seance-couleur);
}

.seance__detail {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.seance__etiquettes {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  font-size: 1rem;
  font-weight: 700;
  color: var(--seance-couleur);
}

.seance__pastille {
  padding: 0 0.625rem;
  border-radius: 999px;
  font-size: 0.875rem;
  line-height: 1.5rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  color: var(--seance-pastille-texte);
  background: var(--seance-pastille);
}

.seance__en-cours {
  padding: 0 0.625rem;
  border-radius: 999px;
  font-size: 0.875rem;
  line-height: 1.5rem;
  background: var(--p-content-background);
}

.seance__lieu {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.75rem;
  font-size: 1rem;
  color: var(--p-text-color);
}

.seance__lieu .pi {
  margin-right: -0.375rem;
  color: var(--seance-couleur);
}

/* Sur téléphone, le jour passe au-dessus du détail. */
@media (max-width: 639px) {
  .seance {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}
</style>
