<script setup>
import { computed, onMounted, ref } from 'vue';
import { HeaderComponent, GlobalLoader } from '@components';
import { useNotificationStore } from '@stores';
import { TYPES_NOTIFICATION, grouperParJour, heure, libelleNonLues, typeNotification } from '@helpers/notifications.js';

const store = useNotificationStore();

const etat = ref('toutes');
const etats = [{ label: 'Toutes', value: 'toutes' }, { label: 'Non lues', value: 'non_lues' }];
const type = ref('tous');
const types = [
  { label: 'Tous les types', value: 'tous' },
  ...Object.entries(TYPES_NOTIFICATION).map(([value, { libelle }]) => ({ label: `${libelle}s`, value })),
];

const affichees = computed(() => store.notifications.filter(notification =>
  (etat.value === 'toutes' || !notification.lue) && (type.value === 'tous' || notification.type === type.value)));

const jours = computed(() => grouperParJour(affichees.value));

// Un message se lit ici en entier : il n'a pas d'autre page où mener.
const estIci = (notification) => notification.lien === '/intranet/notifications';

const marquer = (notification) => {
  if (!notification.lue) {
    store.marquerLues([notification.cle]);
  }
};

onMounted(() => store.charger());
</script>

<template>
  <HeaderComponent
      icon="pi pi-bell"
      titre="Notifications"
      description="Vos notes, absences, documents, actualités et messages des trois derniers mois"
  />

  <GlobalLoader v-if="!store.isLoaded && store.isLoading" />
  <Message v-else-if="store.erreur" severity="error">
    Vos notifications n'ont pas pu être chargées. Réessayez dans quelques instants.
  </Message>

  <template v-else>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <p class="m-0 font-semibold" aria-live="polite">{{ libelleNonLues(store.nonLues) }}</p>
      <Button v-if="store.nonLues > 0" label="Tout marquer comme lu" icon="pi pi-check" outlined
          class="min-h-[44px]" @click="store.marquerLues()" />
    </div>

    <div class="flex flex-wrap gap-6 mb-6">
      <fieldset class="border-0 p-0 m-0">
        <legend class="text-sm font-semibold mb-2">Afficher</legend>
        <SelectButton v-model="etat" :options="etats" option-label="label" option-value="value" :allow-empty="false" class="flex-wrap!" />
      </fieldset>
      <fieldset class="border-0 p-0 m-0">
        <legend class="text-sm font-semibold mb-2">Type</legend>
        <SelectButton v-model="type" :options="types" option-label="label" option-value="value" :allow-empty="false" class="flex-wrap!" />
      </fieldset>
    </div>

    <Message v-if="jours.length === 0" severity="info">
      {{ store.notifications.length === 0 ? 'Rien de nouveau pour l\'instant.' : 'Aucune notification ne correspond à ces filtres.' }}
    </Message>

    <section v-for="(groupe, index) in jours" :key="groupe.jour" class="mb-8" :aria-labelledby="`jour-${index}`">
      <h2 :id="`jour-${index}`" class="text-lg font-semibold mb-3">{{ groupe.jour }}</h2>
      <ul class="list-none p-0 m-0 flex flex-col gap-3">
        <li v-for="notification in groupe.notifications" :key="notification.cle">
          <article :class="['notification', { 'notification--non-lue': !notification.lue }]">
            <i :class="[typeNotification(notification.type).icone, 'text-xl mt-1']" aria-hidden="true"></i>
            <div class="flex-1 min-w-0">
              <p class="m-0 text-sm notification-meta">
                {{ typeNotification(notification.type).libelle }} · {{ heure(new Date(notification.date)) }}
                <strong v-if="!notification.lue"> · Non lue</strong>
              </p>
              <h3 class="notification-titre">{{ notification.titre }}</h3>
              <p v-if="notification.texte" :class="['m-0', estIci(notification) ? 'whitespace-pre-line' : 'line-clamp-2']">
                {{ notification.texte }}
              </p>
            </div>
            <div class="flex items-start">
              <template v-if="estIci(notification)">
                <Button v-if="!notification.lue" label="Marquer comme lu" text class="min-h-[44px]"
                    @click="marquer(notification)" />
              </template>
              <router-link v-else :to="notification.lien" class="notification-lien" @click="marquer(notification)">
                Voir<span class="sr-only"> : {{ notification.titre }}</span>
              </router-link>
            </div>
          </article>
        </li>
      </ul>
    </section>
  </template>
</template>

<style scoped>
.notification {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 6px;
  background: var(--p-content-background);
}

/* La couleur ne porte pas seule l'état : « Non lue » est écrit dans la ligne. */
.notification--non-lue {
  border-left: 4px solid var(--p-primary-color);
}

.notification-meta {
  color: var(--text-color-secondary, #676d75);
}

/* Les titres du thème sont faits pour une page : ici, un titre par notification. */
.notification-titre {
  margin: 0.25rem 0;
  font-size: 1.05rem;
  font-weight: 600;
  line-height: 1.4;
}

:deep(.p-togglebutton) {
  min-height: 44px;
}

.notification-lien {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 0.75rem;
  font-weight: 600;
  text-decoration: underline;
}

.notification-lien:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}
</style>
