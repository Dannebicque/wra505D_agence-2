import api from '@helpers/axios';
import apiCall from '@helpers/apiCall';

const getRechercheService = async (requete, showToast = false) => {
    try {
        const response = await apiCall(
            api.get,
            [`/api/recherche`, {params: {q: requete}}],
            'Recherche effectuée avec succès',
            'Erreur lors de la recherche',
            showToast
        );
        return response['member'];
    } catch (error) {
        console.error('Erreur dans getRechercheService:', error);
        throw error;
    }
}

export { getRechercheService };
