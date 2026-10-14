import {tonNote} from './accueil.js';

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

/**
 * « S1 · 2026-2027 ».
 */
export const libelleSemestre = (semestre) => `${semestre.libelle} · ${(semestre.anneeUniversitaire ?? '').replace('/', '-')}`;

/**
 * Le semestre demandé par l'adresse s'il existe ; sinon le dernier semestre de l'année en cours
 * qui a déjà une note publiée, sinon le dernier de l'année en cours, sinon le plus récent.
 */
export const semestreParDefaut = (semestres, idDemande = null) => {
    const demande = semestres.find(semestre => String(semestre.id) === String(idDemande));
    if (demande) {
        return demande;
    }
    const actifs = semestres.filter(semestre => semestre.actif);
    const notes = actifs.filter(semestre => notesDuSemestre(semestre).length > 0);
    return notes.at(-1) ?? actifs.at(-1) ?? semestres.at(-1) ?? null;
};

const enseignementsDuSemestre = (semestre) => semestre.ues.flatMap(ue => ue.enseignements.map(enseignement => ({...enseignement, ue: ue.libelle})));

/**
 * Évaluations publiées qui comptent dans la moyenne, de la plus ancienne à la plus récente, avec
 * la valeur retenue : la note, ou 0 pour une absence injustifiée.
 */
export const notesDuSemestre = (semestre) => enseignementsDuSemestre(semestre)
    .flatMap(enseignement => enseignement.evaluations
        .filter(evaluation => resultatEvaluation(evaluation).compte)
        .map(evaluation => ({
            ...evaluation,
            valeur: evaluation.statut === 'absent_injustifie' ? 0 : evaluation.note,
            matiere: {code: enseignement.code, libelle: enseignement.libelle},
        })))
    .sort((a, b) => (a.date ?? '').localeCompare(b.date ?? ''));

/**
 * La moyenne de toutes les notes publiées jusqu'à chacune, pondérée par les coefficients : la
 * courbe dit si l'on progresse, sans se substituer aux moyennes d'UE.
 */
export const evolutionNotes = (semestre) => {
    let somme = 0;
    let poids = 0;
    return notesDuSemestre(semestre).map(note => {
        const coefficient = note.coefficient ?? 1;
        somme += note.valeur * coefficient;
        poids += coefficient;
        return {...note, moyenneCumulee: poids > 0 ? Math.round(somme / poids * 100) / 100 : null};
    });
};

/**
 * Ressources et SAÉ qui ont une moyenne, de la meilleure à la plus faible, pénalité comprise.
 */
export const matieresNotees = (semestre) => enseignementsDuSemestre(semestre)
    .filter(enseignement => enseignement.moyennePenalisee !== null && enseignement.moyennePenalisee !== undefined)
    .sort((a, b) => b.moyennePenalisee - a.moyennePenalisee);

const sommePenalites = (enseignements) => Math.round(enseignements.reduce((total, enseignement) => total + (enseignement.penalite ?? 0), 0) * 100) / 100;

/**
 * Les chiffres de tête du semestre. Une pénalité ne retire rien tant que sa matière n'a pas de
 * note : elle est comptée à part, elle s'appliquera dès la première.
 */
export const syntheseSemestre = (semestre) => {
    const ues = semestre.ues.filter(ue => ue.moyenne !== null && ue.moyenne !== undefined);
    const enseignements = enseignementsDuSemestre(semestre);
    const notes = enseignements.filter(enseignement => enseignement.moyenne !== null && enseignement.moyenne !== undefined);
    return {
        uesNotees: ues.length,
        uesValidees: ues.filter(ue => ue.moyenne >= 10).length,
        totalUes: semestre.ues.length,
        pointsPerdus: sommePenalites(notes),
        pointsEnSuspens: sommePenalites(enseignements.filter(enseignement => !notes.includes(enseignement))),
        absencesPenalisantes: enseignements.reduce((total, enseignement) => total + (enseignement.absencesPenalisantes ?? 0), 0),
        absencesEnAttente: enseignements.reduce((total, enseignement) => total + (enseignement.absencesEnAttente ?? 0), 0),
        notesPubliees: notesDuSemestre(semestre).length,
    };
};

/**
 * « −0,5 pt (1 absence) », ou rien sans pénalité.
 */
export const libellePenalite = (enseignement) => {
    if (!enseignement.penalite) {
        return null;
    }
    const absences = enseignement.absencesPenalisantes;
    return `−${formatNote(enseignement.penalite)} pt (${absences} absence${absences > 1 ? 's' : ''})`;
};

/**
 * La teinte de la pastille d'une évaluation : par seuil pour une note, « faible » pour l'absence
 * qui compte 0, atténuée pour un statut qui ne compte pas.
 */
export const tonEvaluation = (evaluation) => {
    const resultat = resultatEvaluation(evaluation);
    if (resultat.severite === 'danger') {
        return 'faible';
    }
    return resultat.severite ? 'statut' : tonNote(evaluation.note);
};
