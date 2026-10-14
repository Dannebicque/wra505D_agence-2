import { describe, expect, it } from 'vitest';
import { champsModifies, initiales, lignesAdresse, normaliserAdresse } from './profil.js';

describe('normaliserAdresse', () => {
    it('donne des chaînes vides à une adresse absente', () => {
        expect(normaliserAdresse(null)).toEqual({
            adresse: '', complement1: '', complement2: '', ville: '', codePostal: '', pays: '',
        });
    });

    it('garde les champs renseignés et complète les autres', () => {
        expect(normaliserAdresse({ adresse: '12 rue de Québec', ville: 'Troyes', complement1: null })).toEqual({
            adresse: '12 rue de Québec', complement1: '', complement2: '', ville: 'Troyes', codePostal: '', pays: '',
        });
    });
});

describe('champsModifies', () => {
    it('ne renvoie rien sans état de départ', () => {
        expect(champsModifies(null, { tel1: '0601020304' })).toEqual({});
    });

    it('ne renvoie que les champs simples qui ont changé', () => {
        const avant = { tel1: '0601020304', tel2: null, mailPerso: 'a@b.fr' };
        const apres = { tel1: '0699999999', tel2: null, mailPerso: 'a@b.fr' };
        expect(champsModifies(avant, apres)).toEqual({ tel1: '0699999999' });
    });

    it('ignore les métadonnées JSON-LD', () => {
        expect(champsModifies({ '@id': '/api/etudiants/1', '@type': 'Etudiant' }, { '@id': '/x', '@type': 'Y' })).toEqual({});
    });

    it('ne transmet que les champs modifiés d\'une adresse', () => {
        const avant = { adresseEtudiante: { adresse: '1 rue A', ville: 'Troyes', codePostal: '10000' } };
        const apres = { adresseEtudiante: { adresse: '2 rue B', ville: 'Troyes', codePostal: '10000' } };
        expect(champsModifies(avant, apres)).toEqual({ adresseEtudiante: { adresse: '2 rue B' } });
    });

    it('tient null, undefined et la chaîne vide pour égaux dans une adresse', () => {
        const avant = { adresseParentale: { adresse: null, complement1: undefined } };
        const apres = { adresseParentale: { adresse: '', complement1: '', complement2: '' } };
        expect(champsModifies(avant, apres)).toEqual({});
    });

    it('compare les tableaux par leur contenu', () => {
        expect(champsModifies({ roles: ['A'] }, { roles: ['A'] })).toEqual({});
        expect(champsModifies({ roles: ['A'] }, { roles: ['A', 'B'] })).toEqual({ roles: ['A', 'B'] });
    });

    it('compare les autres objets en profondeur', () => {
        expect(champsModifies({ bac: { id: 1 } }, { bac: { id: 1 } })).toEqual({});
        expect(champsModifies({ bac: { id: 1 } }, { bac: { id: 2 } })).toEqual({ bac: { id: 2 } });
    });
});

describe('lignesAdresse', () => {
    it('met une ligne par élément, le code postal devant la ville', () => {
        expect(lignesAdresse({
            adresse: '12 rue de Québec', complement1: 'Bât. B', complement2: '', codePostal: 10000, ville: 'Troyes', pays: 'France',
        })).toEqual(['12 rue de Québec', 'Bât. B', '10000 Troyes', 'France']);
    });

    it('n\'écrit ni null ni undefined', () => {
        expect(lignesAdresse({ adresse: '3 place Casimir', complement1: null, ville: 'Troyes' }))
            .toEqual(['3 place Casimir', 'Troyes']);
    });

    it('renvoie une liste vide quand rien n\'est renseigné', () => {
        expect(lignesAdresse(null)).toEqual([]);
        expect(lignesAdresse({ adresse: ' ', ville: '' })).toEqual([]);
    });
});

describe('initiales', () => {
    it('prend la première lettre du prénom et du nom', () => {
        expect(initiales('jane', 'Doe')).toBe('JD');
    });

    it('se contente de ce qui est connu', () => {
        expect(initiales('Jane', null)).toBe('J');
        expect(initiales(undefined, undefined)).toBe('');
    });
});
