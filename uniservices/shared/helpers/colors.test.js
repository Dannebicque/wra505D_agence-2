import { describe, expect, it } from 'vitest';
import { couleurTexteLisible } from './colors.js';

describe('couleurTexteLisible', () => {
    it('écrit en noir sur un fond clair ou moyen', () => {
        expect(couleurTexteLisible('rgb(204, 102, 102)')).toBe('#000000');
        expect(couleurTexteLisible('rgb(255, 255, 255)')).toBe('#000000');
    });

    it('écrit en blanc sur un fond foncé', () => {
        expect(couleurTexteLisible('rgb(77, 54, 119)')).toBe('#FFFFFF');
        expect(couleurTexteLisible('rgb(0, 0, 0)')).toBe('#FFFFFF');
    });

    it('accepte le format à quatre composantes de hexToRgb', () => {
        expect(couleurTexteLisible('rgb(30, 30, 30, 1)')).toBe('#FFFFFF');
    });
});
