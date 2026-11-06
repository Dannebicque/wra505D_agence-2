// Sur téléphone, l'étudiant n'a plus de barre du haut : la recherche est en tête de « Plus ».
describe('Recherche sur téléphone', () => {
    beforeEach(() => {
        cy.viewport(375, 812);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
        cy.get('.bloc-accueil', { timeout: 15000 }).should('exist');
        cy.get('.navigation-bas').contains('button', 'Plus').click();
    });

    it('propose le champ en tête de « Plus », dans la largeur de l\'écran', () => {
        cy.get('#recherche-plus').should('be.visible').and(($champ) => {
            const { left, right } = $champ[0].getBoundingClientRect();
            expect(left).to.be.at.least(0);
            expect(right).to.be.at.most(375);
        });
    });

    it('trouve une page malgré une faute de frappe et l\'ouvre au clavier', () => {
        cy.get('#recherche-plus').type('agneda');
        cy.get('[role="listbox"]').should('contain', 'Emploi du temps');

        cy.get('#recherche-plus').type('{downArrow}{enter}');

        cy.location('pathname').should('eq', '/app/intranet/agenda');
        cy.get('.navigation-feuille').should('not.exist');
    });

    it('referme les propositions avec Échap, puis le panneau', () => {
        cy.get('#recherche-plus').type('agneda');
        cy.get('[role="listbox"]').should('be.visible');

        cy.get('#recherche-plus').type('{esc}');
        cy.get('[role="listbox"]').should('not.exist');
        cy.get('#recherche-plus').should('be.visible');

        cy.press(Cypress.Keyboard.Keys.ESC);
        cy.get('.navigation-feuille').should('not.exist');
    });
});
