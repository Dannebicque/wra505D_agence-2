describe('Organisation du tableau de bord de l\'étudiant', () => {
    const widgets = () => cy.get('.dashboard-grid > article', { timeout: 15000 });
    const titres = ($widgets) => [...$widgets].map((widget) => widget.querySelector('.font-semibold span').textContent.trim());

    beforeEach(() => {
        // Une base neuve n'a aucune préférence : c'est le cas qui en créait un jeu par requête.
        cy.exec('cd back && php bin/console dbal:run-sql "DELETE FROM dashboard_preference"');
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    it('déplace un widget sans le dupliquer, et garde l\'ordre au rechargement', () => {
        widgets().should('have.length.greaterThan', 1).then(($avant) => {
            const avant = titres($avant);

            cy.wrap($avant[0]).find('button[aria-label="Configurer le widget"]').click();
            cy.wrap($avant[0]).find('button[aria-label="Déplacer sur la droite"]').click();

            widgets().should(($apres) => {
                expect(titres($apres)).to.deep.eq([avant[1], avant[0], ...avant.slice(2)]);
            });

            cy.reload();
            widgets().should(($apres) => {
                expect(titres($apres)).to.deep.eq([avant[1], avant[0], ...avant.slice(2)]);
            });
        });
    });

    it('descend un widget d\'un cran à chaque clic, jusqu\'au dernier', () => {
        widgets().should('have.length.greaterThan', 2).then(($avant) => {
            const avant = titres($avant);
            const dernier = avant.length - 1;

            widgets().first().find('button[aria-label="Configurer le widget"]').click();
            for (let rang = 0; rang < dernier; rang++) {
                // Le panneau de réglage suit le widget déplacé : il reste ouvert d'un clic au suivant.
                widgets().eq(rang).find('button[aria-label="Déplacer sur la droite"]').click();
                widgets().eq(rang + 1).should('contain', avant[0]);
            }

            cy.reload();
            widgets().should(($apres) => {
                expect(titres($apres)).to.deep.eq([...avant.slice(1), avant[0]]);
            });
        });
    });

    it('ne propose plus de changer la taille, et met la poignée de glisser dans le panneau de réglage', () => {
        widgets().each(($widget) => cy.wrap($widget).find('.poignee-widget').should('not.exist'));

        widgets().first().find('button[aria-label="Configurer le widget"]').click();
        widgets().first().find('.poignee-widget').should('have.attr', 'aria-hidden', 'true').and(($poignee) => {
            expect($poignee[0].getBoundingClientRect().width).to.be.at.least(44);
        });
        ['Retirer le widget', 'Fermer le panneau de configuration'].forEach((nom) => {
            widgets().first().find(`button[aria-label="${nom}"]`).should('exist');
        });
        ['Augmenter largeur', 'Diminuer largeur', 'Augmenter hauteur', 'Diminuer hauteur'].forEach((nom) => {
            widgets().first().find(`button[aria-label="${nom}"]`).should('not.exist');
        });

        widgets().first().find('button[aria-label="Fermer le panneau de configuration"]').click();
        widgets().first().find('.poignee-widget').should('not.exist');
    });
});
