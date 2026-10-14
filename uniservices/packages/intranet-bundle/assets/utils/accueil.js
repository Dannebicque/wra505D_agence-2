import {parseEdtDate} from './prochainCours.js';
import {saisonDeVacances} from './agenda.js';

const minuit = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

/**
 * Nombre de jours calendaires entre aujourd'hui et une date AAAA-MM-JJ : 0 pour aujourd'hui,
 * 1 pour demain, quelle que soit l'heure.
 */
export function joursAvant(dateIso, aujourdhui) {
    const [annee, mois, jour] = dateIso.slice(0, 10).split('-').map(Number);
    // Arrondi : un passage à l'heure d'hiver allonge une journée d'une heure.
    return Math.round((new Date(annee, mois - 1, jour) - minuit(aujourdhui)) / 86400000);
}

export function libelleDelai(dateIso, aujourdhui) {
    const jours = joursAvant(dateIso, aujourdhui);
    if (jours <= 0) {
        return 'aujourd\'hui';
    }
    return jours === 1 ? 'demain' : `dans ${jours} jours`;
}

/** « demain » ou le nom du jour : « lundi », pour le premier cours de la semaine suivante. */
export function libelleJour(date, aujourdhui) {
    const jours = Math.round((minuit(date) - minuit(aujourdhui)) / 86400000);
    if (jours === 0) {
        return 'aujourd\'hui';
    }
    return jours === 1 ? 'demain' : date.toLocaleDateString('fr-FR', {weekday: 'long'});
}

/**
 * Premier cours d'un jour suivant, parmi des cours aux dates encore sérialisées : c'est ce qui
 * compte une fois la journée finie.
 */
export function premierCoursApres(cours, maintenant) {
    const demain = minuit(maintenant);
    demain.setDate(demain.getDate() + 1);

    return cours
        .map(seance => ({...seance, debut: parseEdtDate(seance.debut), fin: parseEdtDate(seance.fin)}))
        .filter(seance => seance.debut && seance.debut >= demain)
        .sort((a, b) => a.debut - b.debut)[0] ?? null;
}

/** Ton d'une note sur 20, pour sa couleur : sous la moyenne, entre 10 et 14, à partir de 14. */
export function tonNote(note) {
    if (note === null || note === undefined) {
        return null;
    }
    if (note < 10) {
        return 'faible';
    }
    return note < 14 ? 'moyenne' : 'bonne';
}

/**
 * Absences du relevé de /api/me/scolarite que l'étudiant doit encore régler : sans justificatif
 * ou au justificatif refusé, puis en attente de validation. L'API les donne de la plus récente
 * à la plus ancienne.
 */
export function absencesARegler(releve) {
    const absences = (releve?.semestres ?? []).flatMap(semestre => semestre.absences?.liste ?? []);

    return {
        aJustifier: absences.filter(absence => ['aucune', 'refusee'].includes(absence.justification)),
        enAttente: absences.filter(absence => absence.justification === 'en_attente'),
    };
}

/**
 * Minutes de cours de chaque jour de la semaine qui commence au lundi donné, du lundi au vendredi,
 * plus le samedi ou le dimanche s'ils portent un cours. Les chevauchements ne sont pas fusionnés :
 * c'est le temps inscrit à l'emploi du temps.
 */
export function chargeDeLaSemaine(cours, lundi) {
    const parJour = new Map();
    cours.forEach(seance => {
        const debut = parseEdtDate(seance.debut);
        const fin = parseEdtDate(seance.fin);
        if (debut && fin) {
            const cle = debut.toDateString();
            parJour.set(cle, (parJour.get(cle) ?? 0) + Math.round((fin - debut) / 60000));
        }
    });

    const jours = [];
    for (let i = 0; i < 7; i++) {
        const date = new Date(lundi.getFullYear(), lundi.getMonth(), lundi.getDate() + i);
        const minutes = parJour.get(date.toDateString()) ?? 0;
        if (i < 5 || minutes > 0) {
            jours.push({date, minutes});
        }
    }
    return jours;
}

const NOMS_DE_VACANCES = {
    toussaint: 'de la Toussaint',
    noel: 'de Noël',
    hiver: 'd\'hiver',
    printemps: 'de printemps',
    ete: 'd\'été',
};

/**
 * Prochaines vacances d'après la charge des semaines de /api/me/emploi-du-temps/semaines : la
 * première semaine à venir sans cours, suivie d'une semaine de cours (sinon c'est la fin de
 * l'année). Elles commencent le samedi qui la précède.
 */
export function prochainesVacances(semaines, aujourdhui) {
    const lundiCourant = new Date(aujourdhui.getFullYear(), aujourdhui.getMonth(), aujourdhui.getDate() - ((aujourdhui.getDay() + 6) % 7));
    const triees = [...semaines].sort((a, b) => a.lundi.localeCompare(b.lundi));
    const indice = triees.findIndex((semaine, i) => joursAvant(semaine.lundi, lundiCourant) > 0
        && semaine.minutes === 0
        && triees.slice(i + 1).some(suivante => suivante.minutes > 0));
    if (indice === -1) {
        return null;
    }

    const lundi = triees[indice].lundi;
    const [annee, mois, jour] = lundi.split('-').map(Number);
    const samedi = new Date(annee, mois - 1, jour - 2);
    return {
        debut: samedi,
        jours: Math.max(0, Math.round((samedi - minuit(aujourdhui)) / 86400000)),
        nom: NOMS_DE_VACANCES[saisonDeVacances(lundi)],
    };
}

const jourDeLAnnee = (date) => Math.round((minuit(date) - new Date(date.getFullYear(), 0, 0)) / 86400000);
const minutesDe = (cours) => cours.reduce((total, seance) => total + Math.round((seance.end - seance.start) / 60000), 0);

// Plusieurs façons de dire bonne journée, pour qu'un jour ordinaire ne ressemble pas à la veille.
const SALUTS_DU_MATIN = ['Bonne journée, {prenom}', 'Belle journée à vous', 'Bonne journée et bon courage'];

/**
 * Le message du jour de la carte de bienvenue : d'abord un vrai événement (anniversaire,
 * évaluation, vacances, rentrée, week-end), sinon un mot bienveillant selon la journée et
 * l'heure. Jamais plus de 31 caractères hors prénom : il tient sur une ligne de la carte.
 *
 * @param contexte {aujourdhui, prenom, naissance, coursDuJour, semaine, semaines, semaineFormation,
 *   vacances, evaluations} ; coursDuJour et evaluations portent start, end, codeModule, evaluation.
 * @returns {{cle: string, icone: string, texte: string, important: boolean}} important : un vrai
 *   événement, affiché même sur un téléphone.
 */
export function evenementDuJour(contexte) {
    const {aujourdhui, prenom, naissance, coursDuJour = [], semaine = [], semaines = [], semaineFormation, vacances, evaluations = []} = contexte;
    const evenement = (cle, icone, texte, important = true) => ({cle, icone, texte: texte.replace('{prenom}', prenom ?? ''), important});
    const jour = minuit(aujourdhui);
    const weekEnd = [0, 6].includes(jour.getDay());
    const joursSuivants = semaine.filter(j => minuit(j.date) > jour);
    const joursPrecedents = semaine.filter(j => minuit(j.date) < jour);
    const lundi = new Date(jour.getFullYear(), jour.getMonth(), jour.getDate() - ((jour.getDay() + 6) % 7));
    const iso = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    const semaineDu = (date) => semaines.find(s => s.lundi === iso(date));
    const lundiPrecedent = new Date(lundi.getFullYear(), lundi.getMonth(), lundi.getDate() - 7);
    const enVacances = semaineDu(lundi)?.minutes === 0 && semaines.some(s => s.lundi > iso(lundi) && s.minutes > 0)
        && semaines.some(s => s.lundi < iso(lundi) && s.minutes > 0);

    if (naissance) {
        const [, mois, jourNaissance] = naissance.slice(0, 10).split('-').map(Number);
        if (mois === jour.getMonth() + 1 && jourNaissance === jour.getDate()) {
            return evenement('anniversaire', 'pi pi-gift', 'Joyeux anniversaire, {prenom}');
        }
    }
    if (coursDuJour.some(seance => seance.evaluation)) {
        return evenement('evaluation', 'pi pi-pencil', 'Bon courage pour l\'évaluation');
    }
    if (enVacances) {
        return evenement('vacances', 'pi pi-sun', `Bonnes vacances ${NOMS_DE_VACANCES[saisonDeVacances(iso(lundi))]}`);
    }
    if (vacances && vacances.jours <= 3 && coursDuJour.length && joursSuivants.every(j => j.minutes === 0)) {
        return evenement('veille-de-vacances', 'pi pi-sun', 'Dernier jour avant les vacances');
    }
    if (coursDuJour.length && semaineDu(lundiPrecedent)?.minutes === 0 && joursPrecedents.every(j => j.minutes === 0)) {
        return evenement('retour', 'pi pi-sparkles', 'Bon retour de vacances');
    }
    if (semaineFormation === 1) {
        return evenement('rentree', 'pi pi-flag', 'Bonne rentrée');
    }
    const evaluationProche = evaluations.find(seance => minuit(seance.start) > jour);
    if (evaluationProche) {
        const nomDuJour = evaluationProche.start.toLocaleDateString('fr-FR', {weekday: 'long'});
        return evenement('evaluation-proche', 'pi pi-pencil', `Évaluation ${nomDuJour} en ${evaluationProche.codeModule}`);
    }
    if (vacances && vacances.jours <= 7) {
        return evenement('vacances-proches', 'pi pi-sun', vacances.jours > 1 ? `Vacances dans ${vacances.jours} jours` : 'Vacances demain');
    }
    if (weekEnd && !coursDuJour.length) {
        return evenement('week-end', 'pi pi-heart', 'Bon week-end');
    }

    const duJour = minutesDe(coursDuJour);
    if (jour.getDay() === 1 && coursDuJour.length) {
        return evenement('semaine', 'pi pi-heart', 'Bonne semaine, {prenom}', false);
    }
    if (coursDuJour.length && joursSuivants.every(j => j.minutes === 0)) {
        return evenement('fin-de-semaine', 'pi pi-forward', 'Bientôt le week-end, courage', false);
    }
    if (!coursDuJour.length) {
        return evenement('sans-cours', 'pi pi-face-smile', 'Journée sans cours, profitez-en', false);
    }
    if (semaine.filter(j => j.minutes > 0).length > 1 && semaine.every(j => j.minutes <= duJour)) {
        return evenement('journee-chargee', 'pi pi-bolt', 'Grosse journée, bon courage', false);
    }
    if (duJour <= 120) {
        return evenement('journee-legere', 'pi pi-face-smile', 'Journée tranquille aujourd\'hui', false);
    }
    if (aujourdhui.getHours() >= 18) {
        return evenement('soir', 'pi pi-moon', 'Bonne soirée, {prenom}', false);
    }
    if (aujourdhui.getHours() >= 12) {
        return evenement('apres-midi', 'pi pi-sun', 'Bon après-midi, {prenom}', false);
    }
    return evenement('journee', 'pi pi-sun', SALUTS_DU_MATIN[jourDeLAnnee(jour) % SALUTS_DU_MATIN.length], false);
}
