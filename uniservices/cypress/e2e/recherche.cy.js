describe('Recherche de la barre du haut', () => {
    beforeEach(() => {
        // Au-delà de 1024 px, le champ de recherche est celui de la barre, pas celui du menu mobile.
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    it('trouve une matière malgré une faute de frappe', () => {
        cy.get('#recherche-globale').type('devlopement');

        cy.get('[role="listbox"]').should('contain', 'Matières').and('contain', 'R1.11 Développement web');
    });

    it('ouvre une page au clavier', () => {
        cy.get('#recherche-globale').type('agneda');
        cy.get('[role="listbox"]').should('contain', 'Pages').and('contain', 'Agenda');

        cy.get('#recherche-globale').type('{downArrow}{enter}');

        cy.location('pathname').should('eq', '/app/intranet/agenda');
    });

    it('referme les propositions avec Échap', () => {
        cy.get('#recherche-globale').type('agneda');
        cy.get('[role="listbox"]').should('be.visible');

        cy.get('#recherche-globale').type('{esc}');

        cy.get('[role="listbox"]').should('not.exist');
        cy.get('#recherche-globale').should('have.attr', 'aria-expanded', 'false');
    });
});
