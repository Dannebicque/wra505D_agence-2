// Les listings, trombinoscopes et exports d'un semestre donnent les noms, numéros et adresses de
// tous ses étudiants. Le client les avait laissés ouverts sans connexion : ils sont réservés au
// personnel du département du semestre. Le CSV évite de dépendre de Gotenberg.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

let semestreId;

before(() => {
    connexion('personnel');
    cy.request('/api/structure_semestres?itemsPerPage=200')
        .its('body.member')
        .then((semestres) => {
            semestreId = semestres.find((s) => s.libelle === 'S1').id;
        });
    cy.clearCookies();
});

const exports = (id) => [
    { method: 'GET', url: `/api/intranet/export/listing/semestre/${id}?format=csv` },
    { method: 'GET', url: `/api/intranet/export/trombinoscope/semestre/${id}` },
    { method: 'POST', url: '/api/intranet/export/personnalise', body: { semestreId: id, format: 'csv' } },
];

const envoi = ({ method, url, body }) => cy.request({ method, url, body, failOnStatusCode: false });

describe('Exports des étudiants d\'un semestre', () => {
    it('exigent une connexion', () => {
        exports(semestreId).forEach((requete) => envoi(requete).its('status').should('eq', 401));
    });

    it('sont refusés à un étudiant', () => {
        connexion('etudiant');
        exports(semestreId).forEach((requete) => envoi(requete).its('status').should('eq', 403));
    });

    it('rendent le listing au personnel du département', () => {
        connexion('personnel');
        envoi(exports(semestreId)[0]).then(({ status, headers, body }) => {
            expect(status).to.eq(200);
            expect(headers['content-type']).to.contain('text/csv');
            expect(body).to.contain('Groupe;#');
        });
    });
});
