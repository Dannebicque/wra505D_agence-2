// Modules repris en E27.3 : le parc automobile est réservé au personnel habilité, l'étudiant
// retrouve ses invitations aux questionnaires sans voir celles des autres.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (method, url, body) => cy.request({ method, url, body, failOnStatusCode: false });

describe('Accès aux modules repris en E27.3', () => {
    it('exigent une connexion', () => {
        appel('GET', '/api/parcauto').its('status').should('eq', 401);
        appel('GET', '/api/parcauto/accueil').its('status').should('eq', 401);
        appel('GET', '/api/questionnaire/mes-invitations').its('status').should('eq', 401);
    });

    it('refusent le parc automobile à un étudiant', () => {
        connexion('etudiant');
        appel('GET', '/api/parcauto').its('status').should('eq', 403);
        appel('POST', '/api/parcauto/reservations', {}).its('status').should('eq', 403);
    });

    it('donnent à l\'étudiant ses invitations', () => {
        connexion('etudiant');
        appel('GET', '/api/questionnaire/mes-invitations').its('status').should('eq', 200);
    });

    it('ne répondent à aucun jeton d\'invitation inconnu', () => {
        appel('GET', `/api/invitations/${'0'.repeat(32)}`).its('status').should('be.oneOf', [404, 410]);
    });
});
