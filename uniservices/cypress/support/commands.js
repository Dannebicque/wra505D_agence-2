Cypress.Commands.add('connexionInvite', (login) => {
    cy.visit('/app/auth/login');
    cy.get('input[name="username"]').type(login);
    cy.get('input[name="password"]').type('test', { log: false });
    cy.contains('button', 'Connexion invité').click();
    // Le personnel arrive sur le portail, l'étudiant directement sur son accueil.
    cy.location('pathname').should('match', /^\/app\/(auth\/portail|intranet\/?)$/);
});

// Le front envoie à chaque requête l'année universitaire sélectionnée (shared/helpers/contexteAnnuel.js) ;
// sans elle, une opération annuelle répond 400 avant tout contrôle d'accès. Les tests font de même,
// avec l'année active, sauf s'ils posent eux-mêmes ces en-têtes.
Cypress.Commands.overwrite('request', (originalFn, ...args) => {
    const options = typeof args[0] === 'object'
        ? { ...args[0] }
        : args.length === 1
            ? { url: args[0] }
            : { method: args[0], url: args[1], body: args[2] };
    options.headers = {
        'X-Contexte-Annuel': 'selection',
        'X-Annee-Universitaire': 'courante',
        ...options.headers,
    };

    return originalFn(options);
});
