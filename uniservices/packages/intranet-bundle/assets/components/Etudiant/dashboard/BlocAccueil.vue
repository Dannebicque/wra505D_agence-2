<script setup>
import {useId} from 'vue';

defineProps({
  titre: {type: String, required: true},
  icone: {type: String, required: true},
  // Teinte de la pastille d'icône, prise aux types de séance du preset : l'accueil parle les
  // couleurs de l'agenda.
  teinte: {type: String, default: 'cm'},
  lien: {type: String, default: null},
  texteLien: {type: String, default: null},
});

const id = useId();
</script>

<template>
  <section
      class="bloc-accueil"
      :aria-labelledby="id"
      :style="{
        '--lien-background': `var(--p-seance-${teinte}-background)`,
        '--lien-color': `var(--p-seance-${teinte}-color)`,
        '--lien-pill-background': `var(--p-seance-${teinte}-pill-background)`,
        '--lien-pill-color': `var(--p-seance-${teinte}-pill-color)`,
      }"
  >
    <h2 :id="id" class="bloc-accueil__titre">
      <span
          class="bloc-accueil__icone"
          :style="{ background: `var(--p-seance-${teinte}-background)`, color: `var(--p-seance-${teinte}-color)` }"
          aria-hidden="true"
      >
        <i :class="icone"></i>
      </span>
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
  font-size: 1rem !important;
  font-weight: 700;
}

.bloc-accueil__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 0.875rem;
}

.bloc-accueil__lien {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 44px;
  margin-top: auto;
  padding: 0.25rem 0.25rem 0.25rem 1rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 600;
  background: var(--lien-background);
  color: var(--lien-color);
  transition: background-color 150ms ease;
}

.bloc-accueil__lien:hover {
  background: color-mix(in srgb, var(--lien-pill-background) 14%, var(--lien-background));
}

.bloc-accueil__lien:focus-visible {
  outline: 2px solid var(--lien-color);
  outline-offset: 2px;
}

/* La flèche dans une pastille, comme les types de séance de l'agenda : elle glisse au survol. */
.bloc-accueil__fleche {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 0.75rem;
  background: var(--lien-pill-background);
  color: var(--lien-pill-color);
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
