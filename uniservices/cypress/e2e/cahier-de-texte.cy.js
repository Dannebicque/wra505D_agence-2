describe('Cahier de texte de l\'étudiant', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/cahier-de-texte');
        cy.contains('h2', 'Échéances à venir', { timeout: 15000 });
    });

    it('liste les évaluations à venir, la plus proche d\'abord, sans celle qui est annulée', () => {
        cy.contains('li', 'Rendu du dossier')
            .should('contain', 'SAE1.01 Recommandation de communication numérique')
            .and('contain', 'Projet')
            .invoke('text').should('match', /Dans \d+ jours/);
        cy.contains('li', 'Mini-site personnel').should('contain', 'R1.11 Développement web');
        cy.contains('Oral reporté').should('not.exist');

        cy.get('section[aria-labelledby="echeances"] li').then(($lignes) => {
            const dates = [...$lignes].map((ligne) => ligne.querySelector('time').getAttribute('datetime'));
            expect(dates).to.deep.equal([...dates].sort());
        });
    });

    it('ouvre au clavier le plan de cours d\'une matière', () => {
        cy.contains('button', 'R1.11 Développement web').should('contain', 'John DOE').focus();
        cy.press(Cypress.Keyboard.Keys.SPACE);

        cy.contains('h3', 'Évaluation').parent().should('contain', 'Deux TP notés en séance.');
        cy.contains('h3', 'Travaux à rendre').parent().should('contain', 'Mini-site personnel de trois pages');
        cy.contains('h3', 'Programme').parent().find('li').should('have.length', 3).first().should('contain', 'HTML sémantique').and('contain', '3 h');
        cy.contains('h3', 'Séances réalisées').parent().should('contain', 'Balises de structure, titres et listes (1 h 30)');
    });

    it('ne montre pas un plan de cours encore en rédaction', () => {
        cy.get('section[aria-labelledby="plans-de-cours"]')
            .should('contain', 'R1.11 Développement web')
            .and('not.contain', 'R1.01 Anglais');
    });

    it('n\'a qu\'un titre de page et des titres continus', () => {
        cy.get('h1').should('have.length', 1).and('contain', 'Cahier de texte');
        cy.get('#contenu-principal h2').should('have.length', 2);
    });
});

describe('Cahier de texte, côté API', () => {
    it('est refusé au personnel et à un visiteur non connecté', () => {
        cy.request({ url: '/api/me/cahier-de-texte', failOnStatusCode: false }).its('status').should('eq', 401);

        cy.request('POST', '/api/login', { username: 'personnel', password: 'test' });
        cy.request({ url: '/api/me/cahier-de-texte', failOnStatusCode: false }).its('status').should('eq', 403);
    });
});
