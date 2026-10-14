<script setup>
import {useId} from 'vue';

defineProps({
  titre: {type: String, required: true},
  icone: {type: String, required: true},
  // La teinte du bloc, prise aux types de séance : elle marque la pastille de son titre et les
  // icônes de ses lignes, pas ses fonds. Une touche de couleur, sans aplat (C19).
  teinte: {type: String, default: null},
  lien: {type: String, default: null},
  texteLien: {type: String, default: null},
});

const id = useId();
</script>

<template>
  <section
      class="bloc-accueil"
      :aria-labelledby="id"
      :style="teinte ? {
        '--bloc-teinte-fond': `var(--p-seance-${teinte}-background)`,
        '--bloc-teinte-couleur': `var(--p-seance-${teinte}-color)`,
      } : null"
  >
    <h2 :id="id" class="bloc-accueil__titre">
      <span class="bloc-accueil__icone" aria-hidden="true"><i :class="icone"></i></span>
      {{ titre }}
    </h2>
    <slot/>
    <RouterLink v-if="lien" :to="lien" class="bloc-accueil__lien">
      {{ texteLien }}
      <span class="bloc-accueil__fleche" aria-hidden="true"><i class="pi pi-arrow-right"></i></span>
    </RouterLink>
  </section>
</template>

<style scoped>
.bloc-accueil {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem 1.125rem 1.125rem;
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 4px 12px -4px rgb(0 0 0 / 0.06);
  overflow: hidden;
}

.bloc-accueil__titre {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin: 0 !important;
  font-size: 1.125rem !important;
  font-weight: 600;
}

.bloc-accueil__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 1rem;
  background: var(--bloc-teinte-fond, transparent);
  color: var(--bloc-teinte-couleur, var(--p-text-muted-color));
}

.bloc-accueil__lien {
  align-self: flex-end;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  margin-top: auto;
  padding: 0 0.25rem 0 0.75rem;
  border-radius: 999px;
  font-size: 1rem;
  font-weight: 600;
  color: var(--p-primary-color);
  transition: background-color 150ms ease;
}

.bloc-accueil__lien:hover {
  background: var(--p-content-hover-background);
}

.bloc-accueil__lien:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

/* Une pastille pâle autour de la flèche, aux couleurs du bloc : présente sans peser. */
.bloc-accueil__fleche {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 0.75rem;
  background: var(--p-content-hover-background);
  transition: transform 150ms ease;
}

.bloc-accueil__lien:hover .bloc-accueil__fleche {
  transform: translateX(2px);
}

@media (prefers-reduced-motion: reduce) {
  .bloc-accueil__lien,
  .bloc-accueil__fleche {
    transition: none;
  }
}
</style>
