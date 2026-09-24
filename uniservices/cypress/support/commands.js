Cypress.Commands.add('connexionInvite', (login) => {
    cy.visit('/app/auth/login');
    cy.get('input[name="username"]').type(login);
    // Le bouton ne se réactive qu'à la sortie du champ, comme lorsqu'on clique avec la souris.
    cy.get('input[name="password"]').type('test', { log: false }).blur();
    cy.contains('button', 'Connexion invité').click();
    cy.location('pathname').should('eq', '/app/auth/portail');
});
