/**
 * Chaque page de l'étudiant tient dans la largeur d'un téléphone : aucun bloc ne sort de
 * l'écran, rien n'oblige à défiler de côté.
 */
const pages = [
    { adresse: '/app/intranet/', pret: '.dashboard-grid > *' },
    { adresse: '/app/intranet/agenda', pret: '.vuecal' },
    { adresse: '/app/intranet/scolarite', pret: '#contenu-principal h1' },
    { adresse: '/app/documents', pret: '#contenu-principal .card' },
    { adresse: '/app/intranet/cahier-de-texte', pret: '#contenu-principal h1' },
    { adresse: '/app/intranet/notifications', pret: '#contenu-principal h1' },
    { adresse: '/app/intranet/profil', pret: '#contenu-principal h1, #contenu-principal h2' },
];

describe('Pages de l\'étudiant sur téléphone', () => {
    beforeEach(() => {
        cy.viewport(375, 812);
        cy.connexionInvite('etudiant');
    });

    it('tiennent toutes dans la largeur de l\'écran', () => {
        pages.forEach(({ adresse, pret }) => {
            cy.visit(adresse);
            cy.get(pret, { timeout: 15000 }).should('exist');
            cy.get('.layout-main-container').should(($conteneur) => {
                expect($conteneur[0].scrollWidth, adresse).to.be.at.most($conteneur[0].clientWidth);
            });
        });
    });

    it('écrit au support depuis le pied de page', () => {
        cy.visit('/app/intranet/');
        cy.contains('a', 'intranet.iut-troyes@univ-reims.fr', { timeout: 15000 })
            .should('have.attr', 'href', 'mailto:intranet.iut-troyes@univ-reims.fr');
    });
});
