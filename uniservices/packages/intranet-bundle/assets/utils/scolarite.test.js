import {describe, expect, it} from 'vitest';
import {
    creneauAbsence,
    evolutionNotes,
    formatDate,
    formatNote,
    justificationAbsence,
    libellePenalite,
    libelleSemestre,
    matieresNotees,
    notesDuSemestre,
    resultatEvaluation,
    semestreParDefaut,
    syntheseSemestre,
    tonEvaluation,
} from './scolarite.js';

describe('formatNote', () => {
    it('écrit la note à la française', () => {
        expect(formatNote(14.5)).toBe('14,5');
        expect(formatNote(8.97)).toBe('8,97');
    });

    it('écrit un zéro, et un tiret quand il n\'y a pas de valeur', () => {
        expect(formatNote(0)).toBe('0');
        expect(formatNote(null)).toBe('–');
    });
});

describe('resultatEvaluation', () => {
    const publiee = (statut, note = null) => ({etat: 'publiee', statut, note});

    it('distingue une note, un zéro réel et une absence injustifiée', () => {
        expect(resultatEvaluation(publiee('present', 14.5)).texte).toBe('14,5 / 20');
        expect(resultatEvaluation(publiee('present', 0)).texte).toBe('0 / 20');
        expect(resultatEvaluation(publiee('absent_injustifie')).texte).toBe('Absence injustifiée, compte 0');
    });

    it('dit quand un résultat ne compte pas dans la moyenne', () => {
        expect(resultatEvaluation(publiee('absent_justifie')).compte).toBe(false);
        expect(resultatEvaluation(publiee('dispense')).compte).toBe(false);
        expect(resultatEvaluation(publiee('present', 12)).compte).toBe(true);
    });

    it('distingue une note non publiée, une évaluation à venir et une note non saisie', () => {
        expect(resultatEvaluation({etat: 'en_attente'}).texte).toBe('Pas encore publiée');
        expect(resultatEvaluation({etat: 'a_venir'}).texte).toBe('À venir');
        expect(resultatEvaluation(publiee('present', null)).texte).toBe('Note non saisie');
    });
});

describe('justificationAbsence', () => {
    it('traduit l\'état du justificatif', () => {
        expect(justificationAbsence('validee').texte).toBe('Justifiée');
        expect(justificationAbsence('en_attente').texte).toBe('Justificatif en attente');
        expect(justificationAbsence('aucune').texte).toBe('Non justifiée');
    });
});

describe('creneauAbsence', () => {
    it('donne le jour et les heures du cours manqué', () => {
        expect(creneauAbsence({debut: '2026-09-04T10:15:00+00:00', fin: '2026-09-04T12:15:00+00:00'}))
            .toBe('vendredi 4 septembre 2026, 10 h 15 – 12 h 15');
    });
});

describe('formatDate', () => {
    it('écrit la date à la française, et un tiret sans date', () => {
        expect(formatDate('2026-09-11')).toBe('11/09/2026');
        expect(formatDate(null)).toBe('–');
    });
});

const evaluation = (libelle, date, note, champs = {}) => ({libelle, date, note, coefficient: 1, etat: 'publiee', statut: 'present', ...champs});

const semestre = (champs = {}) => ({
    id: 72,
    libelle: 'S1',
    anneeUniversitaire: '2026/2027',
    actif: true,
    ues: [
        {
            libelle: 'UE 1.1', moyenne: 12.25, moyenneSansPenalite: 12.5,
            enseignements: [
                {
                    code: 'R1.01', libelle: 'Anglais', moyenne: 13, moyennePenalisee: 12.5, penalite: 0.5, absencesPenalisantes: 1, absencesEnAttente: 1,
                    evaluations: [
                        evaluation('Oral', '2026-10-12', 16, {coefficient: 2}),
                        evaluation('Écrit', '2026-09-20', null, {statut: 'absent_injustifie'}),
                        evaluation('Quiz', '2026-10-01', null, {statut: 'absent_justifie'}),
                        evaluation('Partiel', '2026-12-01', null, {etat: 'a_venir', statut: null}),
                    ],
                },
                {code: 'R1.02', libelle: 'Culture', moyenne: null, moyennePenalisee: null, penalite: 0, absencesPenalisantes: 0, absencesEnAttente: 0, evaluations: []},
            ],
        },
        {
            libelle: 'UE 1.2', moyenne: 8, moyenneSansPenalite: 8,
            enseignements: [
                {code: 'R1.10', libelle: 'Intégration', moyenne: 8, moyennePenalisee: 8, penalite: 0, absencesPenalisantes: 0, absencesEnAttente: 0, evaluations: [evaluation('TP', '2026-10-05', 8)]},
            ],
        },
        {libelle: 'UE 1.3', moyenne: null, moyenneSansPenalite: null, enseignements: []},
    ],
    ...champs,
});

describe('libelleSemestre', () => {
    it('nomme le semestre et son année', () => {
        expect(libelleSemestre(semestre())).toBe('S1 · 2026-2027');
    });
});

describe('semestreParDefaut', () => {
    const ancien = semestre({id: 10, actif: false});
    const vide = semestre({id: 73, libelle: 'S2', ues: []});
    const courant = semestre();

    it('prend celui de l\'adresse quand il existe', () => {
        expect(semestreParDefaut([ancien, courant, vide], '10')).toBe(ancien);
    });

    it('ouvre sinon le dernier semestre de l\'année qui a déjà une note', () => {
        expect(semestreParDefaut([ancien, courant, vide], '999')).toBe(courant);
    });

    it('se rabat sur le dernier semestre de l\'année, puis sur le plus récent', () => {
        expect(semestreParDefaut([ancien, vide])).toBe(vide);
        expect(semestreParDefaut([ancien])).toBe(ancien);
        expect(semestreParDefaut([])).toBeNull();
    });
});

describe('notesDuSemestre', () => {
    it('ne garde que ce qui compte, par date, l\'absence injustifiée valant 0', () => {
        expect(notesDuSemestre(semestre()).map(note => [note.libelle, note.valeur])).toEqual([
            ['Écrit', 0],
            ['TP', 8],
            ['Oral', 16],
        ]);
    });
});

describe('evolutionNotes', () => {
    it('suit la moyenne pondérée de toutes les notes publiées jusque-là', () => {
        expect(evolutionNotes(semestre()).map(note => note.moyenneCumulee)).toEqual([0, 4, 10]);
    });
});

describe('matieresNotees', () => {
    it('classe les matières notées de la meilleure à la plus faible, pénalité comprise', () => {
        expect(matieresNotees(semestre()).map(matiere => [matiere.code, matiere.ue])).toEqual([
            ['R1.01', 'UE 1.1'],
            ['R1.10', 'UE 1.2'],
        ]);
    });
});

describe('syntheseSemestre', () => {
    it('compte les UE validées parmi celles qui ont une moyenne, et les points perdus', () => {
        expect(syntheseSemestre(semestre())).toEqual({
            uesNotees: 2,
            uesValidees: 1,
            totalUes: 3,
            pointsPerdus: 0.5,
            pointsEnSuspens: 0,
            absencesPenalisantes: 1,
            absencesEnAttente: 1,
            notesPubliees: 3,
        });
    });
});

describe('libellePenalite', () => {
    it('dit la pénalité et le nombre d\'absences, rien sans pénalité', () => {
        expect(libellePenalite({penalite: 1.5, absencesPenalisantes: 3})).toBe('−1,5 pt (3 absences)');
        expect(libellePenalite({penalite: 0.5, absencesPenalisantes: 1})).toBe('−0,5 pt (1 absence)');
        expect(libellePenalite({penalite: 0, absencesPenalisantes: 0})).toBeNull();
    });
});

describe('tonEvaluation', () => {
    it('teinte une note par seuil, l\'absence qui compte 0 en faible, un statut en atténué', () => {
        expect(tonEvaluation(evaluation('A', '2026-10-01', 15))).toBe('bonne');
        expect(tonEvaluation(evaluation('B', '2026-10-01', 9))).toBe('faible');
        expect(tonEvaluation(evaluation('C', '2026-10-01', null, {statut: 'absent_injustifie'}))).toBe('faible');
        expect(tonEvaluation(evaluation('D', '2026-10-01', null, {statut: 'absent_justifie'}))).toBe('statut');
    });
});

describe('syntheseSemestre, pénalité d\'une matière sans note', () => {
    it('la met en suspens : elle ne retire rien avant la première note', () => {
        const sansNote = semestre();
        sansNote.ues[0].enseignements[1] = {...sansNote.ues[0].enseignements[1], penalite: 1, absencesPenalisantes: 2};
        expect(syntheseSemestre(sansNote)).toMatchObject({pointsPerdus: 0.5, pointsEnSuspens: 1});
    });
});
