// Exports, relances et invitations d'un questionnaire : le client les avait laissés ouverts sans
// connexion (envoi d'e-mails, jetons de réponse). Ils sont réservés au personnel ; les droits sur le
// département du questionnaire sont couverts par QuestionnaireFollowUpVoterTest. Les fixtures n'ont
// aucun questionnaire : le type d'utilisateur est vérifié avant la recherche du questionnaire.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const uuid = '00000000-0000-4000-8000-000000000000';

const routes = [
    { method: 'GET', url: `/api/questionnaires/${uuid}/export-excel` },
    { method: 'POST', url: `/api/questionnaires/${uuid}/export-pdf`, body: { charts: {} } },
    { method: 'POST', url: `/api/questionnaires/${uuid}/reminders`, body: { tokens: ['x'] } },
    { method: 'GET', url: `/api/questionnaire_invitations?questionnaire=/api/questionnaires/${uuid}` },
];

const envoi = ({ method, url, body }) => cy.request({ method, url, body, failOnStatusCode: false });

describe('Suivi des questionnaires', () => {
    it('exige une connexion', () => {
        routes.forEach((requete) => envoi(requete).its('status').should('eq', 401));
    });

    it('est refusé à un étudiant', () => {
        connexion('etudiant');
        routes.forEach((requete) => envoi(requete).its('status').should('eq', 403));
    });

    it('laisse passer le personnel jusqu\'au questionnaire demandé', () => {
        connexion('personnel');
        routes.slice(0, 3).forEach((requete) => envoi(requete).its('status').should('eq', 404));
        envoi(routes[3]).its('status').should('eq', 400);
    });

    it('ne liste pas les invitations sans questionnaire', () => {
        connexion('personnel');
        envoi({ method: 'GET', url: '/api/questionnaire_invitations' }).its('status').should('eq', 400);
    });
});
