import {describe, expect, it} from 'vitest';
import {formatDelai, getSituationDuJour, minutesEntre, parseEdtDate} from './prochainCours.js';

const event = (codeModule, debut, fin) => ({
    codeModule,
    debut: `2026-09-24T${debut}:00+00:00`,
    fin: `2026-09-24T${fin}:00+00:00`,
});

const journee = [
    event('R1.03', '14:00', '16:00'),
    event('R1.01', '08:00', '10:00'),
    event('R1.02', '10:15', '12:15'),
];

const a = (heure) => {
    const [h, m] = heure.split(':').map(Number);
    return new Date(2026, 8, 24, h, m);
};

describe('parseEdtDate', () => {
    it('garde l\'heure murale renvoyée par l\'API, quel que soit le décalage annoncé', () => {
        const date = parseEdtDate('2026-09-24T08:30:00+00:00');
        expect(date.getHours()).toBe(8);
        expect(date.getMinutes()).toBe(30);
        expect(date.getDate()).toBe(24);
    });

    it('renvoie null sur une valeur absente ou illisible', () => {
        expect(parseEdtDate(null)).toBeNull();
        expect(parseEdtDate('demain')).toBeNull();
    });
});

describe('getSituationDuJour', () => {
    it('annonce le premier cours avant le début de la journée', () => {
        const situation = getSituationDuJour(journee, a('07:30'));
        expect(situation.enCours).toBeNull();
        expect(situation.prochain.codeModule).toBe('R1.01');
    });

    it('distingue le cours en cours du suivant', () => {
        const situation = getSituationDuJour(journee, a('11:00'));
        expect(situation.enCours.codeModule).toBe('R1.02');
        expect(situation.prochain.codeModule).toBe('R1.03');
    });

    it('considère qu\'un cours qui se termine à l\'instant est fini', () => {
        const situation = getSituationDuJour(journee, a('10:00'));
        expect(situation.enCours).toBeNull();
        expect(situation.prochain.codeModule).toBe('R1.02');
    });

    it('n\'annonce plus rien une fois le dernier cours terminé', () => {
        const situation = getSituationDuJour(journee, a('17:00'));
        expect(situation.aDesCours).toBe(true);
        expect(situation.enCours).toBeNull();
        expect(situation.prochain).toBeNull();
    });

    it('signale une journée sans cours', () => {
        const situation = getSituationDuJour([], a('09:00'));
        expect(situation.aDesCours).toBe(false);
    });

    it('ignore un événement sans horaires exploitables', () => {
        const situation = getSituationDuJour([{codeModule: 'X', debut: null, fin: null}], a('09:00'));
        expect(situation.aDesCours).toBe(false);
    });
});

describe('minutesEntre', () => {
    it('arrondit à la minute supérieure pour ne jamais annoncer 0 min avant le début', () => {
        expect(minutesEntre(new Date(2026, 8, 24, 9, 59, 30), a('10:00'))).toBe(1);
    });

    it('ne renvoie jamais de durée négative', () => {
        expect(minutesEntre(a('10:00'), a('09:00'))).toBe(0);
    });
});

describe('formatDelai', () => {
    it.each([
        [5, '5 min'],
        [59, '59 min'],
        [60, '1 h'],
        [65, '1 h 05'],
        [150, '2 h 30'],
    ])('%i minutes donne « %s »', (minutes, attendu) => {
        expect(formatDelai(minutes)).toBe(attendu);
    });
});
