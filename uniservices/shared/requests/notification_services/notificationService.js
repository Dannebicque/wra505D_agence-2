import api from '@helpers/axios';
import apiCall from '@helpers/apiCall';

const getNotificationsService = async (showToast = false) => {
    try {
        return await apiCall(
            api.get,
            ['/api/me/notifications'],
            'Notifications récupérées avec succès',
            'Erreur lors de la récupération des notifications',
            showToast
        );
    } catch (error) {
        console.error('Erreur dans getNotificationsService:', error);
        throw error;
    }
}

// Sans clé, tout le fil est marqué comme lu. L'API renvoie le fil à jour.
const marquerNotificationsLuesService = async (cles = null, showToast = false) => {
    try {
        return await apiCall(
            api.post,
            ['/api/me/notifications/lues', cles === null ? {} : {cles}],
            'Notifications marquées comme lues',
            'Erreur lors du marquage des notifications',
            showToast
        );
    } catch (error) {
        console.error('Erreur dans marquerNotificationsLuesService:', error);
        throw error;
    }
}

export { getNotificationsService, marquerNotificationsLuesService };
