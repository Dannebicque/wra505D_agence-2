describe('Journée de l\'étudiant sur l\'accueil', () => {
    const journee = () => cy.get('#accueil-bonjour', { timeout: 15000 }).closest('section');

    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    // « Maintenant » et la grille lisent les mêmes cours : ils ne doivent jamais se contredire, quel
    // que soit le jour où le test tourne.
    it('annonce dans « Maintenant » des cours que la grille du jour montre aussi', () => {
        journee().find('.vuecal, .edt-vide', { timeout: 15000 }).should('exist');
        journee().then(($journee) => {
            if ($journee.find('.edt-vide').length) {
                cy.wrap($journee).find('h3').should('not.exist');
                return;
            }

            // Un cours court n'a sur sa carte que son code : on compare les codes.
            $journee.find('h3').each((_, titre) => {
                const code = titre.nextElementSibling?.textContent.trim().split(' - ')[0];
                if (code) {
                    cy.wrap($journee).find('.vuecal').should('contain', code);
                }
            });
        });
    });

    it('ne propose pas à l\'étudiant de faire l\'appel', () => {
        journee().find('button .pi-user').should('not.exist');
    });
});
