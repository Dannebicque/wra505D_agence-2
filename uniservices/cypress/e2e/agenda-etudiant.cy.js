/**
 * Lundi de la semaine de rentrée, avec la même règle que CelcatFausseBaseCommand : l'année bascule
 * à la mi-août. La CI génère la fausse base Celcat le jour même, ses cours partent de ce lundi.
 */
const lundiDeLaRentree = (aujourdhui) => {
    const mois = aujourdhui.getMonth() + 1;
    const annee = (mois === 8 && aujourdhui.getDate() >= 15) || mois > 8
        ? aujourdhui.getFullYear()
        : aujourdhui.getFullYear() - 1;
    const premierSeptembre = new Date(annee, 8, 1);
    const decalage = (premierSeptembre.getDay() + 6) % 7;

    return new Date(annee, 8, 1 - decalage);
};

describe('Agenda de l\'étudiant', () => {
    it('se connecte avec le compte invité et affiche les cours de ses groupes', () => {
        cy.connexionInvite('etudiant');

        // Mercredi de la troisième semaine de cours, loin des vacances : l'agenda s'ouvre sur une
        // semaine qui a toujours des cours, quel que soit le jour où la CI tourne. L'horloge n'est
        // figée qu'ici : le formulaire de connexion a besoin que le temps s'écoule.
        const rentree = lundiDeLaRentree(new Date());
        cy.clock(new Date(rentree.getFullYear(), rentree.getMonth(), rentree.getDate() + 16, 9, 0), ['Date']);
        cy.visit('/app/intranet/agenda');

        cy.get('.vuecal__event', { timeout: 15000 }).should('have.length.greaterThan', 0);
        cy.contains('Une erreur est survenue lors du chargement de l\'emploi du temps').should('not.exist');
    });
});
