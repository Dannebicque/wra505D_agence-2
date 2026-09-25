describe('Notifications de l\'étudiant', () => {
    const cloche = '[aria-controls="panneau-notifications"]';
    const nonLues = ($bouton) => Number(($bouton.attr('aria-label').match(/(\d+) notifications? non lues?/) ?? [0, 0])[1]);

    beforeEach(() => {
        // Chaque test part d'un fil où rien n'est lu.
        cy.exec('cd back && php bin/console dbal:run-sql "DELETE FROM notification_lue"');
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
        cy.get(cloche, { timeout: 15000 }).should('have.attr', 'aria-label').and('match', /\d+ notifications non lues/);
    });

    it('annonce le nombre de non lues et ouvre la dernière à sa page', () => {
        cy.get(cloche).then(($bouton) => {
            const avant = nonLues($bouton);

            cy.get(cloche).click();
            cy.get('#panneau-notifications').should('have.attr', 'role', 'dialog')
                .find('li').should('have.length', 5);
            cy.get('#panneau-notifications li button').first().click();

            cy.location('pathname').should('not.eq', '/app/intranet/');
            cy.get(cloche).should(($apres) => expect(nonLues($apres)).to.eq(avant - 1));
        });
    });

    it('se ferme avec Échap et rend le focus à la cloche', () => {
        cy.get(cloche).focus().type('{enter}');
        cy.get('#panneau-notifications').should('be.visible');
        cy.get('#panneau-notifications').type('{esc}');
        cy.get('#panneau-notifications').should('not.exist');
        cy.focused().should('match', cloche);
    });

    it('lit un message en entier et le marque comme lu', () => {
        cy.visit('/app/intranet/notifications');
        cy.contains('fieldset', 'Type').contains('Messages').click();

        cy.contains('article', 'Changement de salle pour R1.11').as('message')
            .should('contain', 'Le TP de jeudi a lieu en salle B204')
            .and('contain', 'Non lue');
        cy.get('@message').contains('button', 'Marquer comme lu').click();
        cy.get('@message').should('not.contain', 'Non lue');
    });

    it('filtre les non lues et marque tout comme lu', () => {
        cy.visit('/app/intranet/notifications');
        cy.contains('fieldset', 'Afficher').contains('Non lues').click();
        cy.contains('h2', 'Aujourd\'hui');

        cy.contains('button', 'Tout marquer comme lu').click();

        cy.contains('Aucune notification non lue');
        cy.contains('Aucune notification ne correspond à ces filtres.');
        cy.get(cloche).should('have.attr', 'aria-label', 'Notifications, Aucune notification non lue');
    });
});
