describe('Journée de l\'étudiant sur l\'accueil', () => {
    const bienvenue = () => cy.get('.bienvenue', { timeout: 15000 });
    const cours = () => cy.get('.accueil__cours', { timeout: 15000 });

    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    // La carte de bienvenue et la colonne des cours lisent la même journée : elles ne doivent jamais
    // se contredire, quel que soit le jour où le test tourne.
    it('dit dans la carte de bienvenue ce que montre la colonne des cours', () => {
        bienvenue().find('dt', { timeout: 15000 }).first().invoke('text').then((etat) => {
            if (/En ce moment/.test(etat)) {
                cours().find('.cours-carte--en-cours').should('have.length', 1);
            } else if (/Pas de cours/.test(etat)) {
                cours().find('.jour').should('not.exist');
            } else {
                cours().find('.cours-carte--en-cours').should('not.exist');
            }
        });
    });

    it('garde chaque cours lisible : aucune carte sous 40 px de haut', () => {
        cours().find('.jour__creneau').then(($creneaux) => {
            if (!$creneaux.length) {
                return;
            }
            const hauteurs = [...$creneaux].map((creneau) => creneau.getBoundingClientRect().height);
            expect(Math.min(...hauteurs)).to.be.at.least(40);
        });
    });

    it('mène à la semaine par un lien de 44 px', () => {
        cours().contains('a', 'Voir la semaine').should('have.attr', 'href').and('match', /\/intranet\/agenda$/);
        cours().contains('a', 'Voir la semaine').then(($lien) => {
            expect($lien[0].getBoundingClientRect().height).to.be.at.least(44);
        });
    });
});
