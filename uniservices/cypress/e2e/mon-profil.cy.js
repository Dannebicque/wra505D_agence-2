describe('Profil de l\'étudiant', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/profil');
        cy.contains('h2', 'Coordonnées', { timeout: 15000 });
    });

    it('s\'ouvre dans l\'intranet, sous le menu de l\'étudiant, depuis le lien du compte', () => {
        cy.visit('/app/auth/profil');
        cy.location('pathname').should('eq', '/app/intranet/profil');
        cy.get('.layout-sidebar').within(() => {
            cy.contains('a', 'Accueil').should('have.attr', 'href', '/app/intranet/');
            cy.contains('a', 'Emploi du temps').should('have.attr', 'href', '/app/intranet/agenda');
        });
        cy.contains('h2', 'Coordonnées', { timeout: 15000 });
    });

    it('n\'a qu\'un titre de page', () => {
        cy.get('h1').should('have.length', 1).and('contain', 'Profil');
    });

    it('présente la carte de l\'étudiant avec son année en cours', () => {
        cy.get('#profil-nom').should('contain', 'Jane').and('contain', 'Doe');
        cy.get('.carte-etudiant').should('contain', 'Identifiant URCA').and('contain', 'etudiant');
        cy.get('.carte-etudiant [aria-label="Mes groupes"] li', { timeout: 15000 }).should('have.length.at.least', 1);
    });

    it('dit « non renseigné » plutôt que de laisser un champ vide', () => {
        cy.contains('h2', 'Informations générales').closest('section').within(() => {
            ['Date de naissance', 'Bac', 'Numéro étudiant', 'Numéro INE'].forEach((champ) => {
                cy.contains('li', champ).invoke('text').should('match', /Non renseignée?\s*$/);
            });
        });
    });

    it('copie l\'adresse universitaire au clavier', () => {
        cy.window().then((fenetre) => cy.stub(fenetre.navigator.clipboard, 'writeText').resolves().as('copie'));

        cy.contains('button', 'etudiant.user@etudiant.univ-reims.fr').focus();
        cy.press(Cypress.Keyboard.Keys.SPACE);

        cy.get('@copie').should('have.been.calledOnceWith', 'etudiant.user@etudiant.univ-reims.fr');
        cy.contains('Adresse email copiée');
    });

    it('ouvre et referme la saisie des coordonnées sans perdre le focus', () => {
        cy.contains('button', 'Modifier').click();
        cy.focused().should('have.attr', 'id', 'mailPerso');
        cy.get('#mailPerso').type('jane@exemple.fr');

        cy.contains('button', 'Annuler').click();

        cy.focused().should('contain', 'Modifier');
        cy.contains('li', 'Mail personnel').should('contain', 'Non renseigné').and('not.contain', 'jane@exemple.fr');
    });

    it('relie chaque choix de groupe à son libellé', () => {
        cy.contains('h2', 'Scolarité').closest('section').find('label[for^="groupe-"]', { timeout: 15000 })
            .should('have.length.at.least', 1)
            .each((label) => {
                cy.get(`#${label.attr('for')}`).should('exist');
            });
    });
});
