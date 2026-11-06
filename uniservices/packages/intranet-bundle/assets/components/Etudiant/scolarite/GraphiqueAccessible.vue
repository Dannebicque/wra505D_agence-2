<script setup>
import {inject, onBeforeUnmount, ref, useId, watch} from 'vue';

// Un graphique ne se lit ni au clavier ni au lecteur d'écran : le même contenu existe en tableau,
// une bascule passe de l'un à l'autre, et le canvas porte un résumé en une phrase.
defineProps({
  titre: {type: String, required: true},
  resume: {type: String, required: true},
  icone: {type: String, required: true},
  // Teinte de séance de la pastille du titre, comme les blocs de l'accueil.
  teinte: {type: String, default: 'cm'},
  niveau: {type: Number, default: 2},
  rang: {type: Number, default: 0},
});

const VUES = [
  {valeur: 'graphique', libelle: 'Graphique', icone: 'pi pi-chart-bar'},
  {valeur: 'tableau', libelle: 'Tableau', icone: 'pi pi-table'},
];
const vue = ref('graphique');
const id = useId();

// Avant que le tableau ne remplace le graphique, la carte donne sa hauteur à la ligne qui la porte,
// pour que sa voisine n'en change pas. Elle ne la libère qu'une fois le graphique revenu : libérée
// au clic, la voisine s'étirait un instant sur le tableau qui s'efface encore.
const ligne = inject('ligneGraphiques', null);
const carte = ref(null);
let signale = false;
watch(vue, (nouvelle) => {
  if (nouvelle === 'tableau' && !signale) {
    ligne?.ouvrirTableau(carte.value.getBoundingClientRect().height);
    signale = true;
  }
}, {flush: 'pre'});
const graphiqueRevenu = () => {
  if (signale && vue.value === 'graphique') {
    ligne?.fermerTableau();
    signale = false;
  }
};
onBeforeUnmount(() => {
  if (signale) {
    ligne?.fermerTableau();
  }
});
</script>

<template>
  <section
      ref="carte"
      class="graphique apparition"
      :style="{'--rang': rang, '--graphique-fond': `var(--p-seance-${teinte}-background)`, '--graphique-couleur': `var(--p-seance-${teinte}-color)`}"
      :aria-labelledby="`${id}-titre`"
  >
    <div class="graphique__entete">
      <component :is="`h${niveau}`" :id="`${id}-titre`" class="graphique__titre">
        <span class="graphique__icone" aria-hidden="true"><i :class="icone"></i></span>
        {{ titre }}
      </component>
      <SelectButton
          v-model="vue"
          :options="VUES"
          option-label="libelle"
          option-value="valeur"
          :allow-empty="false"
          :aria-label="`Affichage de « ${titre} »`"
          :aria-controls="`${id}-contenu`"
          class="graphique__bascule"
      >
        <template #option="{option}">
          <i :class="option.icone" aria-hidden="true"></i>
          <span class="graphique__bascule-texte">{{ option.libelle }}</span>
        </template>
      </SelectButton>
    </div>
    <slot name="filtres"/>
    <div :id="`${id}-contenu`" class="graphique__contenu">
      <Transition name="vue" mode="out-in" @after-enter="graphiqueRevenu">
        <div v-if="vue === 'tableau'" key="tableau" class="graphique__tableau">
          <slot name="tableau"/>
        </div>
        <div v-else key="graphique" role="img" :aria-label="resume" class="graphique__zone">
          <slot/>
        </div>
      </Transition>
    </div>
  </section>
</template>

<style scoped>
/* Les blocs de l'accueil : carte blanche, ombre douce, titre précédé d'une pastille teintée. */
.graphique {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  min-width: 0;
  padding: 1.25rem 1.375rem 1.5rem;
  border-radius: 18px;
  background: var(--p-content-background);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.04), 0 4px 12px -4px rgb(0 0 0 / 0.06);
}

.graphique__entete {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
}

.graphique__titre {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin: 0 !important;
  font-size: 1.125rem !important;
  font-weight: 600;
}

.graphique__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 1rem;
  background: var(--graphique-fond);
  color: var(--graphique-couleur);
}

/* Graphique et Tableau en pilule, comme Jour et Semaine dans l'agenda. */
.graphique__bascule {
  gap: 0.25rem;
  padding: 0.25rem;
  border-radius: 999px;
  background: var(--p-content-hover-background);
}

.graphique__bascule :deep(.p-togglebutton),
.graphique__bascule :deep(.p-togglebutton-content) {
  gap: 0.375rem;
  border: none;
  border-radius: 999px !important;
}

.graphique__bascule :deep(.p-togglebutton) {
  min-height: 44px;
  padding: 0.25rem;
  background: transparent;
}

.graphique__bascule :deep(.p-togglebutton-checked .p-togglebutton-content) {
  background: var(--p-pastille-active-background);
  color: var(--p-pastille-active-color);
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.12);
}

/* Le contour de focus reste dans la pilule, arrondi comme elle. */
.graphique__bascule :deep(.p-togglebutton:focus-visible) {
  outline: 2px solid var(--p-primary-color);
  outline-offset: -2px;
  box-shadow: none;
}

/* Le graphique prend la hauteur restante de la carte quand elle s'aligne sur une voisine plus
   haute, au lieu de laisser un vide dessous. */
.graphique__contenu {
  position: relative;
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.graphique__zone {
  position: relative;
  display: flex;
  flex: 1;
  flex-direction: column;
}

.graphique__tableau {
  overflow-x: auto;
}

/* Passer du graphique au tableau : l'un s'efface, l'autre monte en fondu. */
.vue-enter-active,
.vue-leave-active {
  transition: opacity 180ms ease, transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.vue-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

.vue-leave-to {
  opacity: 0;
}

/* Les tableaux : en-tête teinté en pilule, lignes aérées, chiffres alignés. */
.graphique :deep(table) {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 1rem;
}

.graphique :deep(thead th) {
  padding: 0.625rem 0.875rem;
  border: none;
  font-size: 1rem;
  font-weight: 600;
  text-align: left;
  color: var(--p-text-muted-color);
  background: var(--p-content-hover-background);
}

.graphique :deep(thead th:first-child) {
  border-radius: 999px 0 0 999px;
}

.graphique :deep(thead th:last-child) {
  border-radius: 0 999px 999px 0;
}

.graphique :deep(tbody th),
.graphique :deep(tbody td) {
  padding: 0.625rem 0.875rem;
  border-bottom: 1px solid var(--p-content-border-color);
  text-align: left;
  vertical-align: middle;
}

.graphique :deep(tbody tr:last-child th),
.graphique :deep(tbody tr:last-child td) {
  border-bottom: none;
}

.graphique :deep(tbody th) {
  font-weight: 600;
}

.graphique :deep(.nombre) {
  text-align: right !important;
  font-variant-numeric: tabular-nums;
}

/* Sur téléphone, la carte se resserre et le titre garde sa bascule sur sa ligne : la pastille du
   titre s'efface, la bascule ne garde que ses icônes, dont les libellés restent lus. */
@media (max-width: 639px) {
  .graphique {
    padding: 1rem;
  }

  .graphique__entete {
    flex-wrap: nowrap;
  }

  .graphique__titre {
    font-size: 1rem !important;
  }

  .graphique__icone {
    display: none;
  }
}

@media (max-width: 479px) {
  .graphique__bascule-texte {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
  }
}

@media (prefers-reduced-motion: reduce) {
  .vue-enter-active,
  .vue-leave-active {
    transition: none;
  }
}
</style>
