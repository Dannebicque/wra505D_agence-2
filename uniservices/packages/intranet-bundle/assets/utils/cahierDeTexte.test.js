import {describe, expect, it} from 'vitest';
import {delaiEcheance, formatHeures, jourEcheance} from './cahierDeTexte.js';

describe('jourEcheance', () => {
    it('écrit le jour en toutes lettres, sans l\'année', () => {
        expect(jourEcheance('2026-10-16')).toBe('vendredi 16 octobre');
    });
});

describe('delaiEcheance', () => {
    const aujourdHui = new Date(2026, 9, 13, 17, 45);

    it.each([
        ['2026-10-13', 'Aujourd\'hui'],
        ['2026-10-14', 'Demain'],
        ['2026-10-16', 'Dans 3 jours'],
        ['2026-11-13', 'Dans 31 jours'],
    ])('%s donne « %s »', (date, attendu) => {
        expect(delaiEcheance(date, aujourdHui)).toBe(attendu);
    });

    it('ne dépend pas de l\'heure de la journée', () => {
        expect(delaiEcheance('2026-10-14', new Date(2026, 9, 13, 0, 5))).toBe('Demain');
        expect(delaiEcheance('2026-10-14', new Date(2026, 9, 13, 23, 55))).toBe('Demain');
    });

    it('compte juste à travers le changement d\'heure', () => {
        expect(delaiEcheance('2026-10-26', new Date(2026, 9, 24, 12, 0))).toBe('Dans 2 jours');
    });
});

describe('formatHeures', () => {
    it.each([
        [3, '3 h'],
        [1.5, '1 h 30'],
        [0.75, '0 h 45'],
        [2.25, '2 h 15'],
    ])('%s heure(s) donne « %s »', (heures, attendu) => {
        expect(formatHeures(heures)).toBe(attendu);
    });

    it.each([null, undefined, 0])('ne dit rien pour %s', (heures) => {
        expect(formatHeures(heures)).toBe('');
    });
});
