describe('Widget « Aujourd\'hui » de l\'étudiant', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    // Les deux widgets lisent le même emploi du temps : ils ne doivent jamais se contredire, quel
    // que soit le jour où le test tourne.
    it('annonce les mêmes cours que le widget « Maintenant »', () => {
        cy.get('#maintenant-titre', { timeout: 15000 }).closest('section').as('maintenant')
            .should(($section) => expect($section.text()).to.match(/Aucun cours aujourd'hui|En cours|Prochain cours|Plus de cours aujourd'hui/));
        cy.contains('button', 'Voir l\'emploi du temps', { timeout: 15000 }).parent().as('aujourdhui');

        cy.get('@maintenant').then(($maintenant) => {
            if (/Aucun cours aujourd'hui/.test($maintenant.text())) {
                cy.get('@aujourdhui').should('contain', 'Aucun cours aujourd\'hui');
                return;
            }

            cy.get('@aujourdhui').should('not.contain', 'Aucun cours aujourd\'hui');
            // Un cours court n'a sur sa carte que son code : on compare les codes.
            $maintenant.find('h3').each((_, titre) => {
                const code = titre.nextElementSibling?.textContent.trim().split(' - ')[0];
                if (code) {
                    cy.get('@aujourdhui').should('contain', code);
                }
            });
        });
    });

    it('ne propose pas à l\'étudiant de faire l\'appel', () => {
        cy.contains('button', 'Voir l\'emploi du temps', { timeout: 15000 }).parent()
            .find('button .pi-user').should('not.exist');
    });
});
