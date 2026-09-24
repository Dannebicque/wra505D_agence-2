describe('Documents par matière et par SAÉ', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/documents');
        cy.contains('h3', 'Matières', { timeout: 15000 });
    });

    it('range les supports de cours par matière et par SAÉ', () => {
        cy.contains('h3', 'Matières').next().within(() => {
            cy.contains('button', 'R1.01 Anglais').should('contain', '2 documents');
            cy.contains('button', 'R1.02 Culture numérique').should('contain', '1 document');
            cy.contains('button', 'R1.03').should('not.exist');
        });
        cy.contains('h3', 'SAÉ').next().should('contain', 'SAE1.01');
    });

    it('n\'affiche que les documents de l\'enseignement choisi au clavier', () => {
        cy.contains('button', 'SAE1.01').focus().type('{enter}');
        cy.contains('button', 'SAE1.01').should('have.attr', 'aria-current', 'true');
        cy.contains('SAE1.01 Recommandation de communication numérique');
        cy.contains('SAÉ 1.01 : sujet et attendus');
        cy.contains('Anglais : vocabulaire du numérique').should('not.exist');

        cy.contains('button', 'Tous les documents').click();
        cy.contains('button', 'SAE1.01').should('not.have.attr', 'aria-current');
    });
});
