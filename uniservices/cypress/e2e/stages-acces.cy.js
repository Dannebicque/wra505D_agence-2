// Les stages des étudiants (entreprise, tuteurs, gratification, évaluation) étaient lisibles et
// modifiables sans connexion. L'étudiant ne lit et ne dépose que son propre stage ; le personnel
// garde ses écrans de suivi.
const connexion = (login) => {
    cy.request('POST', '/api/login', { username: login, password: 'test' });
};

const appel = (method, url, body) => cy.request({
    method,
    url,
    body,
    headers: {
        Accept: 'application/json',
        'Content-Type': method === 'PATCH' ? 'application/merge-patch+json' : 'application/ld+json',
    },
    failOnStatusCode: false,
});

const stage = (id) => `/api/stage_etudiants/${id}`;

const listesDuPersonnel = [
    '/api/stage_soutenances',
    '/api/stage_avenants',
    '/api/stage_convention_templates',
];

describe('Stages des étudiants', () => {
    let moi;
    let autre;
    let periode;
    let sonStage;
    let maDemande;

    before(() => {
        connexion('etudiant');
        cy.request('/api/me/security-context').then(({ body }) => {
            moi = body.user.id;
        });
        connexion('personnel');
        cy.request('/api/etudiants').then(({ body }) => {
            autre = body.member.find((etudiant) => etudiant.id !== moi).id;
        });
        appel('GET', '/api/stage_periodes').then(({ body }) => {
            periode = `/api/stage_periodes/${body[0].id}`;
            appel('POST', '/api/stage_etudiants', { stagePeriode: periode, etudiant: `/api/etudiants/${autre}` })
                .then((reponse) => {
                    expect(reponse.status).to.eq(201);
                    sonStage = reponse.body.id;
                });
        });
        // L'isolation des tests ne vide pas les cookies posés par before() avant le premier test.
        cy.clearAllCookies();
    });

    after(() => {
        connexion('personnel');
        [sonStage, maDemande].filter(Boolean).forEach((id) => appel('DELETE', stage(id)));
    });

    it('sont fermés sans connexion', () => {
        [
            '/api/stage_etudiants',
            stage(sonStage),
            '/api/stage_periodes',
            '/api/stage_periode_interruptions',
            '/api/stage_periode_soutenances',
            ...listesDuPersonnel,
        ].forEach((url) => appel('GET', url).its('status').should('eq', 401));
        appel('POST', '/api/stage_etudiants', { stagePeriode: periode, etudiant: `/api/etudiants/${autre}` })
            .its('status').should('eq', 401);
        appel('PATCH', stage(sonStage), { sujetStage: 'Anonyme' }).its('status').should('eq', 401);
        appel('DELETE', stage(sonStage)).its('status').should('eq', 401);
        appel('GET', `${stage(sonStage)}/pdf`).its('status').should('eq', 401);
        appel('POST', '/api/stage_soutenances/save', {}).its('status').should('eq', 401);
    });

    it('un étudiant ne voit ni ne touche le stage d\'un autre', () => {
        connexion('etudiant');
        appel('GET', '/api/stage_etudiants').then(({ status, body }) => {
            expect(status).to.eq(200);
            expect(body.map((s) => s.id)).not.to.include(sonStage);
            body.forEach((s) => expect(s.etudiant.id).to.eq(moi));
        });
        appel('GET', stage(sonStage)).its('status').should('eq', 403);
        appel('PATCH', stage(sonStage), { sujetStage: 'Pas le mien' }).its('status').should('eq', 403);
        appel('DELETE', stage(sonStage)).its('status').should('eq', 403);
        appel('GET', `${stage(sonStage)}/pdf`).its('status').should('eq', 403);
        appel('POST', '/api/stage_etudiants', { stagePeriode: periode, etudiant: `/api/etudiants/${autre}` })
            .its('status').should('eq', 403);
        listesDuPersonnel.forEach((url) => appel('GET', url).its('status').should('eq', 403));
        appel('POST', '/api/stage_soutenances/save', {}).its('status').should('eq', 403);
    });

    it('un étudiant dépose sa demande de convention, sans pouvoir la valider', () => {
        connexion('etudiant');
        appel('GET', '/api/stage_periodes').its('status').should('eq', 200);
        appel('POST', '/api/stage_etudiants', {
            stagePeriode: periode,
            etudiant: `/api/etudiants/${moi}`,
            etatStage: 'ETAT_STAGE_VALIDE',
        }).its('status').should('eq', 403);
        appel('POST', '/api/stage_etudiants', {
            stagePeriode: periode,
            etudiant: `/api/etudiants/${moi}`,
            sujetStage: 'Ma demande',
            etatStage: 'ETAT_STAGE_AUTORISE',
        }).then(({ status, body }) => {
            expect(status).to.eq(201);
            maDemande = body.id;
            appel('GET', stage(maDemande)).its('status').should('eq', 200);
            appel('GET', '/api/stage_etudiants').its('body').then((stages) => {
                expect(stages.map((s) => s.id)).to.include(maDemande);
            });
            appel('PATCH', stage(maDemande), { sujetStage: 'Ma demande précisée' }).its('status').should('eq', 200);
            appel('PATCH', stage(maDemande), { evaluationNote: 20 }).its('status').should('eq', 403);
        });
    });

    it('restent ouverts au personnel', () => {
        connexion('personnel');
        appel('GET', '/api/stage_etudiants').then(({ status, body }) => {
            expect(status).to.eq(200);
            expect(body.map((s) => s.id)).to.include(sonStage);
        });
        appel('GET', stage(sonStage)).its('status').should('eq', 200);
        appel('PATCH', stage(sonStage), { evaluationCommentaire: 'Suivi en cours' }).its('status').should('eq', 200);
        listesDuPersonnel.forEach((url) => appel('GET', url).its('status').should('eq', 200));
        appel('PATCH', periode, {}).its('status').should('eq', 200);
    });
});
