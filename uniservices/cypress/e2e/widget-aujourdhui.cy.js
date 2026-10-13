describe('Journée de l\'étudiant sur l\'accueil', () => {
    const journee = () => cy.get('.journee', { timeout: 15000 });

    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    // Le bandeau et la liste lisent les mêmes cours : ils ne doivent jamais se contredire, quel
    // que soit le jour où le test tourne.
    it('annonce dans le bandeau un cours que la liste du jour montre aussi', () => {
        journee().find('.journee__etiquette', { timeout: 15000 }).invoke('text').then((etiquette) => {
            if (/Pas de cours aujourd'hui/.test(etiquette)) {
                journee().find('.journee__liste').should('not.exist');
                return;
            }
            journee().find('.journee__liste li').should('have.length.greaterThan', 0);
            if (/En cours|Prochain cours/.test(etiquette)) {
                journee().find('.journee__titre').invoke('text').then((titre) => {
                    journee().find('.journee__liste').should('contain', titre.trim().split(' ')[0]);
                });
            }
        });
    });

    it('marque d\'un mot, pas seulement d\'une couleur, le cours en cours', () => {
        journee().find('.journee__etiquette', { timeout: 15000 }).invoke('text').then((etiquette) => {
            if (/En cours/.test(etiquette)) {
                journee().find('.journee__cours--en-cours').should('contain', 'en cours');
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
