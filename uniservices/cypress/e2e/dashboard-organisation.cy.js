describe('Organisation de l\'accueil de l\'étudiant', () => {
    const blocs = () => cy.get('.accueil-blocs > article', { timeout: 15000 });
    const titres = ($blocs) => [...$blocs].map((bloc) => bloc.querySelector('.font-semibold span').textContent.trim());
    const personnaliser = () => cy.contains('button', 'Personnaliser', { timeout: 15000 });

    beforeEach(() => {
        // Une base neuve n'a aucune préférence : c'est le cas qui en créait un jeu par requête.
        cy.exec('cd back && php bin/console dbal:run-sql "DELETE FROM dashboard_preference"');
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/');
    });

    it('ouvre sur sa journée, puis ses notes et les actualités, dans cet ordre', () => {
        cy.contains('h1', 'Accueil');
        cy.get('#accueil-bonjour').should('contain', 'Bonjour').closest('section').find('.vuecal').should('exist');
        blocs().should(($blocs) => {
            expect(titres($blocs)).to.deep.eq(['Notes', 'Actualités du département', 'Actualités de l\'IUT']);
        });
    });

    it('ne règle plus chaque bloc séparément', () => {
        blocs().should('have.length.greaterThan', 0);
        cy.get('button[aria-label="Configurer le widget"]').should('not.exist');
        cy.contains('Mon dashboard').should('not.exist');
    });

    it('affiche et masque un bloc au clavier, garde le choix au rechargement et rend le focus', () => {
        personnaliser().focus().type('{enter}');
        cy.get('.p-drawer').should('be.visible').within(() => {
            cy.contains('label', 'Contacts').invoke('attr', 'for').then((id) => {
                cy.get(`#${CSS.escape(id)}`).should('not.be.checked').focus().type(' ');
            });
        });
        blocs().last().should('contain', 'Contacts');
        cy.get('body').type('{esc}');
        cy.focused().should('contain', 'Personnaliser');

        cy.reload();
        blocs().should(($blocs) => expect(titres($blocs)).to.include('Contacts'));

        personnaliser().click();
        cy.get('.p-drawer').within(() => {
            cy.contains('label', 'Contacts').click();
        });
        blocs().should(($blocs) => expect(titres($blocs)).not.to.include('Contacts'));
    });
});
