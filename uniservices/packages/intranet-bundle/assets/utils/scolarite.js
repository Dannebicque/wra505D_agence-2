/**
 * Libellés de la page Scolarité, à partir du relevé de /api/me/scolarite.
 * Chaque cas doit rester distinct à l'écran : un zéro n'est pas une absence, une absence
 * justifiée n'est pas une note, une note non publiée n'est pas une note manquante.
 */

const nombre = new Intl.NumberFormat('fr-FR', {maximumFractionDigits: 2});

/**
 * @param {number|null|undefined} valeur
 * @returns {string} « 14,5 », ou un tiret sans valeur
 */
export const formatNote = (valeur) => (valeur === null || valeur === undefined ? '–' : nombre.format(valeur));

/**
 * @returns {{texte: string, severite: string, compte: boolean}} severite reprend celles de PrimeVue ;
 * compte dit si le résultat entre dans la moyenne
 */
export const resultatEvaluation = (evaluation) => {
    if (evaluation.etat === 'a_venir') {
        return {texte: 'À venir', severite: 'secondary', compte: false};
    }
    if (evaluation.etat !== 'publiee') {
        return {texte: 'Pas encore publiée', severite: 'secondary', compte: false};
    }
    switch (evaluation.statut) {
        case 'absent_injustifie':
            return {texte: 'Absence injustifiée, compte 0', severite: 'danger', compte: true};
        case 'absent_justifie':
            return {texte: 'Absence justifiée, ne compte pas', severite: 'info', compte: false};
        case 'dispense':
            return {texte: 'Dispense, ne compte pas', severite: 'info', compte: false};
        default:
            return evaluation.note === null || evaluation.note === undefined
                ? {texte: 'Note non saisie', severite: 'secondary', compte: false}
                : {texte: `${formatNote(evaluation.note)} / 20`, severite: null, compte: true};
    }
};

const JUSTIFICATIONS = {
    validee: {texte: 'Justifiée', severite: 'success'},
    en_attente: {texte: 'Justificatif en attente', severite: 'warn'},
    refusee: {texte: 'Justificatif refusé', severite: 'danger'},
    aucune: {texte: 'Non justifiée', severite: 'danger'},
};

/**
 * @returns {{texte: string, severite: string}}
 */
export const justificationAbsence = (justification) => JUSTIFICATIONS[justification] ?? JUSTIFICATIONS.aucune;

/**
 * « jeudi 4 septembre 2026, 10 h 15 – 12 h 15 », à partir des dates ISO d'une absence.
 */
export const creneauAbsence = (absence) => {
    if (!absence.debut) {
        return '';
    }
    const debut = new Date(absence.debut);
    const jour = debut.toLocaleDateString('fr-FR', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC'});
    const heure = (date) => date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit', timeZone: 'UTC'}).replace(':', ' h ');
    return absence.fin ? `${jour}, ${heure(debut)} – ${heure(new Date(absence.fin))}` : `${jour}, ${heure(debut)}`;
};

/**
 * « 11/09/2026 », à partir d'une date « 2026-09-11 ».
 */
export const formatDate = (date) => (date ? new Date(date).toLocaleDateString('fr-FR', {timeZone: 'UTC'}) : '–');
