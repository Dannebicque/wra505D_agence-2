describe('Scolarité de l\'étudiant', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
        cy.visit('/app/intranet/scolarite');
        cy.contains('h2', 'Semestre S1', { timeout: 15000 });
    });

    it('affiche la moyenne provisoire de l\'UE et ses matières', () => {
        cy.contains('h3', 'UE 1.1 Comprendre').parent().should('contain', '8,97');
        cy.contains('Les moyennes sont provisoires');
    });

    it('distingue un zéro, une absence injustifiée et une note non publiée', () => {
        cy.contains('button', 'R1.01 Anglais').focus().type('{enter}');
        cy.contains('tr', 'Expression écrite').should('contain', '0 / 20');
        cy.contains('tr', 'Compréhension orale').should('contain', '14,5 / 20');

        cy.contains('button', 'R1.11 Développement web').click();
        cy.contains('tr', 'TP formulaires').should('contain', 'Absence injustifiée, compte 0');

        cy.contains('button', 'SAE1.01').click();
        cy.contains('tr', 'Soutenance').should('contain', 'Pas encore publiée').and('not.contain', '17');
    });

    it('résume les absences et leur justification', () => {
        cy.contains('3 absences : 1 justifiée, 1 non justifiée, 1 en attente de justificatif.');
        cy.contains('li', 'R1.10 Intégration').should('contain', 'Non justifiée');
        cy.contains('li', 'R1.03').should('contain', 'Justifiée');
    });
});
