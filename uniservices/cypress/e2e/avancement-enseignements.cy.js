// Suivi d'avancement des enseignements, repris du client : réservé au personnel.
const routes = [
    ['GET', '/api/avancement_enseignements?enseignement=1&groupe=1'],
    ['GET', '/api/historique/avancement_enseignements?enseignement=1&groupe=1'],
    ['POST', '/api/avancement_enseignements?enseignement=1&groupe=1'],
];

const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (method, url) => cy.request({
    method,
    url,
    body: method === 'POST' ? { repere: 'Chapitre 1', prochaineEtape: '', versionId: 0 } : undefined,
    failOnStatusCode: false,
});

describe('Avancement des enseignements', () => {
    routes.forEach(([method, url]) => {
        it(`${method} ${url} exige une connexion`, () => {
            appel(method, url).its('status').should('eq', 401);
        });

        it(`${method} ${url} est refusée à un étudiant`, () => {
            connexion('etudiant');
            appel(method, url).its('status').should('eq', 403);
        });
    });
});
