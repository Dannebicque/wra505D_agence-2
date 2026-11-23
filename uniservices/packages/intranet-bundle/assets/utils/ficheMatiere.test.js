import {describe, expect, it} from 'vitest';
import {jourCours, lienFicheMatiere, prochainCours, tailleFichier} from './ficheMatiere.js';

const maintenant = new Date(2026, 10, 23, 16, 30);

describe('jourCours', () => {
    it('dit aujourd\'hui et demain', () => {
        expect(jourCours(new Date(2026, 10, 23, 17, 0), maintenant)).toBe('Aujourd\'hui');
        expect(jourCours(new Date(2026, 10, 24, 8, 0), maintenant)).toBe('Demain');
    });

    it('donne la date au-delà, le premier du mois écrit 1er', () => {
        expect(jourCours(new Date(2026, 10, 27, 9, 0), maintenant)).toBe('Vendredi 27 novembre');
        expect(jourCours(new Date(2026, 11, 1, 9, 0), maintenant)).toBe('Mardi 1er décembre');
    });
});

describe('prochainCours', () => {
    const cours = {
        id: 610,
        debut: '2026-11-27T09:00:00+01:00',
        fin: '2026-11-27T11:00:00+01:00',
        type: 'TP',
        salle: 'Labo C110',
        enseignant: 'Inès ROUSSEL',
        groupe: 'A',
        evaluation: false,
        enseignement: {id: 13, type: 'ressource'},
    };

    it('lit l\'heure murale de Celcat et le type du créneau', () => {
        expect(prochainCours(cours, maintenant)).toEqual({
            id: 610,
            type: 'tp',
            etiquette: 'TP',
            jour: 'Vendredi 27 novembre',
            horaire: '9 h 00 – 11 h 00',
            enCours: false,
            salle: 'Labo C110',
            enseignant: 'Inès ROUSSEL',
            groupe: 'A',
        });
    });

    it('signale le cours déjà commencé', () => {
        const enCours = {...cours, debut: '2026-11-23T15:45:00+01:00', fin: '2026-11-23T17:45:00+01:00'};
        expect(prochainCours(enCours, maintenant)).toMatchObject({jour: 'Aujourd\'hui', enCours: true});
    });

    it('ne répète pas un groupe qui n\'est que le type', () => {
        expect(prochainCours({...cours, type: 'CM', groupe: 'CM'}, maintenant).groupe).toBeNull();
    });

    it('colore une évaluation et une SAÉ à part', () => {
        expect(prochainCours({...cours, evaluation: true}, maintenant).etiquette).toBe('Évaluation');
        expect(prochainCours({...cours, enseignement: {id: 3, type: 'sae'}}, maintenant).etiquette).toBe('SAÉ');
    });
});

describe('tailleFichier', () => {
    it('écrit la taille en Ko puis en Mo', () => {
        expect(tailleFichier(5499)).toBe('5 Ko');
        expect(tailleFichier(1468006)).toBe('1,4 Mo');
        expect(tailleFichier(0)).toBeNull();
    });
});

describe('lienFicheMatiere', () => {
    it('mène à la fiche de la matière', () => {
        expect(lienFicheMatiere(13)).toBe('/intranet/matieres/13');
    });
});
