// Routes ajoutées par la reprise E27 (socle) : chacune exige une connexion, et celles du
// personnel refusent l'étudiant.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (method, url, body) => cy.request({ method, url, body, failOnStatusCode: false });

describe('Accès aux routes du socle E27', () => {
    it('exigent une connexion', () => {
        appel('GET', '/api/notifications').its('status').should('eq', 401);
        appel('GET', '/api/ldap/personnel/123').its('status').should('eq', 401);
        appel('GET', '/api/annees-universitaires/1/calendrier').its('status').should('eq', 401);
        appel('POST', '/api/intranet/previsionnel/semestres/1/import', { lignes: [] }).its('status').should('eq', 401);
    });

    it('ne livrent que le logo sur la page de connexion', () => {
        appel('GET', '/api/public/login-branding').then((reponse) => {
            expect(reponse.status).to.eq(200);
            expect(Object.keys(reponse.body)).to.deep.eq(['logo_name']);
        });
    });

    it('refusent les outils du personnel à un étudiant', () => {
        connexion('etudiant');
        appel('GET', '/api/ldap/personnel/123').its('status').should('eq', 403);
        appel('POST', '/api/intranet/previsionnel/semestres/1/import', { lignes: [] }).its('status').should('eq', 403);
        appel('POST', '/api/intranet/previsionnel/semestres/1/copie', { source: 2 }).its('status').should('eq', 403);
    });

    it('laissent l\'étudiant lire ses propres notifications', () => {
        connexion('etudiant');
        appel('GET', '/api/notifications/compteur').its('status').should('eq', 200);
    });
});
