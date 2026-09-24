import {describe, expect, it} from 'vitest';
import {dernieresNotes} from './dernieresNotes.js';

const evaluation = (libelle, date, etat = 'publiee') => ({libelle, date, etat, note: 12, statut: 'present'});

const releve = {
    semestres: [{
        libelle: 'S1',
        ues: [{
            libelle: 'UE 1.1 Comprendre',
            enseignements: [
                {code: 'R1.01', libelle: 'Anglais', evaluations: [
                    evaluation('Compréhension orale', '2026-09-11'),
                    evaluation('Expression écrite', '2026-09-18'),
                ]},
                {code: 'R1.02', libelle: 'Culture numérique', evaluations: [
                    evaluation('Examen', '2026-10-31', 'a_venir'),
                ]},
                {code: 'SAE1.01', libelle: 'Recommandation', evaluations: [
                    evaluation('Soutenance', '2026-09-22', 'en_attente'),
                ]},
                {code: 'R1.11', libelle: 'Développement web', evaluations: [
                    evaluation('TP intégration', '2026-09-13'),
                    evaluation('TP formulaires', '2026-09-20'),
                ]},
            ],
        }],
    }],
};

const libelles = (liste) => liste.map(e => e.libelle);

describe('dernieresNotes', () => {
    it('range les évaluations publiées de la plus récente à la plus ancienne', () => {
        expect(libelles(dernieresNotes(releve))).toEqual([
            'TP formulaires', 'Expression écrite', 'TP intégration', 'Compréhension orale',
        ]);
    });

    it('écarte les évaluations à venir ou pas encore publiées', () => {
        const retenues = libelles(dernieresNotes(releve));
        expect(retenues).not.toContain('Examen');
        expect(retenues).not.toContain('Soutenance');
    });

    it('rattache chaque évaluation à sa matière', () => {
        expect(dernieresNotes(releve)[0].matiere).toEqual({code: 'R1.11', libelle: 'Développement web'});
    });

    it('limite le nombre d\'évaluations retenues', () => {
        expect(libelles(dernieresNotes(releve, 2))).toEqual(['TP formulaires', 'Expression écrite']);
    });

    it('renvoie une liste vide sans relevé', () => {
        expect(dernieresNotes(null)).toEqual([]);
        expect(dernieresNotes({semestres: []})).toEqual([]);
    });
});
