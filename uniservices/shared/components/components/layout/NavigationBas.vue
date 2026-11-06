<script setup>
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {useRoute} from 'vue-router';
import {useNotificationStore, useUsersStore} from '@stores';
import {entreeActive, navigationMobile} from '@helpers/menuEtudiant.js';
import {libelleNonLues} from '@helpers/notifications.js';
import {seDeconnecter} from '@helpers/deconnexion.js';
import {useLayout} from './composables/layout.js';
import AppSearch from './AppSearch.vue';

// La navigation de l'étudiant sur téléphone, seule barre de l'écran : ses rubriques principales en
// icônes, sous le pouce, puis « Plus » (la recherche et les autres rubriques) et son compte, en
// avatar tout à droite, comme dans les applications qu'il utilise chaque jour.
const props = defineProps({
  menu: {type: Array, required: true},
});

const route = useRoute();
const userStore = useUsersStore();
const notifications = useNotificationStore();
const {toggleDarkMode, isDarkTheme} = useLayout();
const plusOuvert = ref(false);
const compteOuvert = ref(false);

const navigation = computed(() => navigationMobile(props.menu));
const toutes = computed(() => props.menu.flatMap(groupe => groupe.items));
const active = computed(() => entreeActive(toutes.value, route.path));
const plusActif = computed(() => plusOuvert.value || (active.value !== null && !navigation.value.onglets.includes(active.value)));
const compteActif = computed(() => compteOuvert.value || route.path.includes('/profil'));
const libellePlus = computed(() => (notifications.nonLues > 0 ? `Plus, ${libelleNonLues(notifications.nonLues)}` : 'Plus'));

const nom = computed(() => [userStore.user?.prenom, userStore.user?.nom].filter(Boolean).join(' ') || (userStore.user?.name ?? ''));
const initiales = computed(() => nom.value.split(' ').map(mot => mot[0]).join(''));
const estNotifications = (item) => item.to.includes('/notifications');

// Changer de page referme les panneaux.
watch(() => route.fullPath, () => {
  plusOuvert.value = false;
  compteOuvert.value = false;
});

// La page réserve la hauteur de la barre (--barre-bas) : le pied de page et les boutons flottants
// restent au-dessus d'elle.
onMounted(() => {
  document.documentElement.classList.add('avec-barre-bas');
  if (!notifications.isLoaded) {
    notifications.charger();
  }
});
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
          <i :class="['navigation-bas__icone', onglet.icon]" aria-hidden="true"></i>
          <span class="sr-only">{{ onglet.onglet }}</span>
        </RouterLink>
      </li>
      <li>
        <button
            type="button"
            :class="['navigation-bas__onglet', {'navigation-bas__onglet--actif': plusActif}]"
            aria-haspopup="dialog"
            :aria-expanded="plusOuvert"
            @click="plusOuvert = true"
        >
          <span class="navigation-bas__icone" aria-hidden="true">
            <i class="pi pi-th-large"></i>
            <span v-if="notifications.nonLues > 0" class="navigation-bas__non-lues"></span>
          </span>
          <span class="sr-only">{{ libellePlus }}</span>
        </button>
      </li>
      <li>
        <button
            type="button"
            :class="['navigation-bas__onglet', {'navigation-bas__onglet--actif': compteActif}]"
            aria-haspopup="dialog"
            :aria-expanded="compteOuvert"
            @click="compteOuvert = true"
        >
          <span class="navigation-bas__icone navigation-bas__avatar" aria-hidden="true">
            <img v-if="userStore.userPhoto" :src="userStore.userPhoto" alt="">
            <template v-else>{{ initiales }}</template>
          </span>
          <span class="sr-only">Mon compte</span>
        </button>
      </li>
    </ul>
  </nav>

  <Drawer v-model:visible="plusOuvert" position="bottom" header="Plus" class="navigation-feuille" :block-scroll="true">
    <div class="navigation-feuille__recherche">
      <AppSearch input-id="recherche-plus"/>
    </div>

    <section v-for="groupe in navigation.autres" :key="groupe.label" class="navigation-feuille__groupe">
      <h2 class="navigation-feuille__titre">{{ groupe.label }}</h2>
      <ul class="navigation-feuille__liste">
        <li v-for="item in groupe.items" :key="item.to">
          <RouterLink
              :to="item.to"
              :class="['navigation-feuille__lien', {'navigation-feuille__lien--actif': item === active}]"
              :aria-current="item === active ? 'page' : undefined"
          >
            <span class="navigation-feuille__pastille" aria-hidden="true"><i :class="item.icon"></i></span>
            {{ item.label }}
            <span v-if="estNotifications(item) && notifications.nonLues > 0" class="navigation-feuille__compteur">
              {{ notifications.nonLues }}<span class="sr-only"> non lues</span>
            </span>
            <i class="pi pi-chevron-right navigation-feuille__fleche" aria-hidden="true"></i>
          </RouterLink>
        </li>
      </ul>
    </section>
  </Drawer>

  <Drawer v-model:visible="compteOuvert" position="bottom" header="Mon compte" class="navigation-feuille" :block-scroll="true">
    <ul class="navigation-feuille__liste">
      <li>
        <RouterLink to="/profil" class="navigation-feuille__lien">
          <span class="navigation-feuille__pastille navigation-feuille__pastille--avatar" aria-hidden="true">
            <img v-if="userStore.userPhoto" :src="userStore.userPhoto" alt="">
            <template v-else>{{ initiales }}</template>
          </span>
          <span class="navigation-feuille__texte">
            {{ nom || 'Mon profil' }}
            <span class="navigation-feuille__detail">Voir mon profil</span>
          </span>
          <i class="pi pi-chevron-right navigation-feuille__fleche" aria-hidden="true"></i>
        </RouterLink>
      </li>
      <li>
        <button type="button" class="navigation-feuille__lien" :aria-pressed="isDarkTheme" @click="toggleDarkMode">
          <span class="navigation-feuille__pastille" aria-hidden="true"><i :class="isDarkTheme ? 'pi pi-moon' : 'pi pi-sun'"></i></span>
          Mode sombre
          <span class="navigation-feuille__interrupteur" aria-hidden="true"></span>
        </button>
      </li>
      <li>
        <button type="button" class="navigation-feuille__lien" @click="seDeconnecter">
          <span class="navigation-feuille__pastille" aria-hidden="true"><i class="pi pi-sign-out"></i></span>
          Déconnexion
        </button>
      </li>
    </ul>
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
    border-radius: 999px;
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
  padding: 0.375rem;
  list-style: none;
}

/* Comme Instagram : des icônes seules, atténuées, et celle de la rubrique ouverte en couleur pleine.
   Un point dessous la signale aussi sans la couleur. */
.navigation-bas__onglet {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 52px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: none;
  color: var(--p-text-muted-color);
  font: inherit;
  text-decoration: none;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

.navigation-bas__onglet:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.navigation-bas__icone {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.625rem;
  transition: transform 120ms ease, color 150ms ease;
}

/* Au toucher, l'icône rentre ; elle rebondit quand la rubrique s'ouvre. */
.navigation-bas__onglet:active .navigation-bas__icone {
  transform: scale(0.86);
}

.navigation-bas__onglet--actif {
  color: var(--p-primary-color);
}

.navigation-bas__onglet--actif .navigation-bas__icone {
  animation: rebond 360ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.navigation-bas__onglet--actif::after {
  position: absolute;
  bottom: 0.25rem;
  width: 0.3125rem;
  height: 0.3125rem;
  border-radius: 999px;
  background: currentColor;
  content: '';
}

@keyframes rebond {
  0% {
    transform: scale(0.82);
  }

  60% {
    transform: scale(1.12);
  }
}

/* Des notifications non lues : un point rouge sur « Plus », bordé pour se détacher de l'icône. */
.navigation-bas__non-lues {
  position: absolute;
  top: -0.125rem;
  right: -0.375rem;
  width: 0.625rem;
  height: 0.625rem;
  border: 2px solid var(--p-content-background);
  border-radius: 999px;
  background: var(--p-seance-evaluation-color);
}

/* Le compte en avatar, comme le profil d'Instagram : cerclé quand il est ouvert. */
.navigation-bas__avatar {
  width: 2rem;
  height: 2rem;
  overflow: hidden;
  border-radius: 999px;
  font-size: 1rem;
  font-weight: 700;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}

.navigation-bas__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.navigation-bas__onglet--actif .navigation-bas__avatar {
  box-shadow: 0 0 0 2px var(--p-content-background), 0 0 0 4px var(--p-primary-color);
}

@media (prefers-reduced-motion: reduce) {
  .navigation-bas__icone {
    transition: none;
  }

  .navigation-bas__onglet:active .navigation-bas__icone,
  .navigation-bas__onglet--actif .navigation-bas__icone {
    transform: none;
    animation: none;
  }
}
</style>

<style>
/* La hauteur que la barre occupe avec sa marge, réservée par la page tant qu'elle est affichée. */
@media (max-width: 991px) {
  html.avec-barre-bas {
    --barre-bas: calc(4.625rem + 0.625rem + env(safe-area-inset-bottom, 0px));
  }
}

/* « Plus » et le compte s'ouvrent du bas, comme des feuilles : téléportées, leurs styles ne peuvent
   pas être scopés. PrimeVue fixe la hauteur des panneaux du bas (10rem) : la feuille prend celle de
   son contenu. */
.p-drawer-bottom .navigation-feuille.p-drawer {
  height: auto;
  max-height: 85vh;
  border: 0;
  border-radius: 24px 24px 0 0;
  padding-bottom: env(safe-area-inset-bottom, 0px);
}

.navigation-feuille .p-drawer-title {
  font-size: 1.125rem;
}

.navigation-feuille__recherche {
  margin-bottom: 1rem;
}

.navigation-feuille__recherche .p-autocomplete,
.navigation-feuille__recherche .p-autocomplete-input {
  width: 100%;
}

.navigation-feuille__groupe + .navigation-feuille__groupe {
  margin-top: 1rem;
}

.navigation-feuille__titre {
  margin: 0 0 0.25rem !important;
  padding: 0 0.5rem;
  font-size: 1rem !important;
  font-weight: 600;
  color: var(--p-text-muted-color);
}

.navigation-feuille__liste {
  margin: 0;
  padding: 0;
  list-style: none;
}

.navigation-feuille__lien {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  width: 100%;
  min-height: 52px;
  padding: 0 0.5rem;
  border: 0;
  border-radius: 14px;
  background: none;
  font: inherit;
  font-size: 1.0625rem;
  color: var(--p-text-color);
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}

.navigation-feuille__lien:hover {
  background: var(--p-content-hover-background);
}

.navigation-feuille__lien:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: -2px;
}

.navigation-feuille__lien--actif {
  font-weight: 700;
  color: var(--p-primary-color);
}

.navigation-feuille__pastille {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--p-seance-cm-background);
  color: var(--p-seance-cm-color);
}

.navigation-feuille__pastille--avatar {
  font-weight: 700;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}

.navigation-feuille__pastille--avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.navigation-feuille__texte {
  display: flex;
  flex-direction: column;
  font-weight: 600;
}

.navigation-feuille__detail {
  font-size: 1rem;
  font-weight: 400;
  color: var(--p-text-muted-color);
}

.navigation-feuille__fleche {
  margin-left: auto;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.navigation-feuille__compteur {
  margin-left: auto;
  min-width: 1.75rem;
  padding: 0 0.5rem;
  border-radius: 999px;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.75rem;
  text-align: center;
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.navigation-feuille__compteur + .navigation-feuille__fleche {
  margin-left: 0;
}

/* L'interrupteur du mode sombre : la bille passe à droite quand il est actif. */
.navigation-feuille__interrupteur {
  position: relative;
  width: 2.75rem;
  height: 1.5rem;
  margin-left: auto;
  border: 2px solid var(--p-ui-border-color);
  border-radius: 999px;
  transition: background-color 200ms ease, border-color 200ms ease;
}

.navigation-feuille__interrupteur::after {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 1rem;
  height: 1rem;
  border-radius: 999px;
  background: var(--p-ui-border-color);
  content: '';
  transition: transform 200ms ease, background-color 200ms ease;
}

.navigation-feuille__lien[aria-pressed="true"] .navigation-feuille__interrupteur {
  border-color: var(--p-primary-color);
  background: var(--p-primary-color);
}

.navigation-feuille__lien[aria-pressed="true"] .navigation-feuille__interrupteur::after {
  background: var(--p-primary-contrast-color);
  transform: translateX(1.25rem);
}

@media (prefers-reduced-motion: reduce) {
  .navigation-feuille__interrupteur,
  .navigation-feuille__interrupteur::after {
    transition: none;
  }
}
</style>
