import {describe, expect, it} from 'vitest';
import {ordonnerDepartements} from './contacts.js';

const departements = [
    {id: 4, libelle: 'GEA'},
    {id: 7, libelle: 'GEII'},
    {id: 3, libelle: 'MMI'},
    {id: 5, libelle: 'TC'},
];

const libelles = (liste) => liste.map(departement => departement.libelle);

describe('ordonnerDepartements', () => {
    it('place le département de l\'étudiant en premier', () => {
        expect(libelles(ordonnerDepartements(departements, 3))).toEqual(['MMI', 'GEA', 'GEII', 'TC']);
    });

    it('garde l\'ordre reçu pour les autres départements', () => {
        expect(libelles(ordonnerDepartements(departements, 5))).toEqual(['TC', 'GEA', 'GEII', 'MMI']);
    });

    it('garde l\'ordre reçu quand l\'étudiant n\'a pas de département connu', () => {
        expect(libelles(ordonnerDepartements(departements, null))).toEqual(['GEA', 'GEII', 'MMI', 'TC']);
    });

    it('ne modifie pas la liste reçue', () => {
        const copie = [...departements];
        ordonnerDepartements(departements, 3);
        expect(departements).toEqual(copie);
    });
});
