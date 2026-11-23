import {parseEdtDate} from './prochainCours.js';
import {typeSeance} from './agenda.js';

/**
 * Libellés de la fiche matière, à partir de /api/me/matieres/{id}.
 */

const LIBELLES_TYPE = {cm: 'CM', td: 'TD', tp: 'TP', sae: 'SAÉ', evaluation: 'Évaluation'};

const minuit = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

// « 9 h 00 » plutôt que « 09:00 » : c'est un texte lu par les lecteurs d'écran, comme dans l'agenda.
const heure = (date) => `${date.getHours()} h ${String(date.getMinutes()).padStart(2, '0')}`;

const majuscule = (texte) => texte.charAt(0).toUpperCase() + texte.slice(1);

/**
 * « Aujourd'hui », « Demain », puis « Vendredi 27 novembre » : les prochains cours peuvent tomber
 * plusieurs semaines plus tard, le nom du jour seul ne suffit plus.
 */
export const jourCours = (date, maintenant) => {
    const jours = Math.round((minuit(date) - minuit(maintenant)) / 86400000);
    if (jours === 0) {
        return 'Aujourd\'hui';
    }
    if (jours === 1) {
        return 'Demain';
    }
    const jour = date.getDate() === 1 ? '1er' : date.getDate();
    return majuscule(`${date.toLocaleDateString('fr-FR', {weekday: 'long'})} ${jour} ${date.toLocaleDateString('fr-FR', {month: 'long'})}`);
};

/**
 * Un prochain cours mis en forme : type pour sa couleur et son étiquette, jour, horaire, et s'il
 * a déjà commencé.
 */
export const prochainCours = (cours, maintenant) => {
    const debut = parseEdtDate(cours.debut);
    const fin = parseEdtDate(cours.fin);
    const type = typeSeance(cours);
    return {
        id: cours.id,
        type,
        etiquette: type === 'autre' ? (cours.type || 'Cours') : LIBELLES_TYPE[type],
        jour: debut ? jourCours(debut, maintenant) : '',
        horaire: debut && fin ? `${heure(debut)} – ${heure(fin)}` : '',
        enCours: Boolean(debut && fin && debut <= maintenant && maintenant < fin),
        salle: cours.salle,
        enseignant: cours.enseignant,
        groupe: cours.groupe && cours.groupe !== cours.type ? cours.groupe : null,
    };
};

/**
 * « 12 Ko », « 1,4 Mo ».
 */
export const tailleFichier = (octets) => {
    if (!octets) {
        return null;
    }
    if (octets < 1024 * 1024) {
        return `${Math.max(1, Math.round(octets / 1024))} Ko`;
    }
    return `${new Intl.NumberFormat('fr-FR', {maximumFractionDigits: 1}).format(octets / (1024 * 1024))} Mo`;
};

/**
 * L'adresse de la fiche d'une matière, depuis l'agenda, le relevé ou la fiche elle-même.
 */
export const lienFicheMatiere = (id) => `/intranet/matieres/${id}`;
