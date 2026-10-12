// Les fiches étudiantes étaient lisibles sans connexion, et un étudiant pouvait écrire n'importe quel
// champ de la sienne, rôles compris. Seul le personnel lit les fiches ; l'étudiant ne lit et ne
// modifie que la sienne, et seulement ses coordonnées.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (method, url, body) => cy.request({
    method,
    url,
    body,
    headers: method === 'PATCH' ? { 'Content-Type': 'application/merge-patch+json' } : {},
    failOnStatusCode: false,
});

const fiche = (id) => `/api/etudiants/${id}`;
const scolarite = (id) => `/api/scolarite/etudiant/${id}`;

describe('Fiches étudiantes', () => {
    let moi;
    let autre;

    before(() => {
        connexion('etudiant');
        cy.request('/api/me/security-context').then(({ body }) => {
            moi = body.user.id;
        });
        connexion('personnel');
        cy.request('/api/etudiants').then(({ body }) => {
            autre = body.member.find((etudiant) => etudiant.id !== moi).id;
        });
        // L'isolation des tests ne vide pas les cookies posés par before() avant le premier test.
        cy.clearAllCookies();
    });

    after(() => {
        cy.exec(`cd back && php bin/console dbal:run-sql "UPDATE etudiant SET roles = '[\\"ROLE_ETUDIANT\\"]' WHERE username = 'etudiant'"`);
    });

    it('sont fermées sans connexion', () => {
        appel('GET', '/api/etudiants').its('status').should('eq', 401);
        appel('GET', fiche(autre)).its('status').should('eq', 401);
        appel('GET', scolarite(autre)).its('status').should('eq', 401);
        appel('PATCH', fiche(autre), {}).its('status').should('eq', 401);
    });

    it('sont fermées à un étudiant, sauf la sienne', () => {
        connexion('etudiant');
        appel('GET', '/api/etudiants').its('status').should('eq', 403);
        appel('GET', fiche(autre)).its('status').should('eq', 403);
        appel('GET', scolarite(autre)).its('status').should('eq', 403);
        appel('PATCH', fiche(autre), { tel1: '0600000000' }).its('status').should('eq', 403);
        appel('GET', fiche(moi)).its('status').should('eq', 200);
        appel('GET', scolarite(moi)).its('status').should('eq', 200);
    });

    it('un étudiant modifie ses coordonnées, pas ses rôles', () => {
        connexion('etudiant');
        appel('GET', fiche(moi)).then(({ body }) => {
            appel('PATCH', fiche(moi), { tel1: body.tel1, roles: ['ROLE_SUPER_ADMIN'] })
                .its('status').should('eq', 200);
        });
        appel('GET', fiche(moi)).its('body.roles').should('deep.equal', ['ROLE_ETUDIANT']);
    });

    it('restent ouvertes au personnel', () => {
        connexion('personnel');
        appel('GET', '/api/etudiants').its('status').should('eq', 200);
        appel('GET', fiche(autre)).its('status').should('eq', 200);
        appel('GET', scolarite(autre)).its('status').should('eq', 200);
    });
});
