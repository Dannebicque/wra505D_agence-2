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
