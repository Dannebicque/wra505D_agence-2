import {describe, expect, it} from 'vitest';
import {annonceAgenda, libelleCours} from './agenda.js';

const cours = (jour, debut, fin, champs = {}) => {
    const [hd, md] = debut.split(':').map(Number);
    const [hf, mf] = fin.split(':').map(Number);
    return {
        codeModule: 'SAE1.01',
        libModule: 'Portfolio',
        type: 'TD',
        location: 'Salle B204',
        evaluation: false,
        start: new Date(2026, 8, jour, hd, md),
        end: new Date(2026, 8, jour, hf, mf),
        ...champs,
    };
};

describe('libelleCours', () => {
    it('donne l\'intitulé, le type, le jour, l\'horaire et la salle', () => {
        expect(libelleCours(cours(22, '08:00', '12:15')))
            .toBe('SAE1.01 - Portfolio, TD, mardi 22 septembre de 8 h 00 à 12 h 15, Salle B204');
    });

    it('commence par le code du module, seul texte visible quand deux cours se chevauchent', () => {
        expect(libelleCours(cours(22, '08:00', '10:00'))).toMatch(/^SAE1\.01/);
    });

    it('signale une évaluation', () => {
        expect(libelleCours(cours(22, '14:00', '16:00', {evaluation: true}))).toMatch(/, évaluation$/);
    });

    it('omet ce que Celcat ne renseigne pas', () => {
        expect(libelleCours(cours(22, '14:00', '16:00', {libModule: null, type: null, location: ''})))
            .toBe('SAE1.01, mardi 22 septembre de 14 h 00 à 16 h 00');
    });
});

describe('annonceAgenda', () => {
    const semaine = [
        cours(21, '08:00', '10:00'),
        cours(22, '08:00', '12:15'),
        cours(22, '14:00', '16:00'),
        cours(28, '08:00', '10:00'),
    ];
    const lundi = new Date(2026, 8, 21);
    const finVendredi = new Date(2026, 8, 25, 23, 59, 59, 999);

    it('annonce la semaine affichée et le nombre de ses cours', () => {
        expect(annonceAgenda({vue: 'week', debut: lundi, fin: finVendredi, cours: semaine}))
            .toBe('Semaine du lundi 21 septembre au vendredi 25 septembre : 3 cours');
    });

    it('annonce le jour affiché en vue jour', () => {
        const debut = new Date(2026, 8, 22);
        const fin = new Date(2026, 8, 22, 23, 59, 59, 999);
        expect(annonceAgenda({vue: 'day', debut, fin, cours: semaine}))
            .toBe('Journée du mardi 22 septembre : 2 cours');
    });

    it('dit qu\'il n\'y a aucun cours plutôt que « 0 cours »', () => {
        expect(annonceAgenda({vue: 'week', debut: lundi, fin: finVendredi, cours: []}))
            .toBe('Semaine du lundi 21 septembre au vendredi 25 septembre : aucun cours');
    });
});
