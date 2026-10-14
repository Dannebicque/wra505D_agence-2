describe('Notes et absences de l\'étudiant', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/scolarite');
        cy.contains('h1', 'Notes et absences', { timeout: 15000 });
    });

    it('s\'ouvre sur la synthèse du semestre en cours, en graphiques', () => {
        cy.location('search').should('match', /^\?semestre=\d+$/);
        cy.get('[aria-label="Affichage des notes"]').contains('[aria-pressed="true"]', 'Synthèse');
        cy.contains('.chiffre', 'UE à 10 ou plus').should('contain', '0 / 1');
        cy.get('.graphique canvas').should('have.length', 3);
        cy.contains('.graphique table', 'UE 1.1 Comprendre').should('contain', '8,97');
    });

    it('détaille les UE : moyennes, pénalités d\'absence, et chaque statut d\'évaluation', () => {
        cy.get('[aria-label="Affichage des notes"]').contains('Par UE').click();
        cy.location('search').should('contain', 'affichage=ue');
        cy.contains('.evaluation', 'Expression écrite').should('contain', '0 / 20');
        cy.contains('.evaluation', 'Compréhension orale').should('contain', '14,5 / 20');
        cy.contains('.evaluation', 'TP formulaires').should('contain', 'Absence injustifiée, compte 0');
        cy.contains('.evaluation', 'Soutenance').should('contain', 'Pas encore publiée').and('not.contain', '17');
        cy.contains('li', 'R1.10 Intégration').should('contain', '−0,5 pt (1 absence)');
        cy.contains('li', 'R1.02 Culture numérique').should('contain', 'levable si le justificatif est accepté');

        cy.reload();
        cy.get('[aria-label="Affichage des notes"]', { timeout: 15000 }).contains('[aria-pressed="true"]', 'Par UE');
    });

    it('résume les absences et dit ce qu\'elles coûtent', () => {
        cy.contains('section', 'Absences').within(() => {
            cy.contains('retire 0,5 point à la moyenne de sa matière');
            cy.contains('.compteur', 'non justifiée').should('contain', '1');
            cy.contains('.compteur', 'en attente').should('contain', '1');
            cy.contains('li', 'R1.10 Intégration').should('contain', 'Non justifiée');
            cy.contains('li', 'R1.03').should('contain', 'Justifiée');
        });
    });

    it('change de semestre, et le garde dans l\'adresse', () => {
        cy.location('search').invoke('match', /semestre=(\d+)/).its(1).then((courant) => {
            cy.get('.scolarite__semestre .p-select').click();
            cy.get('.p-select-option').not('[aria-selected="true"]').first().click();
            cy.location('search').should('match', /semestre=\d+/).and('not.contain', `semestre=${courant}`);
            cy.contains('Aucune note publiée pour ce semestre');
        });
    });
});
