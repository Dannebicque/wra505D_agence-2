/**
 * Lundi de la semaine de rentrée, avec la même règle que CelcatFausseBaseCommand : l'année bascule
 * à la mi-août. La CI génère la fausse base Celcat le jour même, ses cours partent de ce lundi.
 */
const lundiDeLaRentree = (aujourdhui) => {
    const mois = aujourdhui.getMonth() + 1;
    const annee = (mois === 8 && aujourdhui.getDate() >= 15) || mois > 8
        ? aujourdhui.getFullYear()
        : aujourdhui.getFullYear() - 1;
    const premierSeptembre = new Date(annee, 8, 1);
    const decalage = (premierSeptembre.getDay() + 6) % 7;

    return new Date(annee, 8, 1 - decalage);
};

/**
 * Mercredi de la troisième semaine de cours, loin des vacances : l'agenda s'ouvre sur une semaine
 * qui a toujours des cours, quel que soit le jour où la CI tourne. L'horloge n'est figée qu'après
 * la connexion : le formulaire a besoin que le temps s'écoule.
 */
const ouvrirAgendaEnSemaineDeCours = () => {
    const rentree = lundiDeLaRentree(new Date());
    cy.clock(new Date(rentree.getFullYear(), rentree.getMonth(), rentree.getDate() + 16, 9, 0), ['Date']);
    cy.visit('/app/intranet/agenda');
};

describe('Agenda de l\'étudiant', () => {
    beforeEach(() => {
        cy.connexionInvite('etudiant');
        ouvrirAgendaEnSemaineDeCours();
    });

    it('affiche les cours de ses groupes', () => {
        cy.get('.vuecal__event', { timeout: 15000 }).should('have.length.greaterThan', 0);
        cy.contains('Une erreur est survenue lors du chargement de l\'emploi du temps').should('not.exist');
    });

    it('atteint un cours au clavier, ouvre son détail et rend le focus en le fermant', () => {
        cy.get('button.edt-cours', { timeout: 15000 }).should('have.length.greaterThan', 0);

        cy.contains('button', 'aujourd\'hui').focus();
        cy.press(Cypress.Keyboard.Keys.TAB);
        cy.focused().should('have.class', 'edt-cours').invoke('attr', 'aria-label').then((libelle) => {
            // Espace plutôt qu'Entrée : dans Electron, cy.press n'active pas un bouton avec Entrée.
            cy.press(Cypress.Keyboard.Keys.SPACE);
            cy.get('[role="dialog"]').should('be.visible');
            ['Appel', 'Tous présents', 'Plan de cours', 'Saisir les notes'].forEach((action) => {
                cy.get('[role="dialog"]').find(`[aria-label="${action}"]`).should('not.exist');
            });

            cy.press(Cypress.Keyboard.Keys.ESC);
            cy.get('[role="dialog"]').should('not.exist');
            cy.focused().should('have.attr', 'aria-label', libelle);
        });
    });

    it('annonce la semaine affichée quand on en change', () => {
        cy.contains('[role="status"]', 'Semaine du', { timeout: 15000 }).invoke('text').then((semaine) => {
            cy.get('button[aria-label="Semaine suivante"]').focus();
            cy.press(Cypress.Keyboard.Keys.SPACE);
            cy.contains('[role="status"]', 'Semaine du', { timeout: 15000 }).should('not.have.text', semaine);
        });
    });
});
