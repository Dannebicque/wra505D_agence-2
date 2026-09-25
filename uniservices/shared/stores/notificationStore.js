import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { getNotificationsService, marquerNotificationsLuesService } from '@requests';

// Partagé entre la cloche de la barre haute et la page Notifications, pour qu'une lecture faite
// sur l'une se voie aussitôt sur l'autre.
export const useNotificationStore = defineStore('notifications', () => {
    const notifications = ref([]);
    const nonLues = ref(0);
    const isLoaded = ref(false);
    const isLoading = ref(false);
    const erreur = ref(false);

    const appliquer = (fil) => {
        notifications.value = fil?.notifications ?? [];
        nonLues.value = fil?.nonLues ?? 0;
        isLoaded.value = true;
        erreur.value = false;
    };

    const charger = async () => {
        if (isLoading.value) return;
        isLoading.value = true;
        try {
            appliquer(await getNotificationsService());
        } catch {
            erreur.value = true;
        } finally {
            isLoading.value = false;
        }
    };

    const marquerLues = async (cles = null) => {
        if (cles !== null && cles.length === 0) return;
        appliquer(await marquerNotificationsLuesService(cles));
    };

    const recentes = computed(() => notifications.value.slice(0, 5));

    return { notifications, nonLues, isLoaded, isLoading, erreur, recentes, charger, marquerLues };
});
