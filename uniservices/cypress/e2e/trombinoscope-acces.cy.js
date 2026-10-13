// L'API refuse la liste des étudiants à un étudiant (E22) : la page lui est fermée aussi, et
// avec elle l'export des données de ses camarades.
describe('Accès au trombinoscope', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
    });

    it('est refusé à un étudiant qui en saisit l\'adresse', () => {
        cy.connexionInvite('etudiant');

        cy.visit('/app/intranet/trombinoscope');

        cy.location('pathname', { timeout: 15000 }).should('eq', '/app/access');
        cy.contains('h1', 'Accès Refusé');
        cy.contains('Exporter les données').should('not.exist');
    });

    it('n\'est proposé à l\'étudiant ni dans son menu ni dans la recherche', () => {
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');

        cy.get('#menu-principal').should('contain', 'Accueil').and('not.contain', 'Trombinoscope');
        cy.get('#recherche-globale').type('trombinoscope');
        cy.get('.p-autocomplete-overlay').should('be.visible').and('not.contain', 'Trombinoscope');
    });

    it('reste ouvert au personnel', () => {
        cy.connexionInvite('personnel');

        cy.visit('/app/intranet/trombinoscope');

        cy.contains('h1', 'Trombinoscope', { timeout: 15000 });
        cy.location('pathname').should('eq', '/app/intranet/trombinoscope');
    });
});
