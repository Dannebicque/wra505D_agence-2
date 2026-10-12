import api from '@helpers/axios';
import apiCall from '@helpers/apiCall';
// ----------------------------------------------
// ------------------- GET ----------------------
// ----------------------------------------------

const getEtablissementService = async (showToast = false) => {
    try {
        return await apiCall(
            api.get,
            [`/api/etablissements`],
            'Établissement récupéré avec succès',
            'Erreur lors de la récupération de l\'établissement',
            showToast
        );
    } catch (error) {
        console.error('Erreur dans getEtablissementService:', error);
        throw error;
    }
}

// Réglages des intégrations, clés d'API masquées : réservés à qui peut modifier l'établissement.
const getEtablissementSettingsService = async (etablissementId, showToast = false) => {
    try {
        return await apiCall(
            api.get,
            [`/api/etablissements/${etablissementId}/settings`],
            'Réglages récupérés avec succès',
            'Erreur lors de la récupération des réglages',
            showToast
        );
    } catch (error) {
        console.error('Erreur dans getEtablissementSettingsService:', error);
        throw error;
    }
}

// ----------------------------------------------
// ------------------- CREATE -------------------
// ----------------------------------------------

// ----------------------------------------------
// ------------------- UPDATE -------------------
// ----------------------------------------------

const updateEtablissementService = async (etablissementId, data, showToast = false) => {
    try {
        return await apiCall(
            api.patch,
            [`/api/etablissements/${etablissementId}`, data, { headers: { 'Content-Type': 'application/merge-patch+json' } }],
            'Établissement mis à jour avec succès',
            'Erreur lors de la mise à jour de l\'établissement',
            showToast
        );
    } catch (error) {
        console.error('Erreur dans updateEtablissementService:', error);
        throw error;
    }
}

const uploadEtablissementLogoService = async (etablissementId, formData, showToast = false) => {
    try {
        return await apiCall(
            api.post,
            [`/api/etablissements/${etablissementId}/logo`, formData],
            'Logo mis à jour avec succès',
            'Erreur lors de la mise à jour du logo',
            showToast
        );
    } catch (error) {
        console.error('Erreur dans uploadEtablissementLogoService:', error);
        throw error;
    }
}

// ----------------------------------------------
// ------------------- DELETE -------------------
// ----------------------------------------------

export { getEtablissementService, getEtablissementSettingsService, updateEtablissementService, uploadEtablissementLogoService };
