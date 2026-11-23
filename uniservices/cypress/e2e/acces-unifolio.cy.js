// Unifolio v2 (E27.2) : l'espace de l'étudiant exige le paquet Unifolio, l'administration est
// réservée au personnel habilité, et seules les publications existantes sont servies sans connexion.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (method, url) => cy.request({ method, url, failOnStatusCode: false });


describe('Accès à Unifolio', () => {
    it('exige une connexion', () => {
        ['/api/unifolio/contexte', '/api/unifolio/portfolios', '/api/unifolio/cv', '/api/unifolio/traces', '/api/unifolio/admin/campagnes']
            .forEach((url) => appel('GET', url).its('status').should('eq', 401));
    });

    it('refuse l\'espace portfolio à un étudiant sans le paquet Unifolio', () => {
        connexion('etudiant');
        appel('GET', '/api/unifolio/portfolios').its('status').should('eq', 403);
        appel('GET', '/api/unifolio/cv').its('status').should('eq', 403);
    });

    it('refuse l\'administration à un étudiant', () => {
        connexion('etudiant');
        appel('GET', '/api/unifolio/admin/campagnes').its('status').should('eq', 403);
    });

    it('ne sert sans connexion aucune publication inexistante', () => {
        appel('GET', '/api/unifolio/public/inconnu/index.html').its('status').should('eq', 404);
        appel('GET', `/api/unifolio/partage/${'0'.repeat(48)}/index.html`).its('status').should('eq', 404);
        appel('GET', '/api/unifolio/public/inconnu/..%2F..%2Fetc%2Fpasswd').its('status').should('eq', 404);
    });
});
