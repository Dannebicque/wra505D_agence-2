// L'import d'étudiants était ouvert sans connexion : n'importe qui pouvait créer des comptes. Il est
// réservé au personnel qui peut modifier une scolarité, comme l'intégration d'un étudiant existant
// et le report des groupes du semestre précédent. Aucun appel n'écrit : un corps incomplet
// s'arrête avant toute écriture.
const routes = [
    { url: '/api/etudiants/new', corps: { anneeUniversitaireId: 0 }, autorise: 400 },
    { url: '/api/new/etudiants', corps: { anneeUniversitaireId: 0 }, autorise: 400 },
    { url: '/api/etudiants/import_apogee', corps: {}, autorise: 200 },
    { url: '/api/import_apogee/etudiants', corps: {}, autorise: 200 },
    { url: '/api/etudiants/0/integrer_scolarite', corps: {}, autorise: 400 },
    { url: '/api/manage-groupes/synchro-semestre-previous/etudiant_scolarite_semestres', corps: {}, autorise: 204 },
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
