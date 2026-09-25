/**
 * Les PrimeIcons sont décoratives : elles accompagnent toujours un texte ou un nom accessible.
 * Un lecteur d'écran ne doit en rencontrer aucune, sur aucune page de l'étudiant.
 */
const pages = [
    { adresse: '/app/intranet/', pret: '.dashboard-grid > *' },
    { adresse: '/app/intranet/agenda', pret: '.vuecal' },
    { adresse: '/app/intranet/scolarite', pret: '#contenu-principal h1' },
    { adresse: '/app/documents', pret: '#contenu-principal .card' },
    { adresse: '/app/intranet/cahier-de-texte', pret: '#contenu-principal h1' },
    { adresse: '/app/intranet/notifications', pret: '#contenu-principal h1' },
    { adresse: '/app/intranet/profil', pret: '#contenu-principal h1' },
];

describe('Icônes décoratives de l\'étudiant', () => {
    it('sont toutes masquées aux lecteurs d\'écran', () => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        pages.forEach(({ adresse, pret }) => {
            cy.visit(adresse);
            cy.get(pret, { timeout: 15000 }).should('exist');
            cy.get('body').should(($body) => {
                const exposees = [...$body[0].querySelectorAll('.pi')]
                    .filter((icone) => !icone.closest('[aria-hidden="true"]'))
                    .map((icone) => icone.className);
                expect(exposees, adresse).to.be.empty;
            });
        });
    });
});
