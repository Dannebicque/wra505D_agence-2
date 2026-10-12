// L'import d'étudiants était ouvert sans connexion : n'importe qui pouvait créer des comptes. Il est
// réservé au personnel qui peut modifier une scolarité. Aucun appel ne crée d'étudiant : un corps
// sans fichier s'arrête avant toute écriture.
const routes = [
    { url: '/api/etudiants/new', corps: { anneeUniversitaireId: 0 }, autorise: 400 },
    { url: '/api/etudiants/import_apogee', corps: {}, autorise: 200 },
];

const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const envoi = (url, body) => cy.request({ method: 'POST', url, body, failOnStatusCode: false });

describe('Import d\'étudiants', () => {
    routes.forEach(({ url, corps, autorise }) => {
        it(`${url} exige une connexion`, () => {
            envoi(url, corps).its('status').should('eq', 401);
        });

        it(`${url} est refusée à un étudiant`, () => {
            connexion('etudiant');
            envoi(url, corps).its('status').should('eq', 403);
        });

        it(`${url} reste ouverte au personnel`, () => {
            connexion('personnel');
            envoi(url, corps).its('status').should('eq', autorise);
        });
    });
});
