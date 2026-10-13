// Route de l'emploi du temps de l'étudiant (C17.1), sur la fausse base Celcat que la CI génère.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (url) => cy.request({ url, failOnStatusCode: false, headers: { Accept: 'application/json' } });

describe('Emploi du temps de l\'étudiant, côté API', () => {
    it('les créneaux ne sont plus lisibles sans connexion', () => {
        appel('/api/edt_events').its('status').should('eq', 401);
        appel('/api/me/emploi-du-temps').its('status').should('eq', 401);
    });

    it('donne la semaine de l\'étudiant, ses heures avec leur fuseau et la plage de son année', () => {
        connexion('etudiant');
        appel('/api/me/emploi-du-temps').then(({ status, body }) => {
            expect(status).to.eq(200);
            expect(body.semaine).to.match(/^\d{4}-\d{2}-\d{2}$/);
            // Le seul cours avant 8 h de la fausse base est dans un groupe qu'il ne suit pas.
            expect(body.plage).to.deep.eq({ debut: '08:00', fin: '19:00' });
            body.cours.forEach((cours) => {
                expect(cours.debut).to.match(/T\d{2}:\d{2}:00\+0[12]:00$/);
            });
        });
    });

    it('refuse une semaine illisible', () => {
        connexion('etudiant');
        appel('/api/me/emploi-du-temps?semaine=demain').its('status').should('eq', 400);
    });

    it('est réservée aux étudiants, le personnel garde les créneaux', () => {
        connexion('personnel');
        appel('/api/me/emploi-du-temps').its('status').should('eq', 403);
        appel('/api/edt_events').its('status').should('eq', 200);
    });
});
