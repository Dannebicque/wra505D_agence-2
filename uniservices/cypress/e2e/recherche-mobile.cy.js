describe('Recherche sur téléphone', () => {
    beforeEach(() => {
        cy.viewport(375, 812);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
        cy.get('.dashboard-grid > *', { timeout: 15000 }).should('exist');
    });

    it('propose un bouton de 44 px qui ouvre le champ et lui donne le focus', () => {
        cy.get('#recherche-globale-mobile').should('not.exist');

        cy.get('button[aria-label="Rechercher"]')
            .should('be.visible')
            .and('have.attr', 'aria-expanded', 'false')
            .and(($bouton) => {
                const { width, height } = $bouton[0].getBoundingClientRect();
                expect(width).to.be.at.least(44);
                expect(height).to.be.at.least(44);
            })
            .focus();
        cy.press(Cypress.Keyboard.Keys.SPACE);

        cy.get('button[aria-label="Rechercher"]').should('have.attr', 'aria-expanded', 'true');
        cy.focused().should('have.id', 'recherche-globale-mobile');
        cy.get('#recherche-mobile').should(($panneau) => {
            const { left, right } = $panneau[0].getBoundingClientRect();
            expect(left).to.be.at.least(0);
            expect(right).to.be.at.most(375);
        });
    });

    it('trouve une page malgré une faute de frappe et l\'ouvre au clavier', () => {
        cy.get('button[aria-label="Rechercher"]').click();
        cy.get('#recherche-globale-mobile').type('agneda');
        cy.get('[role="listbox"]').should('contain', 'Emploi du temps');

        cy.get('#recherche-globale-mobile').type('{downArrow}{enter}');

        cy.location('pathname').should('eq', '/app/intranet/agenda');
        cy.get('#recherche-globale-mobile').should('not.exist');
    });

    it('referme les propositions puis le champ avec Échap, et rend le focus au bouton', () => {
        cy.get('button[aria-label="Rechercher"]').click();
        cy.get('#recherche-globale-mobile').type('agneda');
        cy.get('[role="listbox"]').should('be.visible');

        cy.get('#recherche-globale-mobile').type('{esc}');
        cy.get('[role="listbox"]').should('not.exist');
        cy.get('#recherche-globale-mobile').should('be.visible');

        cy.get('#recherche-globale-mobile').type('{esc}');
        cy.get('#recherche-globale-mobile').should('not.exist');
        cy.focused().should('have.attr', 'aria-label', 'Rechercher');
    });

    it('se referme quand on touche ailleurs', () => {
        cy.get('button[aria-label="Rechercher"]').click();
        cy.get('#recherche-globale-mobile').should('be.visible');

        cy.get('body').click(187, 700);

        cy.get('#recherche-globale-mobile').should('not.exist');
    });
});
