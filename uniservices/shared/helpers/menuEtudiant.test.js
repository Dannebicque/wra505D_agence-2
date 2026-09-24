import { describe, expect, it } from 'vitest';
import { menuEtudiant } from './menuEtudiant.js';

const intranet = {
    name: 'intranet',
    studentMenu: [
        { label: 'Notes et absences', icon: 'n', to: '/intranet/scolarite', groupe: 'scolarite', ordre: 30 },
        { label: 'Accueil', icon: 'a', to: '/intranet/', groupe: 'scolarite', ordre: 10 },
    ],
};
const documents = {
    name: 'documents',
    studentMenu: [{ label: 'Documents', icon: 'd', to: '/documents', groupe: 'scolarite', ordre: 40 }],
};
const stages = {
    name: 'stages',
    studentMenu: [{ label: 'Mon stage', icon: 's', to: '/stage/etudiant', groupe: 'demarches', ordre: 10 }],
};
const sansMenuEtudiant = { name: 'questionnaire', menu: { label: 'Questionnaires', items: [] } };

const tous = () => true;

describe('menuEtudiant', () => {
    it('réunit les entrées des modules dans un groupe, dans l\'ordre voulu', () => {
        const [groupe] = menuEtudiant([intranet, documents], tous);

        expect(groupe.label).toBe('Mon espace');
        expect(groupe.items.map(item => item.label)).toEqual(['Accueil', 'Notes et absences', 'Documents']);
    });

    it('n\'ajoute que les modules actifs, et retire les groupes vides', () => {
        const menu = menuEtudiant([intranet, stages], paquet => paquet === 'intranet');

        expect(menu.map(groupe => groupe.label)).toEqual(['Mon espace']);
    });

    it('ajoute le groupe Démarches quand un module actif en déclare', () => {
        const menu = menuEtudiant([intranet, stages], tous);

        expect(menu.map(groupe => groupe.label)).toEqual(['Mon espace', 'Démarches']);
        expect(menu[1].items).toEqual([{ label: 'Mon stage', icon: 's', to: '/stage/etudiant' }]);
    });

    it('ignore les modules qui n\'ont pas d\'entrée étudiant', () => {
        expect(menuEtudiant([sansMenuEtudiant], tous)).toEqual([]);
    });
});
