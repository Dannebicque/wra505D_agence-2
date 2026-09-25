export const TYPES_NOTIFICATION = {
    note: {libelle: 'Note', icone: 'pi pi-graduation-cap'},
    absence: {libelle: 'Absence', icone: 'pi pi-calendar-times'},
    document: {libelle: 'Document', icone: 'pi pi-file'},
    actualite: {libelle: 'Actualité', icone: 'pi pi-megaphone'},
    message: {libelle: 'Message', icone: 'pi pi-envelope'},
};

export const typeNotification = (type) => TYPES_NOTIFICATION[type] ?? {libelle: 'Notification', icone: 'pi pi-bell'};

const debutDuJour = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

/**
 * « Aujourd'hui », « Hier », puis la date en toutes lettres.
 */
export const libelleJour = (date, maintenant = new Date()) => {
    const ecart = Math.round((debutDuJour(maintenant) - debutDuJour(date)) / 86400000);
    if (ecart === 0) return 'Aujourd\'hui';
    if (ecart === 1) return 'Hier';

    return new Intl.DateTimeFormat('fr-FR', {weekday: 'long', day: 'numeric', month: 'long'}).format(date);
};

export const heure = (date) => new Intl.DateTimeFormat('fr-FR', {hour: '2-digit', minute: '2-digit'}).format(date);

/**
 * Regroupe par jour un fil déjà trié du plus récent au plus ancien, sans changer son ordre.
 */
export const grouperParJour = (notifications, maintenant = new Date()) => {
    const groupes = [];
    for (const notification of notifications) {
        const jour = libelleJour(new Date(notification.date), maintenant);
        const dernier = groupes[groupes.length - 1];
        if (dernier?.jour === jour) {
            dernier.notifications.push(notification);
        } else {
            groupes.push({jour, notifications: [notification]});
        }
    }

    return groupes;
};

export const libelleNonLues = (nombre) => {
    if (nombre === 0) return 'Aucune notification non lue';

    return nombre === 1 ? '1 notification non lue' : `${nombre} notifications non lues`;
};
