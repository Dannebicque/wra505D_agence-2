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
                .to.deep.eq(['À faire', 'Dernières notes', 'Nouveaux documents', 'Actualités', 'Contacts']);
        });
        cy.get('#accueil-bonjour').should('contain', 'Bonjour');
        cy.contains('button', 'Personnaliser').should('not.exist');
        cy.get('button[aria-label="Configurer le widget"]').should('not.exist');
        cy.contains('Mon dashboard').should('not.exist');
    });

    it('aligne en bureau la journée et « À faire », puis documents, actualités et notes', () => {
        cy.visit('/app/intranet/');
        const haut = (selecteur) => cy.get(selecteur, { timeout: 15000 }).then(($bloc) => Math.round($bloc[0].getBoundingClientRect().top));
        haut('.journee').then((journee) => haut('.accueil__a-faire').should('eq', journee));
        haut('.accueil__documents').then((documents) => {
            haut('.accueil__actualites').should('eq', documents);
            haut('.accueil__notes').should('eq', documents);
        });
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

    it('colore chaque note selon son seuil, avec les jetons du thème', () => {
        cy.intercept('GET', '**/api/me/scolarite', releve);
        cy.visit('/app/intranet/');

        const pastille = (type) => cy.window().then((fenetre) => fenetre.getComputedStyle(fenetre.document.documentElement)
            .getPropertyValue(`--p-seance-${type}-pill-background`).trim());
        const fond = (texte) => cy.get('.accueil__notes', { timeout: 15000 }).contains('.note', texte)
            .then(($note) => $note[0].style.background);

        [['8 / 20', 'evaluation'], ['12 / 20', 'cm'], ['15 / 20', 'tp']].forEach(([texte, type]) => {
            fond(texte).should('eq', `var(--p-seance-${type}-pill-background)`);
            pastille(type).should('not.be.empty');
        });
    });
});
