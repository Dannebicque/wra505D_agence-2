describe('Demande de convention de stage', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    // Le stage n'est pas encore dans les modules de l'étudiant (question 5 au client) : la page
    // s'ouvre par son adresse, pas par le menu.
    it('annonce l\'étape en cours', () => {
        cy.visit('/app/stage/demande');
        cy.contains('h1', 'Demande de Convention de Stage', { timeout: 15000 });
        cy.get('ol[aria-label="Étapes de la demande"] li').should('have.length', 4);
        cy.get('[aria-current="step"]').should('contain', 'Profil & Assurances');
    });

    it('explique ce qui manque au lieu de bloquer le bouton Suivant', () => {
        cy.visit('/app/stage/demande');
        cy.contains('button', 'Suivant', { timeout: 15000 })
            .should('not.be.disabled')
            .focus();
        cy.press(Cypress.Keyboard.Keys.SPACE);

        cy.contains('Veuillez sélectionner une période de stage.');
        cy.get('[aria-current="step"]').should('contain', 'Profil & Assurances');
    });
});
