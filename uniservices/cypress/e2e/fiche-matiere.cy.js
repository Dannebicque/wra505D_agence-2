/**
 * Rapport de contraste d'un texte avec son fond, en remontant jusqu'au premier fond non
 * transparent, comme dans contrastes-secondaires.cy.js.
 */
const contraste = (element) => {
    const win = element.ownerDocument.defaultView;
    const canal = (v) => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const pinceau = element.ownerDocument.createElement('canvas').getContext('2d');
    const luminance = (couleur) => {
        pinceau.fillStyle = couleur;
        pinceau.fillRect(0, 0, 1, 1);
        const [r, g, b] = [...pinceau.getImageData(0, 0, 1, 1).data].slice(0, 3).map(canal);
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    let fond = 'rgb(255, 255, 255)';
    for (let e = element; e; e = e.parentElement) {
        const couleur = win.getComputedStyle(e).backgroundColor;
        if (couleur && couleur !== 'rgba(0, 0, 0, 0)' && couleur !== 'transparent') {
            fond = couleur;
            break;
        }
    }
    const [clair, fonce] = [luminance(win.getComputedStyle(element).color), luminance(fond)].sort((a, b) => b - a);
    return (clair + 0.05) / (fonce + 0.05);
};

// Chaque élément qui porte lui-même du texte, hors des cercles décoratifs de l'en-tête.
const pireContraste = ($fiche) => Math.min(...[...$fiche[0].querySelectorAll('*')]
    .filter(element => [...element.childNodes].some(noeud => noeud.nodeType === Node.TEXT_NODE && noeud.textContent.trim()))
    .map(contraste));

const ouvrirLaFicheDepuisLeReleve = () => {
    cy.visit('/app/intranet/scolarite');
    cy.contains('.ue', 'UE 1.1 Comprendre', { timeout: 15000 }).find('button').first().click();
    cy.contains('.ue', 'UE 1.1 Comprendre').contains('a', 'R1.06').click();
    cy.contains('h1', 'R1.06 Production graphique', { timeout: 15000 });
};

describe('Fiche matière de l\'étudiant', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
    });

    it('s\'ouvre depuis le relevé et réunit les UE, la moyenne, les notes, les absences et les documents', () => {
        ouvrirLaFicheDepuisLeReleve();
        cy.location('pathname').should('match', /^\/app\/intranet\/matieres\/\d+$/);

        cy.get('.entete').invoke('text').should('match', /S1 · \d{4}-\d{4}/);
        cy.get('.entete')
            .and('contain', 'UE 1.1 Comprendre · coef. 2')
            .and('contain', 'UE 1.3 Exprimer')
            .and('contain', 'Moyenne provisoire');
        cy.contains('section', 'Prochains cours');
        cy.contains('section', 'Notes').should('contain', 'Charte graphique').and('contain', '12 / 20')
            .and('contain', '(1 absence)');
        cy.contains('section', 'Absences').should('contain', 'Justificatif refusé')
            .and('contain', 'Le certificat ne couvre pas la date du cours.');
        cy.contains('section', 'Documents').should('contain', 'Production graphique : charte et gabarits');
    });

    it('renvoie vers le dépôt du justificatif et vers le document', () => {
        ouvrirLaFicheDepuisLeReleve();
        cy.contains('a', 'Justifier une absence').click();
        cy.location('pathname').should('eq', '/app/intranet/scolarite');
        cy.location('search').should('contain', 'onglet=absences');

        cy.go('back');
        cy.contains('a', 'Production graphique : charte et gabarits', { timeout: 15000 }).click();
        cy.location('pathname').should('eq', '/app/documents');
    });

    it('dit qu\'une matière hors de l\'année en cours est introuvable', () => {
        cy.visit('/app/intranet/matieres/999999');
        cy.contains('h1', 'Matière introuvable', { timeout: 15000 });
        cy.contains('a', 'Voir mes notes et absences').should('have.attr', 'href', '/app/intranet/scolarite');
    });

    it('lit chaque texte à 4,5:1 au moins, en clair comme en sombre', () => {
        ouvrirLaFicheDepuisLeReleve();
        // Les blocs entrent en fondu : on mesure une fois l'animation finie.
        cy.get('.bloc').last().should('have.css', 'opacity', '1');
        cy.get('.fiche').then(($fiche) => {
            expect(pireContraste($fiche)).to.be.at.least(4.5);
        });
        cy.get('button[aria-label="Mode sombre"]').click();
        cy.get('html').should('have.class', 'app-dark');
        cy.get('.fiche').then(($fiche) => {
            expect(pireContraste($fiche)).to.be.at.least(4.5);
        });
    });

    it('s\'atteint au clavier depuis le relevé', () => {
        cy.visit('/app/intranet/scolarite');
        cy.contains('.ue', 'UE 1.1 Comprendre', { timeout: 15000 }).find('button').first().click();
        cy.contains('.ue', 'UE 1.1 Comprendre').contains('a', 'R1.06').focus();
        cy.focused().should('have.css', 'outline-style', 'solid');
        cy.focused().type('{enter}');
        cy.contains('h1', 'R1.06 Production graphique', { timeout: 15000 });
    });
});
