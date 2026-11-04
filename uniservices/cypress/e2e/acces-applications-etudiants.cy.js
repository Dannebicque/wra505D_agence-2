// Attribution des applications aux étudiants (E26.11) : réservée au droit de scolarité, limitée
// aux étudiants du département actif, et l'intranet n'est jamais retiré.
const url = '/api/etudiants/bulk-applications';

const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const envoi = (body) => cy.request({ method: 'POST', url, body, failOnStatusCode: false });

describe('Accès des étudiants aux applications', () => {
    it('exige une connexion', () => {
        envoi({ etudiantIds: [1], applications: ['stages'] }).its('status').should('eq', 401);
    });

    it('est refusé à un étudiant', () => {
        connexion('etudiant');
        envoi({ etudiantIds: [1], applications: ['stages'] }).its('status').should('eq', 403);
    });

    it('refuse une action inconnue', () => {
        connexion('personnel');
        envoi({ etudiantIds: [1], action: 'drop', applications: [] }).its('status').should('eq', 400);
    });

    it('refuse un nom d\'application invalide', () => {
        connexion('personnel');
        envoi({ etudiantIds: [1], applications: ['<script>'] }).its('status').should('eq', 400);
    });

    it('ignore un étudiant hors du département actif', () => {
        connexion('personnel');
        envoi({ etudiantIds: [999999], applications: ['stages'] }).then((reponse) => {
            expect(reponse.status).to.eq(200);
            expect(reponse.body.count).to.eq(0);
        });
    });
});
