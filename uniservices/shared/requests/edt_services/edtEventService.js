import api from '@helpers/axios';
import apiCall from '@helpers/apiCall';

const getEdtEventsService = async (params, scope ='', showToast = false) => {
    try {
        const response = await apiCall(
            api.get,
            [`/api${scope}/edt_events`, { params }],
            'Événéments de l\'emploi du temps du personnel récupérés avec succès',
            'Erreur lors de la récupération des événements de l\'emploi du temps du personnel',
            showToast
        );
        if (!response) return null;
        if (Array.isArray(response)) return response; // déjà un tableau
        if (response.member) return response.member;
        if (response['hydra:member']) return response['hydra:member'];
        // sinon c'est probablement un DTO / objet simple (ex: EdtStatsDto)
        return response;
    } catch (error) {
        console.error('Erreur dans getPersonnelEdtEventsService:', error);
        throw error;
    }
}

// semaine : un jour de la semaine voulue, au format AAAA-MM-JJ.
// enArrierePlan : lecture d'avance, dont l'échec reste silencieux au lieu d'ouvrir la page 500.
const getMonEmploiDuTempsService = async (semaine, enArrierePlan = false) => {
    return await apiCall(
        api.get,
        ['/api/me/emploi-du-temps', { params: { semaine }, ...(enArrierePlan ? { pageErreurServeur: false } : {}) }],
        'Emploi du temps récupéré avec succès',
        'Erreur lors de la récupération de l\'emploi du temps',
        false
    );
}

const getMesSemainesService = async () => {
    return await apiCall(
        api.get,
        ['/api/me/emploi-du-temps/semaines'],
        'Semaines récupérées avec succès',
        'Erreur lors de la récupération des semaines',
        false
    );
}

export { getEdtEventsService, getMonEmploiDuTempsService, getMesSemainesService };
