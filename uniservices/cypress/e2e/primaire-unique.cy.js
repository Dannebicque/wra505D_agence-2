describe('Une seule couleur primaire', () => {
    const primaire = () => cy.document().then(d => getComputedStyle(d.documentElement).getPropertyValue('--p-primary-color').trim().toUpperCase());

    it('garde le violet de la DA sur chaque module', () => {
        cy.visit('/app/auth/login');
        cy.contains('Connexion URCA', { timeout: 15000 });
        primaire().should('eq', '#4D3677');

        cy.connexionInvite('etudiant');
        ['/app/intranet/', '/app/documents', '/app/intranet/scolarite'].forEach((page) => {
            cy.visit(page);
            cy.get('.layout-menu', { timeout: 15000 });
            primaire().should('eq', '#4D3677');
        });
    });
});
