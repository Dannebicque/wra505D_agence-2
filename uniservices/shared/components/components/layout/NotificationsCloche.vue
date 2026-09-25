<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationStore } from '@stores';
import { heure, libelleJour, libelleNonLues, typeNotification } from '@helpers/notifications.js';

const store = useNotificationStore();
const router = useRouter();
const panneau = ref();
const ouvert = ref(false);

const basculer = (event) => panneau.value.toggle(event);

const ouvrir = async (notification) => {
  panneau.value.hide();
  if (!notification.lue) {
    await store.marquerLues([notification.cle]);
  }
  router.push(notification.lien);
};

const toutVoir = () => {
  panneau.value.hide();
  router.push('/intranet/notifications');
};

// Un onglet laissé ouvert se remet à jour quand l'étudiant y revient.
const rafraichirAuRetour = () => {
  if (document.visibilityState === 'visible') {
    store.charger();
  }
};

onMounted(() => {
  store.charger();
  document.addEventListener('visibilitychange', rafraichirAuRetour);
});

onBeforeUnmount(() => document.removeEventListener('visibilitychange', rafraichirAuRetour));
</script>

<template>
  <button type="button" class="layout-topbar-action notifications-cloche"
    :aria-label="`Notifications, ${libelleNonLues(store.nonLues)}`" aria-haspopup="dialog" :aria-expanded="ouvert"
    aria-controls="panneau-notifications" @click="basculer">
    <i class="pi pi-bell" aria-hidden="true"></i>
    <b v-if="store.nonLues > 0" class="notifications-compteur" aria-hidden="true">
      {{ store.nonLues > 99 ? '99+' : store.nonLues }}
    </b>
    <span>Notifications</span>
  </button>

  <Popover ref="panneau" id="panneau-notifications" aria-labelledby="titre-panneau-notifications"
    @show="ouvert = true" @hide="ouvert = false">
    <div class="w-80 max-w-[85vw]">
      <div class="flex items-center justify-between gap-2 mb-2">
        <h2 id="titre-panneau-notifications" class="titre-panneau">Notifications</h2>
        <Button v-if="store.nonLues > 0" label="Tout marquer comme lu" text size="small" class="min-h-[44px]"
          @click="store.marquerLues()" />
      </div>

      <p v-if="store.erreur" class="text-sm">Les notifications n'ont pas pu être chargées.</p>
      <p v-else-if="store.isLoaded && store.recentes.length === 0" class="text-sm">Rien de nouveau pour l'instant.</p>
      <ul v-else class="list-none p-0 m-0">
        <li v-for="notification in store.recentes" :key="notification.cle">
          <button type="button" class="notification-ligne" @click="ouvrir(notification)">
            <i :class="[typeNotification(notification.type).icone, 'mt-1']" aria-hidden="true"></i>
            <span class="flex-1 min-w-0">
              <span class="block font-semibold truncate">{{ notification.titre }}</span>
              <span v-if="notification.texte" class="block text-sm truncate">{{ notification.texte }}</span>
              <span class="block text-xs notification-meta">
                {{ typeNotification(notification.type).libelle }} · {{ libelleJour(new Date(notification.date)) }},
                {{ heure(new Date(notification.date)) }}
                <strong v-if="!notification.lue"> · Non lue</strong>
              </span>
            </span>
          </button>
        </li>
      </ul>

      <Button label="Tout voir" outlined class="w-full mt-2 min-h-[44px]" @click="toutVoir" />
    </div>
  </Popover>
</template>

<style scoped>
/* Les titres du thème sont faits pour une page, pas pour un panneau. */
.titre-panneau {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  line-height: 1.4;
}

.notifications-cloche {
  position: relative;
  min-width: 44px;
  min-height: 44px;
}

/* Le compteur est lu dans le nom du bouton : la pastille ne sert qu'à l'œil. Pas de span : la
   barre haute les masque sur grand écran. */
.notifications-compteur {
  position: absolute;
  top: 0.1rem;
  right: 0.1rem;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.25rem;
  text-align: center;
}

.notification-ligne {
  display: flex;
  gap: 0.75rem;
  width: 100%;
  min-height: 44px;
  padding: 0.5rem;
  border-radius: 6px;
  text-align: left;
  color: inherit;
}

.notification-ligne:hover {
  background: var(--p-content-hover-background);
}

.notification-ligne:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.notification-meta {
  color: var(--text-color-secondary, #676d75);
}
</style>
