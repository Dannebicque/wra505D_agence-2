import api from '@helpers/axios';
import apiCall from '@helpers/apiCall';

const getMaFicheMatiereService = async (id, showToast = false) => {
    try {
        return await apiCall(
            api.get,
            [`/api/me/matieres/${id}`],
            'Fiche matière récupérée avec succès',
            'Erreur lors de la récupération de la fiche matière',
            showToast
        );
    } catch (error) {
        console.error('Erreur dans getMaFicheMatiereService:', error);
        throw error;
    }
}

export { getMaFicheMatiereService };
