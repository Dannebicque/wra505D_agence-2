import {tonNote} from './accueil.js';
import {parseEdtDate} from './prochainCours.js';

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

/**
 * Les semestres groupés par année universitaire, dans l'ordre du relevé.
 */
export const semestresParAnnee = (semestres) => semestres.reduce((annees, semestre) => {
    const annee = (semestre.anneeUniversitaire ?? '').replace('/', '-');
    const groupe = annees.find(candidat => candidat.annee === annee);
    if (groupe) {
        groupe.semestres.push(semestre);
    } else {
        annees.push({annee, semestres: [semestre]});
    }
    return annees;
}, []);

/**
 * Où en est une UE, sans verdict : tant que la sous-commission n'a pas siégé, rien n'est acquis
 * ni perdu.
 */
export const statutUe = (ue) => {
    if (ue.moyenne === null || ue.moyenne === undefined) {
        return {texte: 'pas encore de note', ton: null};
    }
    return ue.moyenne >= 10 ? {texte: 'au-dessus de 10', ton: 'bonne'} : {texte: 'sous 10', ton: 'faible'};
};

/**
 * Le résumé du semestre en phrases courtes, à lire avant tout le reste.
 */
export const resumeNotes = (semestre) => {
    const chiffres = syntheseSemestre(semestre);
    if (chiffres.notesPubliees === 0) {
        return ['Aucune note publiée pour ce semestre.'];
    }
    const phrases = [`${chiffres.uesValidees} UE sur ${chiffres.uesNotees} au-dessus de 10.`];
    const sous = semestre.ues.filter(ue => ue.moyenne !== null && ue.moyenne !== undefined && ue.moyenne < 10);
    if (sous.length) {
        phrases.push(`Sous 10 : ${sous.map(ue => ue.libelle).join(', ')}.`);
    }
    if (chiffres.pointsPerdus) {
        phrases.push(`Les absences non justifiées vous retirent ${formatNote(chiffres.pointsPerdus)} point${chiffres.pointsPerdus > 1 ? 's' : ''}.`);
    }
    return phrases;
};

/**
 * « 13 − 0,5 (1 absence) = 12,5 » : la moyenne brute, la pénalité et ce qui est retenu, écrits
 * en clair plutôt qu'une moyenne amputée sans explication. Rien sans pénalité.
 */
export const detailPenalite = (enseignement) => {
    if (!enseignement.penalite) {
        return null;
    }
    const absences = enseignement.absencesPenalisantes;
    const pluriel = absences > 1 ? 's' : '';
    if (enseignement.moyenne === null || enseignement.moyenne === undefined) {
        return `−${formatNote(enseignement.penalite)} pt dès la première note (${absences} absence${pluriel})`;
    }
    return `${formatNote(enseignement.moyenne)} − ${formatNote(enseignement.penalite)} (${absences} absence${pluriel}) = ${formatNote(enseignement.moyennePenalisee)}`;
};

/**
 * Les absences du semestre rangées par ce qu'elles demandent : à justifier d'abord, puis refusées
 * (à justifier de nouveau), en attente, justifiées.
 */
export const absencesParEtat = (liste) => ({
    aJustifier: liste.filter(absence => absence.justification === 'aucune'),
    refusees: liste.filter(absence => absence.justification === 'refusee'),
    enAttente: liste.filter(absence => absence.justification === 'en_attente'),
    justifiees: liste.filter(absence => absence.justification === 'validee'),
});

/**
 * Début et fin d'une absence en heure locale, pour préremplir le justificatif.
 */
export const periodeAbsence = (absence) => ({
    debut: parseEdtDate(absence.debut),
    fin: parseEdtDate(absence.fin ?? absence.debut),
});

/**
 * « 2026-09-10T13:30:00 » : l'heure murale, sans fuseau, comme le serveur range les créneaux.
 */
export const dateHeureLocale = (date) => {
    const deux = (nombre) => String(nombre).padStart(2, '0');
    return `${date.getFullYear()}-${deux(date.getMonth() + 1)}-${deux(date.getDate())}T${deux(date.getHours())}:${deux(date.getMinutes())}:00`;
};

/**
 * Ce que le serveur accepte, vérifié avant l'envoi pour répondre tout de suite.
 */
export const PIECE_JUSTIFICATIVE = {types: ['application/pdf', 'image/jpeg', 'image/png'], tailleMax: 5 * 1024 * 1024};

export const erreurPiece = (fichier) => {
    if (!fichier) {
        return 'Joignez la pièce justificative.';
    }
    if (!PIECE_JUSTIFICATIVE.types.includes(fichier.type)) {
        return 'La pièce doit être un PDF, un JPG ou un PNG.';
    }
    return fichier.size > PIECE_JUSTIFICATIVE.tailleMax ? 'La pièce dépasse 5 Mo.' : null;
};
