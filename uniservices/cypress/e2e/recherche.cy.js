describe('Recherche de la barre du haut', () => {
    beforeEach(() => {
        // Au-delà de 1024 px, le champ de recherche est celui de la barre, pas celui du menu mobile.
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    it('trouve une matière malgré une faute de frappe', () => {
        cy.get('#recherche-globale').type('devlopement');

        cy.get('[role="listbox"]').should('contain', 'Matières').and('contain', 'R1.11 Développement web');
    });

    it('ouvre une page au clavier, même cherchée sous son ancien nom', () => {
        cy.get('#recherche-globale').type('agneda');
        cy.get('[role="listbox"]').should('contain', 'Pages').and('contain', 'Emploi du temps');

        cy.get('#recherche-globale').type('{downArrow}{enter}');

        cy.location('pathname').should('eq', '/app/intranet/agenda');
    });

    it('referme les propositions avec Échap', () => {
        cy.get('#recherche-globale').type('agneda');
        cy.get('[role="listbox"]').should('be.visible');

        cy.get('#recherche-globale').type('{esc}');

        cy.get('[role="listbox"]').should('not.exist');
        cy.get('#recherche-globale').should('have.attr', 'aria-expanded', 'false');
    });

    it('présente une personne avec son statut, son adresse et l\'action proposée', () => {
        cy.get('#recherche-globale').type('jhon');

        cy.contains('[role="option"]', 'John DOE')
            .and('contain', 'Maître de conférences')
            .and('contain', '@univ-reims.fr')
            .and('contain', 'Écrire');
    });

    it('garde lisible une requête longue', () => {
        cy.get('#recherche-globale').type('recommandation de communication numerique');

        // La requête tient entière dans le champ, sans défilement horizontal.
        cy.get('#recherche-globale').should(($champ) => {
            expect($champ[0].scrollWidth).to.be.at.most($champ[0].clientWidth);
        });

        cy.get('[role="listbox"]').should('contain', 'SAE1.01 Recommandation de communication numérique');
    });

    it('n\'affiche qu\'un seul message quand rien ne correspond', () => {
        cy.get('#recherche-globale').type('zzqqww');

        cy.get('.p-autocomplete-overlay').should('have.length', 1).invoke('text').then((texte) => {
            expect(texte.match(/Aucun résultat/g)).to.have.length(1);
            expect(texte).to.contain('Aucun résultat pour « zzqqww »');
        });
    });
});
