describe('Accueil de l\'étudiant', () => {
    const titres = () => cy.get('.accueil h2', { timeout: 15000 });

    // Une date locale AAAA-MM-JJ, à quelques jours d'aujourd'hui : le test tourne n'importe quel jour.
    const dansJours = (jours) => {
        const date = new Date();
        date.setDate(date.getDate() + jours);
        return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    };

    const evaluation = (libelle, note) => ({
        libelle, date: '2026-09-21', coefficient: 1, type: 'Devoir', etat: 'publiee', note, statut: null,
    });

    const releve = {
        anneeUniversitaire: '2026-2027',
        semestres: [{
            libelle: 'S1',
            ues: [{
                numero: 1,
                libelle: 'UE 1',
                moyenne: null,
                enseignements: [{
                    code: 'R1.06', libelle: 'Production graphique', type: 'ressource', coefficient: 1, moyenne: null,
                    evaluations: [evaluation('Sous la moyenne', 8), evaluation('Correcte', 12), evaluation('Bonne', 15)],
                }],
            }],
            absences: {
                total: 3, justifiees: 1, injustifiees: 1, enAttente: 1,
                liste: [
                    { debut: '2026-09-17T08:00:00+00:00', fin: '2026-09-17T10:00:00+00:00', matiere: 'R1.02 Culture numérique', justification: 'en_attente' },
                    { debut: '2026-09-10T13:30:00+00:00', fin: '2026-09-10T15:30:00+00:00', matiere: 'R1.10 Intégration', justification: 'aucune' },
                    { debut: '2026-09-04T10:15:00+00:00', fin: '2026-09-04T12:15:00+00:00', matiere: 'R1.03 Stratégies', justification: 'validee' },
                ],
            },
        }],
    };

    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
    });

    it('range ses blocs par ordre d\'importance, sans rien à configurer', () => {
        cy.visit('/app/intranet/');
        cy.contains('h1', 'Accueil');
        titres().should(($titres) => {
            expect([...$titres].map((titre) => titre.textContent.trim()).slice(1))
                .to.deep.eq(['Aujourd\'hui', 'Dernières notes', 'Absences et à faire', 'Nouveaux documents', 'Actualités', 'Contacts']);
        });
        cy.get('#accueil-bonjour').should('contain', 'Bonjour');
        cy.contains('button', 'Personnaliser').should('not.exist');
        cy.get('button[aria-label="Configurer le widget"]').should('not.exist');
        cy.contains('Mon dashboard').should('not.exist');
    });

    it('se lit en bureau par étages : la journée, puis notes et absences, puis le reste', () => {
        cy.visit('/app/intranet/');
        const boite = (selecteur) => cy.get(selecteur, { timeout: 15000 }).then(($bloc) => $bloc[0].getBoundingClientRect());
        boite('.bienvenue').then((bienvenue) => {
            boite('.accueil__cours').its('top').should('be.closeTo', bienvenue.top, 1);
            boite('.accueil__cours').its('left').should('be.greaterThan', bienvenue.right);
        });
        boite('.accueil__notes').then((notes) => boite('.accueil__a-faire').its('top').should('be.closeTo', notes.top, 1));
        boite('.accueil__documents').then((documents) => {
            boite('.accueil__actualites').its('top').should('be.closeTo', documents.top, 1);
            boite('.accueil__contacts').its('top').should('be.closeTo', documents.top, 1);
        });
    });

    it('réserve le rouge à ce qui demande une action', () => {
        cy.intercept('GET', '**/api/me/scolarite', releve);
        cy.visit('/app/intranet/');
        cy.get('.accueil__a-faire .ligne--action', { timeout: 15000 }).should('have.length', 1).and('contain', 'absence à justifier');
    });

    it('liste les absences à régler et les évaluations à venir', () => {
        cy.intercept('GET', '**/api/me/scolarite', releve);
        cy.intercept('GET', '**/api/me/cahier-de-texte', {
            matieres: [],
            echeances: [{ date: dansJours(3), libelle: 'Examen', type: 'Examen', coefficient: 1, code: 'R1.02', matiere: 'Culture numérique' }],
        });
        cy.visit('/app/intranet/');

        cy.get('.accueil__a-faire', { timeout: 15000 }).within(() => {
            cy.contains('a', '1 absence à justifier').should('contain', 'R1.10 Intégration').and('have.attr', 'href').and('match', /\/intranet\/scolarite$/);
            cy.contains('a', '1 justificatif en attente').should('contain', 'R1.02 Culture numérique');
            cy.contains('a', 'Examen').should('contain', 'R1.02 Culture numérique').and('contain', 'dans 3 jours');
            cy.get('a').each(($lien) => expect($lien[0].getBoundingClientRect().height).to.be.at.least(44));
        });
    });

    it('pose chaque note dans une pastille douce, teintée selon son seuil', () => {
        cy.intercept('GET', '**/api/me/scolarite', releve);
        cy.visit('/app/intranet/');

        cy.get('.accueil__notes', { timeout: 15000 }).within(() => {
            cy.contains('.note', '8 / 20').should('have.class', 'note--faible');
            cy.contains('.note', '12 / 20').should('have.class', 'note--moyenne');
            cy.contains('.note', '15 / 20').should('have.class', 'note--bonne');
            cy.get('.note--faible, .note--moyenne, .note--bonne').each(($note) => {
                expect($note.css('background-color')).not.to.match(/rgba\(0, 0, 0, 0\)|transparent/);
            });
        });
    });
});
