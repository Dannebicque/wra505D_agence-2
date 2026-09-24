import {describe, expect, it} from 'vitest';
import {grouperParJour, libelleJour, libelleNonLues, typeNotification} from './notifications.js';

const maintenant = new Date(2026, 8, 24, 10, 0);

describe('libelleJour', () => {
    it('dit aujourd\'hui et hier, puis la date en toutes lettres', () => {
        expect(libelleJour(new Date(2026, 8, 24, 0, 5), maintenant)).toBe('Aujourd\'hui');
        expect(libelleJour(new Date(2026, 8, 23, 23, 59), maintenant)).toBe('Hier');
        expect(libelleJour(new Date(2026, 8, 21, 8, 0), maintenant)).toBe('lundi 21 septembre');
    });
});

describe('grouperParJour', () => {
    it('regroupe par jour sans changer l\'ordre du fil', () => {
        const fil = [
            {cle: 'message-1', date: new Date(2026, 8, 24, 9, 0).toISOString()},
            {cle: 'note-1', date: new Date(2026, 8, 24, 8, 0).toISOString()},
            {cle: 'absence-1', date: new Date(2026, 8, 22, 8, 0).toISOString()},
        ];

        expect(grouperParJour(fil, maintenant).map(g => [g.jour, g.notifications.map(n => n.cle)])).toEqual([
            ['Aujourd\'hui', ['message-1', 'note-1']],
            ['mardi 22 septembre', ['absence-1']],
        ]);
    });

    it('ne renvoie rien pour un fil vide', () => {
        expect(grouperParJour([], maintenant)).toEqual([]);
    });
});

describe('libelleNonLues', () => {
    it('accorde le nombre', () => {
        expect(libelleNonLues(0)).toBe('Aucune notification non lue');
        expect(libelleNonLues(1)).toBe('1 notification non lue');
        expect(libelleNonLues(4)).toBe('4 notifications non lues');
    });
});

describe('typeNotification', () => {
    it('nomme chaque type et retombe sur une notification générique', () => {
        expect(typeNotification('message').libelle).toBe('Message');
        expect(typeNotification('inconnu')).toEqual({libelle: 'Notification', icone: 'pi pi-bell'});
    });
});
