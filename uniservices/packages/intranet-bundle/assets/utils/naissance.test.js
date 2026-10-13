import { describe, expect, it } from 'vitest';
import { formatNaissance } from '../../../../shared/helpers/date.js';

describe('formatNaissance', () => {
    const aujourdHui = new Date(2026, 9, 13);

    it('donne la date et l\'âge une fois l\'anniversaire passé', () => {
        expect(formatNaissance('2005-03-02', aujourdHui)).toBe('02/03/2005 (21 ans)');
    });

    it('ne compte pas l\'année tant que l\'anniversaire n\'est pas passé', () => {
        expect(formatNaissance('2005-10-14', aujourdHui)).toBe('14/10/2005 (20 ans)');
    });

    it('compte l\'année le jour de l\'anniversaire', () => {
        expect(formatNaissance('2005-10-13', aujourdHui)).toBe('13/10/2005 (21 ans)');
    });

    it('lit la date complète que renvoie l\'API', () => {
        expect(formatNaissance('2005-03-02T00:00:00+00:00', aujourdHui)).toBe('02/03/2005 (21 ans)');
    });

    it.each([null, undefined, '', 'inconnue'])('ne renvoie rien pour %s', (valeur) => {
        expect(formatNaissance(valeur, aujourdHui)).toBeNull();
    });
});
