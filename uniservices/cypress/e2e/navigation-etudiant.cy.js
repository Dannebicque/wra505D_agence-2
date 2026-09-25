describe('Navigation de l\'étudiant', () => {
    const entrees = ['Accueil', 'Emploi du temps', 'Notes et absences', 'Documents', 'Cahier de texte'];

    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
    });

    it('arrive sur l\'accueil, sans passer par le portail', () => {
        cy.location('pathname').should('match', /^\/app\/intranet\/?$/);
        cy.visit('/app/auth/portail');
        cy.location('pathname').should('match', /^\/app\/intranet\/?$/);
    });

    it('retrouve sur l\'accueil les actualités que portait le portail', () => {
        cy.contains('Actualités du département', { timeout: 15000 });
        cy.contains('Réunion de rentrée des MMI 1');
    });

    it('garde le même menu sur chaque module', () => {
        cy.visit('/app/intranet/');
        cy.get('.layout-menu', { timeout: 15000 }).should('contain', 'Mon espace');
        entrees.forEach(entree => cy.get('.layout-menu').should('contain', entree));

        cy.get('.layout-menu').contains('a', 'Documents').click();
        cy.location('pathname').should('eq', '/app/documents');
        cy.contains('h3', 'Matières', { timeout: 15000 });
        entrees.forEach(entree => cy.get('.layout-menu').should('contain', entree));

        cy.get('.layout-menu').contains('a', 'Notes et absences').click();
        cy.location('pathname').should('eq', '/app/intranet/scolarite');
    });

    it('ne montre ni portail, ni applications, ni messagerie vide dans la barre du haut', () => {
        cy.visit('/app/intranet/');
        cy.get('.layout-topbar', { timeout: 15000 }).should('not.contain', 'Portail')
            .and('not.contain', 'Applications')
            .and('not.contain', 'Messages');
    });

    it('fait défiler le contenu seul, sans que la page ne s\'allonge sous lui', () => {
        const pageTientDansLaFenetre = (doc) => {
            expect(doc.scrollingElement.scrollHeight).to.be.at.most(doc.defaultView.innerHeight);
        };

        cy.visit('/app/intranet/');
        cy.contains('Votre département', { timeout: 15000 });
        cy.document().should(pageTientDansLaFenetre);

        cy.visit('/app/intranet/notifications');
        cy.get('.notification-lien', { timeout: 15000 }).should('exist');
        cy.document().should(pageTientDansLaFenetre);
    });

    it('ne propose pas de « Retour » sur l\'accueil, sa page d\'arrivée', () => {
        cy.visit('/app/intranet/');
        cy.contains('h1', 'Dashboard', { timeout: 15000 });
        cy.get('#contenu-principal').contains('button', 'Retour').should('not.exist');
    });

    it('range le département et retire « Paramètres » dans le menu du profil', () => {
        cy.visit('/app/intranet/');
        cy.get('[aria-controls="profile_menu"]', { timeout: 15000 }).click();
        cy.get('#profile_menu').should('contain', 'MMI')
            .and('contain', 'Profil')
            .and('not.contain', 'Paramètres');
    });
});
