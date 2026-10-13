describe('Fiches du trombinoscope', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('personnel');
        cy.visit('/app/intranet/trombinoscope');
        cy.contains('h3', 'Jane Doe', { timeout: 15000 });
    });

    it('n\'affichent aucun objet de l\'API', () => {
        cy.get('#contenu-principal').invoke('text').should('not.match', /@id|@type|\/api\/|[{}]/);
    });

    it('tiennent le nom et l\'adresse dans la carte', () => {
        cy.contains('h3', 'Jane Doe').closest('.card').should(($carte) => {
            expect($carte.text()).to.contain('etudiant.user@etudiant.univ-reims.fr');
            expect($carte[0].scrollWidth).to.be.at.most($carte[0].clientWidth);
        });
    });
});
