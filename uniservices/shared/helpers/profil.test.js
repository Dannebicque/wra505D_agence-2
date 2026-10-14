import { describe, expect, it } from 'vitest';
import {
    champsModifies, decisionJury, groupesApresChoix, initiales, iriGroupe, lignesAdresse, moyenneSur20, normaliserAdresse,
    scolariteEnCours,
} from './profil.js';

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

const groupe = (id, type, libelle) => ({ '@id': `/api/structure_groupes/${id}`, id, type, libelle });

describe('scolariteEnCours', () => {
    const semestre = (id, libelle, actif, groupes) => ({
        semestre: { id, libelle, actif, annee: { libelle: 'MMI 1' }, typesGroupe: ['CM', 'TD', 'TP'] },
        groupes,
    });

    it('ne dit rien sans inscription', () => {
        expect(scolariteEnCours([])).toBeNull();
        expect(scolariteEnCours(null)).toBeNull();
    });

    it('lit l\'inscription active et son semestre actif, groupes dans l\'ordre du semestre', () => {
        const scolarites = [
            { actif: false, anneeUniversitaire: { libelle: '2025/2026' }, semestres: [] },
            {
                actif: true,
                anneeUniversitaire: { libelle: '2026/2027' },
                semestres: [
                    semestre(1, 'S1', false, []),
                    semestre(2, 'S2', true, [groupe(52, 'TP', 'A'), groupe(50, 'CM', 'CM'), groupe(51, 'TD', 'AB')]),
                ],
            },
        ];
        expect(scolariteEnCours(scolarites)).toEqual({
            anneeUniversitaire: '2026/2027',
            formation: 'MMI 1',
            semestre: 'S2',
            groupes: [{ type: 'CM', libelle: 'CM' }, { type: 'TD', libelle: 'AB' }, { type: 'TP', libelle: 'A' }],
        });
    });

    it('prend la première inscription et le dernier semestre faute d\'actif', () => {
        const scolarites = [{
            actif: false,
            anneeUniversitaire: { libelle: '2026/2027' },
            semestres: [semestre(1, 'S1', false, []), semestre(2, 'S2', false, [])],
        }];
        expect(scolariteEnCours(scolarites)).toMatchObject({ semestre: 'S2', groupes: [] });
    });

    it('se rabat sur l\'année de l\'inscription sans semestre', () => {
        expect(scolariteEnCours([{ actif: true, anneeUniversitaire: { libelle: '2026/2027' }, annee: [{ libelle: 'MMI 2' }] }]))
            .toEqual({ anneeUniversitaire: '2026/2027', formation: 'MMI 2', semestre: null, groupes: [] });
    });
});

describe('groupesApresChoix', () => {
    const actuels = [groupe(50, 'CM', 'CM'), groupe(51, 'TD', 'AB'), groupe(52, 'TP', 'A')];
    const disponibles = [...actuels, groupe(53, 'TP', 'B')];

    it('remplace le groupe du même type', () => {
        expect(groupesApresChoix(actuels, disponibles, 53, 'TP').map((g) => g.id)).toEqual([50, 51, 53]);
    });

    it('retire le groupe quand le choix est vide', () => {
        expect(groupesApresChoix(actuels, disponibles, null, 'TD').map((g) => g.id)).toEqual([50, 52]);
    });

    it('ajoute un type encore absent', () => {
        expect(groupesApresChoix([groupe(50, 'CM', 'CM')], disponibles, 51, 'TD').map((g) => g.id)).toEqual([50, 51]);
    });

    it('ne modifie pas la liste reçue', () => {
        groupesApresChoix(actuels, disponibles, 53, 'TP');
        expect(actuels.map((g) => g.id)).toEqual([50, 51, 52]);
    });
});

describe('iriGroupe', () => {
    it('reprend l\'IRI du groupe, ou la construit', () => {
        expect(iriGroupe(groupe(50, 'CM', 'CM'))).toBe('/api/structure_groupes/50');
        expect(iriGroupe({ id: 7, type: 'TD' })).toBe('/api/structure_groupes/7');
    });
});

describe('decisionJury', () => {
    it('distingue validé, non validé et en attente', () => {
        expect(decisionJury(true)).toEqual({ texte: 'Validé', ton: 'valide' });
        expect(decisionJury(false)).toEqual({ texte: 'Non validé', ton: 'non-valide' });
        expect(decisionJury(null)).toEqual({ texte: 'En attente', ton: 'attente' });
    });
});

describe('moyenneSur20', () => {
    it('écrit la moyenne à la française, deux décimales', () => {
        expect(moyenneSur20(13.4)).toBe('13,40 / 20');
        expect(moyenneSur20('9.875')).toBe('9,88 / 20');
    });

    it('ne dit rien sans moyenne', () => {
        expect(moyenneSur20(null)).toBeNull();
        expect(moyenneSur20(undefined)).toBeNull();
    });
});
