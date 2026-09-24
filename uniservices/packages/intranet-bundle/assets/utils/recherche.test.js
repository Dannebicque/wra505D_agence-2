import {describe, expect, it} from 'vitest';
import {correspond, destinationResultat, filtrerPages, grouperResultats, normaliser} from '../../../../shared/helpers/recherche.js';

describe('normaliser', () => {
    it('ignore la casse, les accents et la ponctuation', () => {
        expect(normaliser('  Développement Front-End !')).toBe('developpement front end');
    });
});

describe('correspond', () => {
    it.each([
        ['identique', 'Agenda', 'Agenda'],
        ['début de mot', 'trombi', 'Trombinoscope'],
        ['lettres inversées', 'agneda', 'Agenda'],
        ['lettre manquante', 'scolrité', 'Scolarité'],
        ['plusieurs mots', 'cahier texte', 'Cahier de texte'],
    ])('trouve malgré les fautes : %s', (_, requete, texte) => {
        expect(correspond(requete, texte)).toBe(true);
    });

    it.each([
        ['mot court sans tolérance', 'agd', 'Agenda'],
        ['un mot sans correspondance', 'cahier notes', 'Cahier de texte'],
        ['requête vide', '  ', 'Agenda'],
    ])('ne trouve pas : %s', (_, requete, texte) => {
        expect(correspond(requete, texte)).toBe(false);
    });
});

describe('filtrerPages', () => {
    it('garde les pages dont le libellé correspond', () => {
        const pages = [{libelle: 'Agenda'}, {libelle: 'Scolarité'}, {libelle: 'Cahier de texte'}];

        expect(filtrerPages(pages, 'agnda').map((page) => page.libelle)).toEqual(['Agenda']);
    });

    it('retrouve une page par l\'un de ses mots-clés', () => {
        const pages = [{libelle: 'Emploi du temps', motsCles: ['agenda', 'edt']}, {libelle: 'Documents'}];

        expect(filtrerPages(pages, 'agneda').map((page) => page.libelle)).toEqual(['Emploi du temps']);
    });
});

describe('destinationResultat', () => {
    it('écrit à une personne', () => {
        expect(destinationResultat({type: 'personnel', mail: 'john.doe@univ-reims.fr'})).toEqual({href: 'mailto:john.doe@univ-reims.fr'});
    });

    it('ne mène nulle part pour une personne sans adresse', () => {
        expect(destinationResultat({type: 'etudiant', mail: null})).toBeNull();
    });

    it('ouvre une page, une matière dans l\'agenda, un document dans la liste des documents', () => {
        expect(destinationResultat({type: 'page', to: '/intranet/agenda'})).toEqual({to: '/intranet/agenda'});
        expect(destinationResultat({type: 'enseignement'})).toEqual({to: '/intranet/agenda'});
        expect(destinationResultat({type: 'document'})).toEqual({to: '/documents'});
    });
});

describe('grouperResultats', () => {
    it('regroupe par type, pages en tête, sans groupe vide', () => {
        const groupes = grouperResultats([
            {type: 'etudiant', libelle: 'Jane Doe'},
            {type: 'page', libelle: 'Agenda'},
            {type: 'etudiant', libelle: 'Paul Martin'},
        ]);

        expect(groupes.map((groupe) => [groupe.libelle, groupe.items.length])).toEqual([['Pages', 1], ['Étudiants', 2]]);
    });
});
