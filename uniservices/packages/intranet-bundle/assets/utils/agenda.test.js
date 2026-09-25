import {describe, expect, it} from 'vitest';
import {libelleCours} from './agenda.js';

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
