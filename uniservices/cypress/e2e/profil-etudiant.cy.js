describe('Profil de l\'étudiant', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/profil');
        cy.contains('h2', 'Informations générales', { timeout: 15000 });
    });

    it('affiche l\'identité que l\'API renvoie', () => {
        cy.contains('li', 'Prénom :').should('contain', 'Jane');
        cy.contains('li', 'Nom :').should('contain', 'Doe');
        cy.contains('li', 'Login URCA :').should('contain', 'etudiant');
        cy.contains('li', 'Mail univ :').should('contain', 'etudiant.user@etudiant.univ-reims.fr');
    });

    it('dit « non renseigné » plutôt que de laisser un champ vide', () => {
        cy.contains('li', 'Date de naissance :').should('contain', 'Non renseignée').and('not.contain', 'ans');
        ['Bac :', 'Promotion :', 'Numéro étudiant :', 'Numéro INE :'].forEach((champ) => {
            cy.contains('li', champ).invoke('text').should('match', /Non renseignée?\s*$/);
        });
    });

    it('n\'a qu\'un titre de page', () => {
        cy.get('h1').should('have.length', 1).and('contain', 'Profil');
    });

    it('copie l\'adresse universitaire au clavier', () => {
        cy.window().then((fenetre) => cy.stub(fenetre.navigator.clipboard, 'writeText').resolves().as('copie'));

        cy.contains('button', 'etudiant.user@etudiant.univ-reims.fr').focus();
        cy.press(Cypress.Keyboard.Keys.SPACE);

        cy.get('@copie').should('have.been.calledOnceWith', 'etudiant.user@etudiant.univ-reims.fr');
        cy.contains('Adresse email copiée');
    });
});
