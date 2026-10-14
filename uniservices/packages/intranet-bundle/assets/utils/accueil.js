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

