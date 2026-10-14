describe('Journée de l\'étudiant sur l\'accueil', () => {
    const journee = () => cy.get('.journee', { timeout: 15000 });

    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    // Le bandeau dit l'état, la liste porte les cours : le cours que le bandeau situe est celui que
    // la liste signale, quel que soit le jour où le test tourne.
    it('signale dans la liste le cours dont parle le bandeau', () => {
        journee().find('.journee__etat', { timeout: 15000 }).invoke('text').then((etat) => {
            if (/^\s*En cours/.test(etat)) {
                journee().find('.journee__cours--en-cours').should('contain', 'en cours');
            } else if (/^\s*Prochain cours/.test(etat)) {
                journee().find('.journee__maintenant').should('contain', 'dans');
            } else {
                journee().find('.journee__maintenant').should('not.exist');
            }
        });
    });

    it('ne répète pas dans le bandeau l\'intitulé du cours en cours', () => {
        journee().find('.journee__etat', { timeout: 15000 }).invoke('text').then((etat) => {
            if (/^\s*(En cours|Prochain cours)/.test(etat)) {
                journee().find('.journee__liste .journee__libelle').each(($libelle) => {
                    expect(etat).not.to.contain($libelle.text().trim());
                });
            }
        });
    });

    it('mène à la semaine par un lien de 44 px', () => {
        journee().contains('a', 'Voir la semaine').should('have.attr', 'href').and('match', /\/intranet\/agenda$/);
        journee().contains('a', 'Voir la semaine').then(($lien) => {
            expect($lien[0].getBoundingClientRect().height).to.be.at.least(44);
        });
    });
});
