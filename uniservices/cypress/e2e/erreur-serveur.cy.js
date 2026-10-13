describe('Page d\'erreur serveur', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
    });

    it('ramène sur la page d\'origine quand on la recharge une fois l\'erreur passée', () => {
        cy.intercept(
            { method: 'GET', url: '**/api/me/scolarite*', times: 1 },
            { statusCode: 500, body: { detail: 'Panne simulée' } },
        );
        cy.visit('/app/intranet/scolarite');

        cy.location('pathname', { timeout: 15000 }).should('eq', '/app/500');
        cy.contains('h1', 'Erreur');
        cy.contains('Panne simulée');

        cy.reload();

        cy.location('pathname', { timeout: 15000 }).should('eq', '/app/intranet/scolarite');
        cy.contains('h2', 'Semestre S1', { timeout: 15000 });
    });

    it('ramène sur l\'accueil une page d\'erreur ouverte sans erreur', () => {
        cy.window().then((win) => win.sessionStorage.clear());
        cy.visit('/app/500');

        cy.location('pathname', { timeout: 15000 }).should('not.eq', '/app/500');
        cy.get('#contenu-principal h1', { timeout: 15000 }).should('not.contain', 'Erreur');
    });
});
