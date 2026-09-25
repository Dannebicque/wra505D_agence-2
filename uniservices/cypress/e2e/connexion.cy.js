describe('Page de connexion', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.visit('/app/auth/login');
        cy.get('input[name="username"]', { timeout: 15000 });
    });

    it('mène d\'abord au formulaire, avant la présentation des applications', () => {
        cy.contains('a', 'Aller au contenu').focus();
        cy.press(Cypress.Keyboard.Keys.TAB);
        cy.focused().should('contain', 'Connexion URCA');
    });

    it('affiche le logo de l\'IUT et présente les applications sous leur vrai nom', () => {
        cy.get('img[alt="IUT de Troyes"]').should(($logo) => {
            expect($logo[0].naturalWidth).to.be.greaterThan(0);
        });
        cy.get('main ~ div li img').each(($logo) => expect($logo.attr('alt')).to.eq(''));
        cy.contains('li', 'Documents');
        cy.contains('Bundle').should('not.exist');
        cy.get('button[aria-label="Applications suivantes"]').should('have.css', 'width', '44px');
    });

    it('coche « Se souvenir de moi » depuis son libellé', () => {
        cy.contains('label', 'Se souvenir de moi').click();
        cy.get('#rememberme1').should('be.checked');
    });

    it('active « Connexion invité » dès que les deux champs sont remplis, sans quitter le mot de passe', () => {
        cy.get('input[name="username"]').type('etudiant');
        cy.get('input[name="password"]').type('test', { log: false });
        cy.focused().should('have.attr', 'name', 'password');
        cy.contains('button', 'Connexion invité').should('not.be.disabled');
    });
});
