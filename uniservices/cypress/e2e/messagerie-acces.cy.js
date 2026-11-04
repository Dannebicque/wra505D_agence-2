// Messagerie interne (E26.13) : la rédaction est réservée au personnel et ses destinataires sont
// pris dans le département actif de l'expéditeur.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (method, url, body) => cy.request({ method, url, body, failOnStatusCode: false });

const message = (cible) => ({
    sujet: 'Test d\'accès',
    contenu: 'Contenu',
    destinataireType: 'ETUDIANTS',
    etudiantIds: cible,
});

describe('Messagerie interne', () => {
    it('la boîte de réception exige une connexion', () => {
        appel('GET', '/api/messages').its('status').should('eq', 401);
    });

    it('un étudiant ne peut pas rédiger de message', () => {
        connexion('etudiant');
        appel('POST', '/api/messages', message([1])).its('status').should('eq', 403);
    });

    it('un étudiant n\'a pas de messages envoyés ni de cibles', () => {
        connexion('etudiant');
        appel('GET', '/api/messages/sent').its('status').should('eq', 403);
        appel('GET', '/api/messages/targets').its('status').should('eq', 403);
    });

    it('un destinataire hors du département est ignoré', () => {
        connexion('personnel');
        appel('POST', '/api/messages', message([999999])).its('status').should('eq', 400);
    });

    it('un message d\'autrui reste inaccessible', () => {
        connexion('etudiant');
        appel('GET', '/api/messages/999999').its('status').should('be.oneOf', [403, 404]);
    });
});
