// Routes du personnel qui répondaient 500 à tout appel (E18) : chacune rend désormais un code
// défendable, et un étudiant n'y a pas accès.
const synchronisations = [
    '/api/oreof/ref-competences/synchronisation',
    '/api/oreof/ref-formation/synchronisation',
];

const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (method, url, body) => cy.request({ method, url, body, failOnStatusCode: false });

describe('Routes réservées au personnel', () => {
    synchronisations.forEach((url) => {
        it(`${url} n'accepte que POST`, () => {
            connexion('superadmin');
            appel('GET', url).its('status').should('eq', 405);
        });

        it(`${url} exige une connexion`, () => {
            appel('POST', url, {}).its('status').should('eq', 401);
        });

        it(`${url} est refusée à un étudiant`, () => {
            connexion('etudiant');
            appel('POST', url, {}).its('status').should('eq', 403);
        });

        it(`${url} répond 400 sans identifiants`, () => {
            connexion('superadmin');
            appel('POST', url, {}).its('status').should('eq', 400);
        });
    });

    it('la synchronisation des compétences répond 404 pour un diplôme inconnu', () => {
        connexion('superadmin');
        appel('POST', synchronisations[0], { departementId: 999999, diplomeId: 999999 })
            .its('status').should('eq', 404);
    });

    it('les statistiques d\'emploi du temps sont refusées à un étudiant', () => {
        connexion('etudiant');
        appel('GET', '/api/stats/edt_events').its('status').should('eq', 403);
    });

    it('les statistiques d\'emploi du temps restent ouvertes au personnel', () => {
        connexion('personnel');
        appel('GET', '/api/stats/edt_events').its('status').should('eq', 200);
    });
});
