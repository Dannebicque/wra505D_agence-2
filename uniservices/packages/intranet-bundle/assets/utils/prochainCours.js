// L'API sérialise l'heure murale de Celcat avec un décalage +00:00 : on relit les composants
// tels quels plutôt que de laisser Date convertir depuis l'UTC.
export function parseEdtDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value ?? '');
    if (!match) return null;
    const [, annee, mois, jour, heures, minutes] = match.map(Number);
    return new Date(annee, mois - 1, jour, heures, minutes);
}

export function getSituationDuJour(events, now) {
    const cours = events
        .map(event => ({...event, debut: parseEdtDate(event.debut), fin: parseEdtDate(event.fin)}))
        .filter(event => event.debut && event.fin)
        .sort((a, b) => a.debut - b.debut);

    return {
        aDesCours: cours.length > 0,
        enCours: cours.find(event => event.debut <= now && now < event.fin) ?? null,
        prochain: cours.find(event => event.debut > now) ?? null,
    };
}

export function minutesEntre(debut, fin) {
    return Math.max(0, Math.ceil((fin - debut) / 60000));
}

export function formatDelai(minutes) {
    if (minutes < 60) return `${minutes} min`;
    const heures = Math.floor(minutes / 60);
    const reste = minutes % 60;
    return reste === 0 ? `${heures} h` : `${heures} h ${String(reste).padStart(2, '0')}`;
}
