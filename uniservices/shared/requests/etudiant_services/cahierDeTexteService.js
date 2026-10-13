import api from '@helpers/axios';
import apiCall from '@helpers/apiCall';

const getMonCahierDeTexteService = async (showToast = false) => {
    try {
        return await apiCall(
            api.get,
            ['/api/me/cahier-de-texte'],
            'Cahier de texte récupéré avec succès',
            'Erreur lors de la récupération du cahier de texte',
            showToast
        );
    } catch (error) {
        console.error('Erreur dans getMonCahierDeTexteService:', error);
        throw error;
    }
}

export { getMonCahierDeTexteService };
