describe('Navigation de l\'étudiant', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    it('ouvre les documents depuis le menu et garde le menu sur leur page', () => {
        cy.get('.layout-menu', { timeout: 15000 }).contains('a', 'Documents').click();
        cy.location('pathname').should('eq', '/app/documents');
        cy.contains('h3', 'Matières', { timeout: 15000 });
        cy.get('.layout-menu').should('contain', 'Scolarité').and('contain', 'Documents');
        cy.get('.layout-menu').contains('a', 'Scolarité').click();
        cy.location('pathname').should('eq', '/app/intranet/scolarite');
    });
});
