import { describe, expect, it } from 'vitest';
import { entreeActive, menuEtudiant, navigationMobile } from './menuEtudiant.js';

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

describe('navigationMobile', () => {
    const menu = [
        {
            label: 'Mon espace',
            icon: 'e',
            items: [
                { label: 'Accueil', to: '/intranet/', onglet: 'Accueil' },
                { label: 'Emploi du temps', to: '/intranet/agenda', onglet: 'Agenda' },
                { label: 'Cahier de texte', to: '/intranet/cahier-de-texte' },
            ],
        },
        { label: 'Démarches', icon: 'd', items: [{ label: 'Mon stage', to: '/stage/etudiant' }] },
    ];

    it('met en onglets les entrées qui en portent un, et range les autres par groupe', () => {
        const { onglets, autres } = navigationMobile(menu);

        expect(onglets.map(item => item.onglet)).toEqual(['Accueil', 'Agenda']);
        expect(autres.map(groupe => [groupe.label, groupe.items.map(item => item.label)])).toEqual([
            ['Mon espace', ['Cahier de texte']],
            ['Démarches', ['Mon stage']],
        ]);
    });

    it('garde au plus quatre onglets, le reste passe dans « Plus »', () => {
        const items = ['A', 'B', 'C', 'D', 'E'].map(lettre => ({ label: lettre, to: `/${lettre}`, onglet: lettre }));
        const { onglets, autres } = navigationMobile([{ label: 'Mon espace', icon: 'e', items }]);

        expect(onglets.map(item => item.onglet)).toEqual(['A', 'B', 'C', 'D']);
        expect(autres[0].items.map(item => item.label)).toEqual(['E']);
    });

    it('transmet le libellé court du manifeste', () => {
        const [groupe] = menuEtudiant([{ name: 'documents', studentMenu: [{ label: 'Documents', icon: 'd', to: '/documents', groupe: 'scolarite', ordre: 40, onglet: 'Documents' }] }], tous);

        expect(groupe.items[0].onglet).toBe('Documents');
    });
});

describe('entreeActive', () => {
    const entrees = [
        { label: 'Accueil', to: '/intranet/' },
        { label: 'Emploi du temps', to: '/intranet/agenda' },
        { label: 'Documents', to: '/documents' },
    ];
    const active = (chemin) => entreeActive(entrees, chemin)?.label ?? null;

    it('allume l\'accueil sur sa seule page', () => {
        expect(active('/intranet')).toBe('Accueil');
        expect(active('/intranet/')).toBe('Accueil');
        expect(active('/intranet/profil')).toBeNull();
    });

    it('allume une entrée sur ses sous-pages, et la plus précise l\'emporte', () => {
        expect(active('/intranet/agenda')).toBe('Emploi du temps');
        expect(active('/documents/categorie/12')).toBe('Documents');
        expect(active('/documentation')).toBeNull();
    });
});
