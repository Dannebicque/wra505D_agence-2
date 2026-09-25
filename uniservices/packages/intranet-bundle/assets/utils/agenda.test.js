import {describe, expect, it} from 'vitest';
import {annonceAgenda, libelleCours, libelleSemaine, titrePeriode} from './agenda.js';

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

describe('titrePeriode', () => {
    it('nomme le jour affiché en entier, avec une majuscule', () => {
        expect(titrePeriode({vue: 'day', debut: new Date(2026, 8, 16)})).toBe('Mercredi 16 septembre 2026');
        expect(titrePeriode({vue: 'day', debut: new Date(2026, 9, 1)})).toBe('Jeudi 1er octobre 2026');
    });

    it('ne répète ni le mois ni l\'année d\'une semaine qui tient dans un mois', () => {
        expect(titrePeriode({vue: 'week', debut: new Date(2026, 8, 14), fin: new Date(2026, 8, 18, 23, 59)}))
            .toBe('14 – 18 septembre 2026');
    });

    it('nomme les deux mois, puis les deux années, d\'une semaine à cheval', () => {
        expect(titrePeriode({vue: 'week', debut: new Date(2026, 8, 28), fin: new Date(2026, 9, 2)}))
            .toBe('28 septembre – 2 octobre 2026');
        expect(titrePeriode({vue: 'week', debut: new Date(2026, 11, 28), fin: new Date(2027, 0, 1)}))
            .toBe('28 décembre 2026 – 1er janvier 2027');
    });
});

describe('libelleSemaine', () => {
    it('explique les deux numérotations sur une ligne', () => {
        expect(libelleSemaine(38, 2)).toBe('Semaine 38 · 2e semaine de formation');
        expect(libelleSemaine(37, 1)).toBe('Semaine 37 · 1re semaine de formation');
    });

    it('garde la seule semaine du calendrier hors des semaines de formation', () => {
        expect(libelleSemaine(52, 0)).toBe('Semaine 52');
    });
});
