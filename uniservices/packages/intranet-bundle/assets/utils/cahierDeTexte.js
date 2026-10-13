/**
 * Libellés de la page Cahier de texte, à partir de /api/me/cahier-de-texte.
 */

const JOUR = 24 * 60 * 60 * 1000;

const minuitUtc = (date) => Date.UTC(date.getFullYear(), date.getMonth(), date.getDate());

/**
 * « vendredi 16 octobre », à partir d'une date « 2026-10-16 ».
 */
export const jourEcheance = (date) => new Date(date).toLocaleDateString('fr-FR', {weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC'});

/**
 * « Aujourd'hui », « Demain » ou « Dans 3 jours » : le temps qu'il reste avant une échéance.
 *
 * @param {string} date « 2026-10-16 »
 * @param {Date} aujourdHui
 */
export const delaiEcheance = (date, aujourdHui = new Date()) => {
    const jours = Math.round((new Date(date).getTime() - minuitUtc(aujourdHui)) / JOUR);
    if (jours <= 0) {
        return 'Aujourd\'hui';
    }
    return jours === 1 ? 'Demain' : `Dans ${jours} jours`;
};

/**
 * « 1 h 30 » pour 1,5 heure, « 3 h » pour 3. Rien sans durée.
 *
 * @param {number|null|undefined} heures
 */
export const formatHeures = (heures) => {
    if (heures === null || heures === undefined || heures <= 0) {
        return '';
    }
    const minutes = Math.round(heures * 60);
    const reste = minutes % 60;
    return reste ? `${Math.floor(minutes / 60)} h ${String(reste).padStart(2, '0')}` : `${minutes / 60} h`;
};
