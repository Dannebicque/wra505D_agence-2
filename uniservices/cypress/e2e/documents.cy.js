describe('Documents par matière et par SAÉ', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/documents');
        cy.contains('h2', 'Matières', { timeout: 15000 });
    });

    it('suit une hiérarchie de titres sans saut, sous un seul h1', () => {
        cy.contains('h1', 'Documents');
        cy.get('#contenu-principal').find('h1, h2, h3, h4, h5, h6').then(($titres) => {
            const niveaux = [...$titres].map((titre) => Number(titre.tagName[1]));
            expect(niveaux.filter((niveau) => niveau === 1)).to.have.length(1);
            niveaux.slice(1).forEach((niveau, index) => {
                expect(niveau, `titre ${index + 2} après un h${niveaux[index]}`).to.be.at.most(niveaux[index] + 1);
            });
        });
    });

    it('range les supports de cours par matière et par SAÉ', () => {
        cy.contains('h2', 'Matières').next().within(() => {
            cy.contains('button', 'R1.01 Anglais').should('contain', '2 documents');
            cy.contains('button', 'R1.02 Culture numérique').should('contain', '1 document');
            cy.contains('button', 'R1.03').should('not.exist');
        });
        cy.contains('h2', 'SAÉ').next().should('contain', 'SAE1.01');
    });

    it('n\'affiche que les documents de l\'enseignement choisi au clavier', () => {
        cy.contains('button', 'SAE1.01').focus().type('{enter}');
        cy.contains('button', 'SAE1.01').should('have.attr', 'aria-current', 'true');
        cy.contains('SAE1.01 Recommandation de communication numérique');
        cy.contains('SAÉ 1.01 : sujet et attendus');
        cy.contains('Anglais : vocabulaire du numérique').should('not.exist');

        cy.contains('button', 'Tous les documents').click();
        cy.contains('button', 'SAE1.01').should('not.have.attr', 'aria-current');
    });

    it('ouvre une catégorie par son lien, à une adresse qui résiste au rechargement', () => {
        const lienCategorie = () => cy.get('a[href*="categorie="]').first();

        lienCategorie().find('span.flex-1').invoke('text').then((texte) => {
            const categorie = texte.trim();

            lienCategorie().click();
            cy.location('search').should('match', /^\?categorie=\w+$/);
            lienCategorie().should('have.attr', 'aria-current', 'page');
            cy.contains('h2', categorie);

            cy.reload();
            cy.contains('h2', categorie, { timeout: 15000 });
            lienCategorie().should('have.attr', 'aria-current', 'page');

            cy.contains('button', 'Tous les documents').click();
            cy.location('search').should('eq', '');
            cy.contains('h2', 'Tous les documents');
        });
    });

    it('n\'affiche aucun emoji, ni dans les documents ni dans leur détail', () => {
        // Le pied de page commun écrit « Copyright © » : ©, ® et ™ comptent comme pictogrammes.
        const emoji = /(?![©®™])\p{Extended_Pictographic}/u;
        cy.get('body').invoke('text').should('not.match', emoji);
        cy.contains('button', 'SAE1.01').click();
        cy.contains('SAÉ 1.01 : sujet et attendus').click();
        cy.contains('Informations');
        cy.get('body').invoke('text').should('not.match', emoji);
    });

    it('garde les favoris de l\'étudiant d\'un chargement à l\'autre', () => {
        const favoris = () => cy.get('nav').contains('button', 'Favoris');
        const document = 'SAÉ 1.01 : sujet et attendus';

        cy.exec('cd back && php bin/console dbal:run-sql "DELETE FROM document_favori"');
        cy.reload();
        cy.contains('h2', 'Matières', { timeout: 15000 });
        favoris().should('contain', '0');

        cy.contains('button', 'SAE1.01').click();
        cy.contains('.card', document).find('button[aria-label="Ajouter aux favoris"]').click();
        cy.contains('.card', document).find('button[aria-label="Retirer des favoris"]');
        favoris().should('contain', '1');

        cy.reload();
        cy.contains('h2', 'Matières', { timeout: 15000 });
        favoris().should('contain', '1').click();
        cy.contains('.card', document).find('button[aria-label="Retirer des favoris"]').click();
        favoris().should('contain', '0');
        cy.contains('.card', document).should('not.exist');
    });
});
