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
  <section class="bloc-accueil" :aria-labelledby="id">
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
      <i class="pi pi-arrow-right" aria-hidden="true"></i>
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
  align-self: flex-end;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  min-height: 44px;
  margin-top: auto;
  padding: 0 0.25rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--p-primary-color);
}

.bloc-accueil__lien:hover {
  text-decoration: underline;
}
</style>
