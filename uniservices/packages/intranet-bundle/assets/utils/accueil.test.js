import {describe, expect, it} from 'vitest';
import {
    absencesARegler,
    joursAvant,
    libelleDelai,
    libelleJour,
    premierCoursApres,
    tonNote,
} from './accueil.js';

const cours = (codeModule, debut, fin) => ({codeModule, debut, fin});

describe('joursAvant', () => {
    it('compte 0 jour pour aujourd\'hui, même tard le soir', () => {
        expect(joursAvant('2026-10-13T08:00:00+00:00', new Date(2026, 9, 13, 23, 59))).toBe(0);
    });

    it('compte 1 jour pour demain', () => {
        expect(joursAvant('2026-10-14T08:00:00+00:00', new Date(2026, 9, 13, 12, 0))).toBe(1);
    });

    it('compte un nombre négatif pour une date passée', () => {
        expect(joursAvant('2026-10-10T08:00:00+00:00', new Date(2026, 9, 13, 12, 0))).toBe(-3);
    });

    it('compte 2 jours à travers le passage à l\'heure d\'hiver', () => {
        expect(joursAvant('2026-10-26T08:00:00+00:00', new Date(2026, 9, 24, 12, 0))).toBe(2);
    });

    // La nuit de l'heure d'été ne dure que 23 h : sans arrondi, deux jours en feraient un.
    it('compte 2 jours à travers le passage à l\'heure d\'été', () => {
        expect(joursAvant('2026-03-30', new Date(2026, 2, 28, 12, 0))).toBe(2);
    });
});

describe('libelleDelai', () => {
    it('annonce « aujourd\'hui » pour le jour même', () => {
        expect(libelleDelai('2026-10-13T08:00:00+00:00', new Date(2026, 9, 13, 9, 0))).toBe('aujourd\'hui');
    });

    it('annonce « demain » pour le lendemain', () => {
        expect(libelleDelai('2026-10-14T08:00:00+00:00', new Date(2026, 9, 13, 9, 0))).toBe('demain');
    });

    it('annonce « dans 18 jours » pour une date plus lointaine', () => {
        expect(libelleDelai('2026-10-31T08:00:00+00:00', new Date(2026, 9, 13, 9, 0))).toBe('dans 18 jours');
    });

    it('annonce « aujourd\'hui » pour une date passée', () => {
        expect(libelleDelai('2026-10-10T08:00:00+00:00', new Date(2026, 9, 13, 9, 0))).toBe('aujourd\'hui');
    });
});

describe('libelleJour', () => {
    it('annonce « aujourd\'hui » pour le même jour', () => {
        expect(libelleJour(new Date(2026, 9, 13, 8, 0), new Date(2026, 9, 13, 20, 0))).toBe('aujourd\'hui');
    });

    it('annonce « demain » pour le lendemain', () => {
        expect(libelleJour(new Date(2026, 9, 14, 8, 0), new Date(2026, 9, 13, 20, 0))).toBe('demain');
    });

    it('annonce « lundi » pour un lundi vu depuis un vendredi', () => {
        expect(libelleJour(new Date(2026, 9, 19, 8, 0), new Date(2026, 9, 16, 8, 0))).toBe('lundi');
    });
});

describe('premierCoursApres', () => {
    it('ignore les cours du jour même, même encore à venir', () => {
        const liste = [
            cours('CE_JOUR', '2026-10-13T18:00:00+00:00', '2026-10-13T20:00:00+00:00'),
            cours('DEMAIN', '2026-10-14T08:00:00+00:00', '2026-10-14T10:00:00+00:00'),
        ];
        expect(premierCoursApres(liste, new Date(2026, 9, 13, 12, 0)).codeModule).toBe('DEMAIN');
    });

    it('renvoie le plus tôt des jours suivants, même si la liste n\'est pas triée', () => {
        const liste = [
            cours('APRES_DEMAIN', '2026-10-15T08:00:00+00:00', '2026-10-15T10:00:00+00:00'),
            cours('DEMAIN_TARD', '2026-10-14T16:00:00+00:00', '2026-10-14T18:00:00+00:00'),
            cours('DEMAIN_TOT', '2026-10-14T08:00:00+00:00', '2026-10-14T10:00:00+00:00'),
        ];
        expect(premierCoursApres(liste, new Date(2026, 9, 13, 12, 0)).codeModule).toBe('DEMAIN_TOT');
    });

    it('renvoie null quand aucun cours n\'est à venir', () => {
        expect(premierCoursApres([], new Date(2026, 9, 13, 12, 0))).toBeNull();
        expect(premierCoursApres(
            [cours('CE_JOUR', '2026-10-13T18:00:00+00:00', '2026-10-13T20:00:00+00:00')],
            new Date(2026, 9, 13, 12, 0),
        )).toBeNull();
    });

    it('renvoie debut et fin convertis en Date', () => {
        const liste = [cours('DEMAIN', '2026-10-14T08:30:00+00:00', '2026-10-14T10:00:00+00:00')];
        const prochain = premierCoursApres(liste, new Date(2026, 9, 13, 12, 0));
        expect(prochain.debut).toBeInstanceOf(Date);
        expect(prochain.debut).toEqual(new Date(2026, 9, 14, 8, 30));
        expect(prochain.fin).toEqual(new Date(2026, 9, 14, 10, 0));
    });
});

describe('tonNote', () => {
    it('renvoie null pour une note absente', () => {
        expect(tonNote(null)).toBeNull();
        expect(tonNote(undefined)).toBeNull();
    });

    it.each([
        [9.99, 'faible'],
        [10, 'moyenne'],
        [13.99, 'moyenne'],
        [14, 'bonne'],
        [0, 'faible'],
    ])('%s sur 20 donne « %s »', (note, attendu) => {
        expect(tonNote(note)).toBe(attendu);
    });
});

describe('absencesARegler', () => {
    it('répartit les absences sans justificatif ou refusées, en attente, et ignore les validées', () => {
        const releve = {
            semestres: [{
                absences: {
                    liste: [
                        {id: 1, justification: 'aucune'},
                        {id: 2, justification: 'validee'},
                        {id: 3, justification: 'en_attente'},
                    ],
                },
            }],
        };
        const {aJustifier, enAttente} = absencesARegler(releve);
        expect(aJustifier.map(absence => absence.id)).toEqual([1]);
        expect(enAttente.map(absence => absence.id)).toEqual([3]);
    });

    it('réunit les absences de plusieurs semestres dans l\'ordre', () => {
        const releve = {
            semestres: [
                {absences: {liste: [
                    {id: 1, justification: 'aucune'},
                    {id: 2, justification: 'en_attente'},
                ]}},
                {absences: {liste: [
                    {id: 3, justification: 'refusee'},
                    {id: 4, justification: 'en_attente'},
                ]}},
            ],
        };
        const {aJustifier, enAttente} = absencesARegler(releve);
        expect(aJustifier.map(absence => absence.id)).toEqual([1, 3]);
        expect(enAttente.map(absence => absence.id)).toEqual([2, 4]);
    });

    it('renvoie deux listes vides sans relevé ou sans absences', () => {
        expect(absencesARegler(null)).toEqual({aJustifier: [], enAttente: []});
        expect(absencesARegler({semestres: [{absences: {liste: []}}]}))
            .toEqual({aJustifier: [], enAttente: []});
    });
});
