const connexionParFormulaire = (login) => {
    cy.visit('/app/auth/login');
    cy.get('input[name="username"]').type(login);
    cy.get('input[name="password"]').type('test', { log: false });
    cy.contains('button', 'Connexion invité').click();
    // Le personnel arrive sur le portail, l'étudiant directement sur son accueil.
    cy.location('pathname').should('match', /^\/app\/(auth\/portail|intranet\/?)$/);
};

// Le cookie BEARER dure 900 s. Une session qui approche de sa fin est refaite, plutôt que de
// laisser le jeton expirer au milieu d'un test.
const MARGE_AVANT_EXPIRATION = 60;

const sessionEncoreValide = () => {
    cy.getCookie('BEARER').then((cookie) => {
        expect(cookie?.expiry ?? 0).to.be.greaterThan(Date.now() / 1000 + MARGE_AVANT_EXPIRATION);
    });
    cy.request('/api/me/security-context');
};

// Pour les tests qui portent sur la connexion elle-même ou sur la page d'arrivée.
Cypress.Commands.add('connexionInviteParFormulaire', connexionParFormulaire);

// Le formulaire n'est rempli qu'une fois par compte : les tests suivants reprennent les cookies de
// cette connexion. La page est vide au retour, c'est au test d'ouvrir celle qu'il vérifie.
Cypress.Commands.add('connexionInvite', (login) => {
    cy.session(['invite', login], () => connexionParFormulaire(login), {
        validate: sessionEncoreValide,
        cacheAcrossSpecs: true,
    });
});
