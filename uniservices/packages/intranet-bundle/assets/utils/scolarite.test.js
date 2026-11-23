import {describe, expect, it} from 'vitest';
import {
    absencesParEtat,
    absencesParMatiere,
    creneauAbsence,
    dateHeureLocale,
    detailPenalite,
    erreurPiece,
    evolutionUe,
    formatDate,
    formatNote,
    justificationAbsence,
    libelleSemestre,
    periodeAbsence,
    poidsMatiere,
    notesDuSemestre,
    resultatEvaluation,
    semestreParDefaut,
    semestresParAnnee,
    statutUe,
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


describe('tonEvaluation', () => {
    it('teinte une note par seuil, l\'absence qui compte 0 en faible, un statut en atténué', () => {
        expect(tonEvaluation(evaluation('A', '2026-10-01', 15))).toBe('bonne');
        expect(tonEvaluation(evaluation('B', '2026-10-01', 9))).toBe('faible');
        expect(tonEvaluation(evaluation('C', '2026-10-01', null, {statut: 'absent_injustifie'}))).toBe('faible');
        expect(tonEvaluation(evaluation('D', '2026-10-01', null, {statut: 'absent_justifie'}))).toBe('statut');
    });
});

describe('syntheseSemestre, matière commune à deux UE', () => {
    it('compte une seule fois ses notes et sa pénalité', () => {
        const anglais = {code: 'R1.01', libelle: 'Anglais', moyenne: 13, moyennePenalisee: 12.5, penalite: 0.5, absencesPenalisantes: 1, absencesEnAttente: 0, evaluations: [evaluation('Oral', '2026-10-12', 16)]};
        const chiffres = syntheseSemestre(semestre({ues: [
            {libelle: 'UE 1.1', moyenne: 12.5, enseignements: [anglais]},
            {libelle: 'UE 1.3', moyenne: 12.5, enseignements: [anglais]},
        ]}));
        expect(chiffres.notesPubliees).toBe(1);
        expect(chiffres.pointsPerdus).toBe(0.5);
        expect(chiffres.absencesPenalisantes).toBe(1);
    });
});

describe('syntheseSemestre, pénalité d\'une matière sans note', () => {
    it('la met en suspens : elle ne retire rien avant la première note', () => {
        const sansNote = semestre();
        sansNote.ues[0].enseignements[1] = {...sansNote.ues[0].enseignements[1], penalite: 1, absencesPenalisantes: 2};
        expect(syntheseSemestre(sansNote)).toMatchObject({pointsPerdus: 0.5, pointsEnSuspens: 1});
    });
});

describe('semestresParAnnee', () => {
    it('range les semestres sous leur année, dans l\'ordre du relevé', () => {
        const groupes = semestresParAnnee([
            semestre({id: 1, anneeUniversitaire: '2025/2026'}),
            semestre({id: 2, anneeUniversitaire: '2026/2027'}),
            semestre({id: 3, libelle: 'S2', anneeUniversitaire: '2026/2027'}),
        ]);
        expect(groupes.map(groupe => [groupe.annee, groupe.semestres.map(s => s.id)])).toEqual([
            ['2025-2026', [1]],
            ['2026-2027', [2, 3]],
        ]);
    });
});

describe('statutUe', () => {
    it('dit où en est l\'UE par rapport à 10, sans verdict', () => {
        expect(statutUe({moyenne: 10})).toEqual({texte: 'au-dessus de 10', ton: 'bonne'});
        expect(statutUe({moyenne: 9.99})).toEqual({texte: 'sous 10', ton: 'faible'});
        expect(statutUe({moyenne: null})).toEqual({texte: 'pas encore de note', ton: null});
    });
});

describe('evolutionUe', () => {
    const ue = {
        enseignements: [
            {
                code: 'R1.01', coefficient: 1, penalite: 0.5,
                evaluations: [
                    evaluation('Écrit', '2026-09-20', null, {statut: 'absent_injustifie'}),
                    evaluation('Quiz', '2026-09-25', null, {statut: 'absent_justifie'}),
                    evaluation('Oral', '2026-10-12', 16, {coefficient: 2}),
                    evaluation('Partiel', '2026-12-01', null, {etat: 'a_venir', statut: null}),
                ],
            },
            {code: 'R1.10', coefficient: 2, penalite: 0, evaluations: [evaluation('TP', '2026-10-05', 8)]},
            {code: 'R1.02', coefficient: 3, penalite: 1, evaluations: []},
        ],
    };

    it('recalcule la moyenne de l\'UE à chaque date de note, comme le serveur', () => {
        expect(evolutionUe(ue)).toEqual([
            {date: '2026-09-20', moyenne: 0, nouvelles: [{libelle: 'Écrit', matiere: 'R1.01'}]},
            {date: '2026-10-05', moyenne: 5.33, nouvelles: [{libelle: 'TP', matiere: 'R1.10'}]},
            {date: '2026-10-12', moyenne: 8.72, nouvelles: [{libelle: 'Oral', matiere: 'R1.01'}]},
        ]);
    });

    it('ne trace rien sans note publiée', () => {
        expect(evolutionUe({enseignements: [{code: 'R1.02', coefficient: 1, evaluations: [evaluation('Partiel', '2026-12-01', null, {etat: 'a_venir'})]}]})).toEqual([]);
    });
});

describe('poidsMatiere', () => {
    it('donne la part du coefficient de la matière dans son UE', () => {
        const ue = {enseignements: [{coefficient: 3}, {coefficient: 1}, {coefficient: 2}]};
        expect(ue.enseignements.map(enseignement => poidsMatiere(ue, enseignement))).toEqual([50, 17, 33]);
        expect(poidsMatiere({enseignements: []}, {coefficient: 1})).toBe(0);
    });
});

describe('absencesParMatiere', () => {
    it('compte les absences de chaque matière par état, la plus touchée d\'abord', () => {
        expect(absencesParMatiere([
            {matiere: 'R1.02 Culture', justification: 'en_attente'},
            {matiere: 'R1.10 Intégration', justification: 'aucune'},
            {matiere: 'R1.10 Intégration', justification: 'refusee'},
            {matiere: 'R1.10 Intégration', justification: 'validee'},
            {matiere: '', justification: 'validee'},
        ])).toEqual([
            {matiere: 'R1.10 Intégration', aJustifier: 2, enAttente: 0, justifiees: 1},
            {matiere: 'Cours', aJustifier: 0, enAttente: 0, justifiees: 1},
            {matiere: 'R1.02 Culture', aJustifier: 0, enAttente: 1, justifiees: 0},
        ]);
    });
});

describe('detailPenalite', () => {
    it('écrit la moyenne brute, la pénalité et la moyenne retenue', () => {
        expect(detailPenalite({moyenne: 13, penalite: 0.5, absencesPenalisantes: 1, moyennePenalisee: 12.5})).toBe('13 − 0,5 (1 absence) = 12,5');
    });

    it('annonce la pénalité d\'une matière sans note, et ne dit rien sans pénalité', () => {
        expect(detailPenalite({moyenne: null, penalite: 1, absencesPenalisantes: 2, moyennePenalisee: null})).toBe('−1 pt dès la première note (2 absences)');
        expect(detailPenalite({moyenne: 12, penalite: 0, absencesPenalisantes: 0, moyennePenalisee: 12})).toBeNull();
    });
});

describe('absencesParEtat', () => {
    it('range les absences par ce qu\'elles demandent', () => {
        const groupes = absencesParEtat(['aucune', 'refusee', 'en_attente', 'validee', 'aucune'].map((justification, rang) => ({rang, justification})));
        expect(Object.fromEntries(Object.entries(groupes).map(([etat, liste]) => [etat, liste.map(absence => absence.rang)]))).toEqual({
            aJustifier: [0, 4],
            refusees: [1],
            enAttente: [2],
            justifiees: [3],
        });
    });
});

describe('periodeAbsence et dateHeureLocale', () => {
    it('reprennent l\'heure murale du créneau, aller et retour', () => {
        const periode = periodeAbsence({debut: '2026-09-10T13:30:00+00:00', fin: '2026-09-10T15:30:00+00:00'});
        expect(dateHeureLocale(periode.debut)).toBe('2026-09-10T13:30:00');
        expect(dateHeureLocale(periode.fin)).toBe('2026-09-10T15:30:00');
    });
});

describe('erreurPiece', () => {
    it('exige une pièce PDF, JPG ou PNG de 5 Mo au plus', () => {
        expect(erreurPiece(null)).toBe('Joignez la pièce justificative.');
        expect(erreurPiece({type: 'text/plain', size: 10})).toBe('La pièce doit être un PDF, un JPG ou un PNG.');
        expect(erreurPiece({type: 'application/pdf', size: 6 * 1024 * 1024})).toBe('La pièce dépasse 5 Mo.');
        expect(erreurPiece({type: 'image/png', size: 2000})).toBeNull();
    });
});
