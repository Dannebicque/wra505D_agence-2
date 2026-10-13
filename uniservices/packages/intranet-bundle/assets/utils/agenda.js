const formatJour = (date) => date.toLocaleDateString('fr-FR', {weekday: 'long', day: 'numeric', month: 'long'});

// « 8 h 00 » plutôt que « 08:00 » : c'est un texte lu par les lecteurs d'écran.
const formatHeure = (date) => `${date.getHours()} h ${String(date.getMinutes()).padStart(2, '0')}`;

export function libelleCours(cours) {
    return [
        [cours.codeModule, cours.libModule].filter(Boolean).join(' - '),
        cours.type,
        `${formatJour(cours.start)} de ${formatHeure(cours.start)} à ${formatHeure(cours.end)}`,
        cours.location,
        cours.evaluation ? 'évaluation' : null,
    ].filter(Boolean).join(', ');
}

export function annonceAgenda({vue, debut, fin, cours}) {
    const nombre = cours.filter(({start}) => start >= debut && start <= fin).length;
    const periode = vue === 'day'
        ? `Journée du ${formatJour(debut)}`
        : `Semaine du ${formatJour(debut)} au ${formatJour(fin)}`;

    return `${periode} : ${nombre === 0 ? 'aucun cours' : `${nombre} cours`}`;
}

const majuscule = (texte) => texte.charAt(0).toUpperCase() + texte.slice(1);

// « 1er octobre » : toLocaleDateString écrirait « 1 octobre ».
const jourDuMois = (date) => `${date.getDate() === 1 ? '1er' : date.getDate()} ${date.toLocaleDateString('fr-FR', {month: 'long'})}`;

export function titrePeriode({vue, debut, fin}) {
    if (vue === 'day') {
        return majuscule(`${debut.toLocaleDateString('fr-FR', {weekday: 'long'})} ${jourDuMois(debut)} ${debut.getFullYear()}`);
    }
    if (debut.getFullYear() !== fin.getFullYear()) {
        return `${jourDuMois(debut)} ${debut.getFullYear()} – ${jourDuMois(fin)} ${fin.getFullYear()}`;
    }
    if (debut.getMonth() !== fin.getMonth()) {
        return `${jourDuMois(debut)} – ${jourDuMois(fin)} ${fin.getFullYear()}`;
    }
    return `${debut.getDate() === 1 ? '1er' : debut.getDate()} – ${jourDuMois(fin)} ${fin.getFullYear()}`;
}

// L'IUT compte en semaines de formation, le calendrier en semaines ISO : on garde les deux, sur
// une seule ligne, pour qu'aucun des deux numéros ne reste sans explication.
export function libelleSemaine(semaineCalendrier, semaineFormation) {
    const calendrier = `Semaine ${semaineCalendrier}`;
    if (!semaineFormation) {
        return calendrier;
    }
    return `${calendrier} · ${semaineFormation}${semaineFormation === 1 ? 're' : 'e'} semaine de formation`;
}

export function typeSeance(cours) {
    if (cours.evaluation) {
        return 'evaluation';
    }
    if (cours.enseignement?.type === 'sae') {
        return 'sae';
    }
    const type = String(cours.type ?? '').toLowerCase();
    if (type === 'cm' || type === 'td' || type === 'tp') {
        return type;
    }
    return 'autre';
}

// Hauteurs mesurées des cartes de CoursCarte.vue : quatre lignes et leurs marges tiennent en
// 86 px, deux lignes en 50 px.
export function densiteCarte(hauteur) {
    if (hauteur >= 86) {
        return 'complete';
    }
    if (hauteur >= 50) {
        return 'compacte';
    }
    return 'ligne';
}

export function pausesDuJour(cours, minimum = 15) {
    if (cours.length < 2) {
        return [];
    }
    const tries = [...cours].sort((a, b) => a.start - b.start);
    const fusions = tries.reduce((acc, creneau) => {
        const dernier = acc[acc.length - 1];
        if (dernier && creneau.start <= dernier.end) {
            dernier.end = new Date(Math.max(dernier.end, creneau.end));
        } else {
            acc.push({start: creneau.start, end: creneau.end});
        }
        return acc;
    }, []);

    const pauses = [];
    for (let i = 1; i < fusions.length; i++) {
        const start = fusions[i - 1].end;
        const end = fusions[i].start;
        const minutes = Math.round((end - start) / 60000);
        if (minutes >= minimum) {
            pauses.push({start, end, minutes});
        }
    }
    return pauses;
}

export function coursParJour(cours) {
    const jours = new Map();
    for (const creneau of cours) {
        const {start} = creneau;
        const date = new Date(start.getFullYear(), start.getMonth(), start.getDate());
        if (!jours.has(date.getTime())) {
            jours.set(date.getTime(), {date, cours: []});
        }
        jours.get(date.getTime()).cours.push(creneau);
    }

    return [...jours.values()]
        .sort((a, b) => a.date - b.date)
        .map(({date, cours: liste}) => ({
            date,
            cours: [...liste].sort((a, b) => a.start - b.start),
        }));
}

export function joursDeLaSemaine(lundi, cours) {
    const debut = new Date(lundi.getFullYear(), lundi.getMonth(), lundi.getDate());
    const jours = [];
    for (let decalage = 0; decalage < 5; decalage++) {
        jours.push(new Date(debut.getFullYear(), debut.getMonth(), debut.getDate() + decalage));
    }
    for (let decalage = 5; decalage < 7; decalage++) {
        const jour = new Date(debut.getFullYear(), debut.getMonth(), debut.getDate() + decalage);
        const aCours = cours.some(({start}) => start.getFullYear() === jour.getFullYear()
            && start.getMonth() === jour.getMonth()
            && start.getDate() === jour.getDate());
        if (aCours) {
            jours.push(jour);
        }
    }
    return jours;
}

export function etatCours(cours, maintenant) {
    if (maintenant >= cours.end) {
        return {etat: 'passe', dans: null};
    }
    if (maintenant >= cours.start) {
        return {etat: 'en-cours', dans: null};
    }
    return {etat: 'a-venir', dans: Math.ceil((cours.start - maintenant) / 60000)};
}

// Les vacances scolaires d'un IUT, reconnues au mois de leur lundi.
const SAISONS_DE_VACANCES = [
    {saison: 'toussaint', mois: [9, 10]},
    {saison: 'noel', mois: [11]},
    {saison: 'hiver', mois: [0, 1, 2]},
    {saison: 'printemps', mois: [3, 4]},
];

export function saisonDeVacances(lundi) {
    const mois = Number(lundi.split('-')[1]) - 1;
    return SAISONS_DE_VACANCES.find(({mois: liste}) => liste.includes(mois))?.saison ?? 'ete';
}
