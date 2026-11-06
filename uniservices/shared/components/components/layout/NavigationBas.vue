<script setup>
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {useRoute} from 'vue-router';
import {entreeActive, navigationMobile} from '@helpers/menuEtudiant.js';

// La navigation de l'étudiant sur téléphone : ses rubriques principales en bas de l'écran, sous le
// pouce, comme les applications qu'il utilise chaque jour. Le reste du menu s'ouvre depuis « Plus ».
const props = defineProps({
  menu: {type: Array, required: true},
});

const route = useRoute();
const plusOuvert = ref(false);

const navigation = computed(() => navigationMobile(props.menu));
const toutes = computed(() => props.menu.flatMap(groupe => groupe.items));
const active = computed(() => entreeActive(toutes.value, route.path));
const plusActif = computed(() => active.value !== null && !navigation.value.onglets.includes(active.value));

// Changer de page referme « Plus ».
watch(() => route.fullPath, () => {
  plusOuvert.value = false;
});

// La page réserve la hauteur de la barre (--barre-bas) : le pied de page et les boutons flottants
// restent au-dessus d'elle.
onMounted(() => document.documentElement.classList.add('avec-barre-bas'));
onBeforeUnmount(() => document.documentElement.classList.remove('avec-barre-bas'));
</script>

<template>
  <nav class="navigation-bas" aria-label="Navigation principale">
    <ul class="navigation-bas__liste">
      <li v-for="onglet in navigation.onglets" :key="onglet.to">
        <RouterLink
            :to="onglet.to"
            :class="['navigation-bas__onglet', {'navigation-bas__onglet--actif': onglet === active}]"
            :aria-current="onglet === active ? 'page' : undefined"
        >
          <span class="navigation-bas__indicateur" aria-hidden="true"><i :class="onglet.icon"></i></span>
          <span class="navigation-bas__libelle">{{ onglet.onglet }}</span>
        </RouterLink>
      </li>
      <li v-if="navigation.autres.length">
        <button
            type="button"
            :class="['navigation-bas__onglet', {'navigation-bas__onglet--actif': plusActif}]"
            aria-haspopup="dialog"
            :aria-expanded="plusOuvert"
            @click="plusOuvert = true"
        >
          <span class="navigation-bas__indicateur" aria-hidden="true"><i class="pi pi-th-large"></i></span>
          <span class="navigation-bas__libelle">Plus</span>
        </button>
      </li>
    </ul>
  </nav>

  <Drawer v-model:visible="plusOuvert" position="bottom" header="Plus" class="navigation-plus" :block-scroll="true">
    <section v-for="groupe in navigation.autres" :key="groupe.label" class="navigation-plus__groupe">
      <h2 class="navigation-plus__titre">{{ groupe.label }}</h2>
      <ul class="navigation-plus__liste">
        <li v-for="item in groupe.items" :key="item.to">
          <RouterLink
              :to="item.to"
              :class="['navigation-plus__lien', {'navigation-plus__lien--actif': item === active}]"
              :aria-current="item === active ? 'page' : undefined"
          >
            <span class="navigation-plus__icone" aria-hidden="true"><i :class="item.icon"></i></span>
            {{ item.label }}
            <i class="pi pi-chevron-right navigation-plus__fleche" aria-hidden="true"></i>
          </RouterLink>
        </li>
      </ul>
    </section>
  </Drawer>
</template>

<style scoped>
/* N'existe que sur téléphone et petite tablette ; au-delà, le menu latéral reprend la main. */
.navigation-bas {
  display: none;
}

/* Une pilule flottante, détachée des bords et translucide : le contenu défile dessous. */
@media (max-width: 991px) {
  .navigation-bas {
    position: fixed;
    right: 0.75rem;
    bottom: calc(0.625rem + env(safe-area-inset-bottom, 0px));
    left: 0.75rem;
    z-index: 997;
    display: block;
    border: 1px solid color-mix(in srgb, var(--p-content-border-color) 60%, transparent);
    border-radius: 28px;
    background: color-mix(in srgb, var(--p-content-background) 80%, transparent);
    -webkit-backdrop-filter: blur(18px) saturate(1.5);
    backdrop-filter: blur(18px) saturate(1.5);
    box-shadow: 0 10px 30px -12px rgb(0 0 0 / 0.3);
  }
}

.navigation-bas__liste {
  display: grid;
  grid-auto-columns: minmax(0, 1fr);
  grid-auto-flow: column;
  margin: 0;
  padding: 0.3125rem 0.25rem;
  list-style: none;
}

/* Une pastille derrière l'icône marque la rubrique ouverte, comme les pilules de la page des notes. */
.navigation-bas__onglet {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  width: 100%;
  min-height: 52px;
  padding: 0;
  border: 0;
  border-radius: 14px;
  background: none;
  color: var(--p-text-muted-color);
  font: inherit;
  text-decoration: none;
  cursor: pointer;
}

.navigation-bas__onglet:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.navigation-bas__indicateur {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 2rem;
  border-radius: 999px;
  font-size: 1.125rem;
  transition: background-color 200ms ease, color 200ms ease;
}

.navigation-bas__libelle {
  font-size: 1rem;
  font-weight: 500;
  line-height: 1.1;
  white-space: nowrap;
}

.navigation-bas__onglet--actif {
  color: var(--p-primary-color);
}

.navigation-bas__onglet--actif .navigation-bas__indicateur {
  background: var(--p-seance-cm-background);
  color: var(--p-seance-cm-color);
  animation: pastille 320ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* La pastille s'élargit depuis l'icône quand l'onglet devient actif. */
@keyframes pastille {
  from {
    transform: scaleX(0.4);
    opacity: 0;
  }
}

.navigation-bas__onglet--actif .navigation-bas__libelle {
  font-weight: 700;
}

@media (prefers-reduced-motion: reduce) {
  .navigation-bas__indicateur {
    transition: none;
  }

  .navigation-bas__onglet--actif .navigation-bas__indicateur {
    animation: none;
  }
}
</style>

<style>
/* La hauteur que la barre occupe avec sa marge, réservée par la page tant qu'elle est affichée. */
@media (max-width: 991px) {
  html.avec-barre-bas {
    --barre-bas: calc(4.5rem + 0.625rem + env(safe-area-inset-bottom, 0px));
  }
}

/* « Plus » s'ouvre du bas, comme une feuille : téléportée, ses styles ne peuvent pas être scopés.
   PrimeVue fixe la hauteur des panneaux du bas (10rem) : la feuille prend celle de son contenu. */
.p-drawer-bottom .navigation-plus.p-drawer {
  height: auto;
  max-height: 80vh;
  border: 0;
  border-radius: 24px 24px 0 0;
  padding-bottom: env(safe-area-inset-bottom, 0px);
}

.navigation-plus .p-drawer-title {
  font-size: 1.125rem;
}

.navigation-plus__groupe + .navigation-plus__groupe {
  margin-top: 1rem;
}

.navigation-plus__titre {
  margin: 0 0 0.25rem !important;
  padding: 0 0.5rem;
  font-size: 1rem !important;
  font-weight: 600;
  color: var(--p-text-muted-color);
}

.navigation-plus__liste {
  margin: 0;
  padding: 0;
  list-style: none;
}

.navigation-plus__lien {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  min-height: 52px;
  padding: 0 0.5rem;
  border-radius: 14px;
  font-size: 1.0625rem;
  color: var(--p-text-color);
  text-decoration: none;
}

.navigation-plus__lien:hover {
  background: var(--p-content-hover-background);
}

.navigation-plus__lien:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: -2px;
}

.navigation-plus__lien--actif {
  font-weight: 700;
  color: var(--p-primary-color);
}

.navigation-plus__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 999px;
  background: var(--p-seance-cm-background);
  color: var(--p-seance-cm-color);
}

.navigation-plus__fleche {
  margin-left: auto;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}
</style>
