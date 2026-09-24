import {describe, expect, it} from 'vitest';
import {creneauAbsence, formatDate, formatNote, justificationAbsence, resultatEvaluation} from './scolarite.js';

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
