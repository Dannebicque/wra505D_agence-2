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
