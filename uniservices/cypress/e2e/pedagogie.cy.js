// Entrée « Pédagogie » du personnel (E26.14) : elle remplace « Plans de cours », dont l'ancienne
// adresse reste valable.
describe('Pédagogie', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('personnel');
    });

    it('redirige l\'ancienne adresse des plans de cours', () => {
        cy.visit('/app/intranet/plan-de-cours');
        cy.location('pathname', { timeout: 15000 }).should('eq', '/app/intranet/pedagogie');
        cy.contains('[role="tab"]', 'Plans de cours', { timeout: 15000 }).should('be.visible');
    });

    it('n\'est pas proposée à un étudiant', () => {
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
        cy.get('.layout-menu', { timeout: 15000 }).should('not.contain', 'Pédagogie');
    });
});
