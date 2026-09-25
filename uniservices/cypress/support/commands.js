Cypress.Commands.add('connexionInvite', (login) => {
    cy.visit('/app/auth/login');
    cy.get('input[name="username"]').type(login);
    // Le bouton ne se réactive qu'à la sortie du champ, comme lorsqu'on clique avec la souris.
    cy.get('input[name="password"]').type('test', { log: false }).blur();
    cy.contains('button', 'Connexion invité').click();
    // Le personnel arrive sur le portail, l'étudiant directement sur son accueil.
    cy.location('pathname').should('match', /^\/app\/(auth\/portail|intranet\/?)$/);
});
