// Les demandes d'achat (montants, fournisseurs, PDF signés) étaient lisibles et modifiables sans
// connexion. Elles sont réservées au personnel habilité du département de la demande.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (method, url, body) => cy.request({
    method,
    url,
    body,
    headers: {
        Accept: 'application/json',
        'Content-Type': method === 'PATCH' ? 'application/merge-patch+json' : 'application/json',
    },
    failOnStatusCode: false,
});

const commande = (id) => `/api/finance_bon_commandes/${id}`;
const documents = (id) => [`${commande(id)}/pdf`, `${commande(id)}/html`, `${commande(id)}/download-signed-pdf`];

// Demande de démonstration du département GEA, où le personnel de test n'a pas le module finance.
const AUTRE_DEPARTEMENT = 'Licences logiciel de comptabilité';

describe('Finances et achats', () => {
    let duDepartement;
    let dUnAutre;

    before(() => {
        connexion('superadmin');
        appel('GET', '/api/finance_bon_commandes').then(({ status, body }) => {
            expect(status).to.eq(200);
            dUnAutre = body.find((demande) => demande.objetDemande === AUTRE_DEPARTEMENT).id;
            duDepartement = body.find((demande) => demande.objetDemande !== AUTRE_DEPARTEMENT).id;
        });
        cy.clearAllCookies();
    });

    it('sont fermées sans connexion', () => {
        [
            '/api/finance_bon_commandes',
            commande(duDepartement),
            ...documents(duDepartement),
            '/api/finance_receptions',
            '/api/finance_fournisseurs',
        ].forEach((url) => appel('GET', url).its('status').should('eq', 401));
        appel('POST', '/api/finance_bon_commandes', { objetDemande: 'Anonyme', montantHt: '1.00' })
            .its('status').should('eq', 401);
        appel('PATCH', commande(duDepartement), { objetDemande: 'Anonyme' }).its('status').should('eq', 401);
        appel('DELETE', commande(duDepartement)).its('status').should('eq', 401);
        appel('POST', `${commande(duDepartement)}/upload-signed-pdf`).its('status').should('eq', 401);
    });

    it('sont refusées à un étudiant', () => {
        connexion('etudiant');
        [
            '/api/finance_bon_commandes',
            commande(duDepartement),
            ...documents(duDepartement),
            '/api/finance_receptions',
            '/api/finance_fournisseurs',
        ].forEach((url) => appel('GET', url).its('status').should('eq', 403));
        appel('PATCH', commande(duDepartement), { objetDemande: 'Étudiant' }).its('status').should('eq', 403);
        appel('POST', `${commande(duDepartement)}/upload-signed-pdf`).its('status').should('eq', 403);
    });

    it('ne montrent au personnel que les demandes de son département', () => {
        connexion('personnel');
        appel('GET', '/api/finance_bon_commandes').then(({ status, body }) => {
            expect(status).to.eq(200);
            expect(body.map((demande) => demande.id)).to.include(duDepartement).and.not.include(dUnAutre);
        });
        appel('GET', commande(duDepartement)).its('status').should('eq', 200);
        [commande(dUnAutre), ...documents(dUnAutre)]
            .forEach((url) => appel('GET', url).its('status').should('eq', 403));
    });

    it('ne laissent pas modifier une demande sans le rôle de demandeur', () => {
        connexion('personnel');
        appel('PATCH', commande(duDepartement), { objetDemande: 'Consultation seule' }).its('status').should('eq', 403);
        appel('DELETE', commande(duDepartement)).its('status').should('eq', 403);
        appel('POST', `${commande(duDepartement)}/upload-signed-pdf`).its('status').should('eq', 403);
    });
});
