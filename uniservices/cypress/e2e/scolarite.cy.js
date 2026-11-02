// Les justificatifs déposés par l'étudiant pendant un test : retirés avant et après, pour que
// chaque test parte de l'absence de R1.10 encore à justifier.
const retirerJustificatifsDeposes = () => {
    cy.exec(`cd back && php bin/console dbal:run-sql "UPDATE etudiant_absence a JOIN etudiant_absence_justificatif j ON j.id = a.absence_justificatif_id SET a.absence_justificatif_id = NULL WHERE j.fichier LIKE '/api/justificatifs/%'"`);
    cy.exec(`cd back && php bin/console dbal:run-sql "DELETE FROM etudiant_absence_justificatif WHERE fichier LIKE '/api/justificatifs/%'"`);
};

describe('Notes et absences de l\'étudiant', () => {
    beforeEach(() => {
        retirerJustificatifsDeposes();
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/scolarite');
        cy.contains('h1', 'Notes et absences', { timeout: 15000 });
    });

    after(retirerJustificatifsDeposes);

    it('s\'ouvre sur les notes : un résumé, puis chaque UE face au seuil de 10', () => {
        cy.location('search').should('match', /^\?semestre=\d+$/);
        cy.contains('[role="tab"]', 'Notes').should('have.attr', 'aria-selected', 'true');
        cy.contains('0 UE sur 1 au-dessus de 10.');
        cy.contains('Sous 10 : UE 1.1 Comprendre.');
        cy.contains('.ue', 'UE 1.1 Comprendre').should('contain', '8,97').and('contain', 'sous 10');
    });

    it('déplie une UE : moyennes, pénalités écrites en clair, et chaque statut d\'évaluation', () => {
        cy.contains('.ue', 'UE 1.1 Comprendre').find('button').first().click();
        cy.contains('.matiere', 'R1.10').should('contain', '−0,5 pt dès la première note (1 absence)');
        cy.contains('.matiere', 'R1.02').should('contain', 'levable si le justificatif est accepté');
        cy.contains('.evaluation', 'Expression écrite').should('contain', '0 / 20');
        cy.contains('.evaluation', 'Compréhension orale').should('contain', '14,5 / 20');
        cy.contains('.evaluation', 'TP formulaires').should('contain', 'Absence injustifiée, compte 0');
        cy.contains('.evaluation', 'Soutenance').should('contain', 'Pas encore publiée').and('not.contain', '17');
    });

    it('range les absences par ce qu\'elles demandent, dans leur propre onglet', () => {
        cy.contains('[role="tab"]', 'Absences').click();
        cy.location('search').should('contain', 'onglet=absences');
        cy.contains('retire 0,5 point à la moyenne de sa matière');
        cy.contains('.compteur', 'à justifier').should('contain', '1');
        cy.contains('section', 'À justifier').should('contain', 'R1.10 Intégration');
        cy.contains('section', 'En attente de décision').should('contain', 'R1.02');
        cy.contains('section', 'Justifiées').should('contain', 'R1.03');
    });

    it('justifie une absence depuis le panneau latéral', () => {
        cy.contains('[role="tab"]', 'Absences').click();
        cy.contains('section', 'À justifier').contains('button', 'Justifier').click();

        cy.get('.p-drawer').should('be.visible').within(() => {
            cy.contains('R1.10 Intégration');
            cy.get('#justificatif-debut').invoke('val').should('match', /^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/);
            cy.contains('button', 'Déposer le justificatif').click();
            cy.contains('Indiquez le motif de l\'absence.');
            cy.contains('Joignez la pièce justificative.');

            cy.contains('button', 'Rendez-vous médical').click().should('have.attr', 'aria-pressed', 'true');
            cy.contains('Indiquez le motif de l\'absence.').should('not.exist');
            cy.get('#justificatif-piece').selectFile({
                contents: Cypress.Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n'),
                fileName: 'certificat.pdf',
                mimeType: 'application/pdf',
            }, { force: true });
            cy.contains('.piece', 'certificat.pdf');
            cy.contains('button', 'Déposer le justificatif').click();
        });

        cy.get('.p-drawer').should('not.exist');
        cy.contains('section', 'En attente de décision').should('contain', 'R1.10 Intégration');
        cy.contains('section', 'À justifier').should('not.exist');
        cy.focused().should('have.id', 'absences-enAttente');
    });

    it('change de semestre en un clic, et le garde dans l\'adresse', () => {
        cy.location('search').invoke('match', /semestre=(\d+)/).its(1).then((courant) => {
            cy.get('.selecteur button[aria-pressed="false"]').first().click();
            cy.location('search').should('match', /semestre=\d+/).and('not.contain', `semestre=${courant}`);
            cy.contains('Aucune note publiée pour ce semestre.');
        });
    });
});
