/**
 * Lundi de la semaine de rentrée, avec la même règle que CelcatFausseBaseCommand : l'année bascule
 * à la mi-août. La CI génère la fausse base Celcat le jour même, ses cours partent de ce lundi.
 */
const lundiDeLaRentree = (aujourdhui) => {
    const mois = aujourdhui.getMonth() + 1;
    const annee = (mois === 8 && aujourdhui.getDate() >= 15) || mois > 8
        ? aujourdhui.getFullYear()
        : aujourdhui.getFullYear() - 1;
    const premierSeptembre = new Date(annee, 8, 1);
    const decalage = (premierSeptembre.getDay() + 6) % 7;

    return new Date(annee, 8, 1 - decalage);
};

/**
 * Mercredi de la troisième semaine de cours, loin des vacances : l'agenda s'ouvre sur une semaine
 * qui a toujours des cours, quel que soit le jour où la CI tourne. L'horloge n'est figée qu'après
 * la connexion : le formulaire a besoin que le temps s'écoule.
 */
const ouvrirAgendaEnSemaineDeCours = () => {
    const rentree = lundiDeLaRentree(new Date());
    cy.clock(new Date(rentree.getFullYear(), rentree.getMonth(), rentree.getDate() + 16, 9, 0), ['Date']);
    cy.visit('/app/intranet/agenda');
};

describe('Agenda de l\'étudiant', () => {
    beforeEach(() => {
        cy.connexionInvite('etudiant');
        ouvrirAgendaEnSemaineDeCours();
    });

    it('affiche les cours de ses groupes', () => {
        cy.get('.vuecal__event', { timeout: 15000 }).should('have.length.greaterThan', 0);
        cy.contains('Une erreur est survenue lors du chargement de l\'emploi du temps').should('not.exist');
    });

    it('atteint un cours au clavier, ouvre son détail et rend le focus en le fermant', () => {
        cy.get('button.edt-cours', { timeout: 15000 }).should('have.length.greaterThan', 0);

        cy.contains('button', 'Aujourd\'hui').focus();
        cy.press(Cypress.Keyboard.Keys.TAB);
        cy.focused().should('have.class', 'edt-cours').invoke('attr', 'aria-label').then((libelle) => {
            // Espace plutôt qu'Entrée : dans Electron, cy.press n'active pas un bouton avec Entrée.
            cy.press(Cypress.Keyboard.Keys.SPACE);
            cy.get('[role="dialog"]').should('be.visible');
            ['Appel', 'Tous présents', 'Plan de cours', 'Saisir les notes'].forEach((action) => {
                cy.get('[role="dialog"]').find(`[aria-label="${action}"]`).should('not.exist');
            });

            cy.press(Cypress.Keyboard.Keys.ESC);
            cy.get('[role="dialog"]').should('not.exist');
            cy.focused().should('have.attr', 'aria-label', libelle);
        });
    });

    it('annonce la semaine affichée quand on en change', () => {
        cy.contains('[role="status"]', 'Semaine du', { timeout: 15000 }).invoke('text').then((semaine) => {
            cy.get('button[aria-label="Semaine suivante"]').focus();
            cy.press(Cypress.Keyboard.Keys.SPACE);
            cy.contains('[role="status"]', 'Semaine du', { timeout: 15000 }).should('not.have.text', semaine);
        });
    });
});

/** Rapport de contraste entre deux couleurs CSS, converties par un canevas (oklch, color-mix). */
const rapport = (doc, avant, fond) => {
    const pinceau = doc.createElement('canvas').getContext('2d', { willReadFrequently: true });
    const rgb = (couleur, dessous) => {
        pinceau.fillStyle = dessous;
        pinceau.fillRect(0, 0, 1, 1);
        pinceau.fillStyle = couleur;
        pinceau.fillRect(0, 0, 1, 1);
        return [...pinceau.getImageData(0, 0, 1, 1).data].slice(0, 3);
    };
    const luminance = ([r, g, b]) => {
        const canal = (v) => (v / 255 <= 0.03928 ? v / 255 / 12.92 : ((v / 255 + 0.055) / 1.055) ** 2.4);
        return 0.2126 * canal(r) + 0.7152 * canal(g) + 0.0722 * canal(b);
    };
    const plein = rgb(fond, '#ffffff');
    const [a, b] = [luminance(rgb(avant, `rgb(${plein})`)), luminance(plein)].sort((x, y) => y - x);
    return (a + 0.05) / (b + 0.05);
};

/** Contraste le plus faible entre un texte d'une carte de cours et le fond de sa carte. */
const pireContrasteDesCartes = ($cartes) => Math.min(...$cartes.toArray().flatMap((carte) => {
    const win = carte.ownerDocument.defaultView;
    const fond = win.getComputedStyle(carte).backgroundColor;
    return [...carte.querySelectorAll('p, span, strong')]
        .filter((texte) => texte.innerText.trim())
        .map((texte) => rapport(carte.ownerDocument, win.getComputedStyle(texte).color, fond));
}));

describe('Semaine de l\'étudiant en bureau', () => {
    beforeEach(() => {
        cy.viewport(1440, 900);
        cy.connexionInvite('etudiant');
        ouvrirAgendaEnSemaineDeCours();
        cy.get('.cours-carte', { timeout: 15000 }).should('have.length.greaterThan', 0);
    });

    it('garde la plage horaire de l\'année, de 8 h à 19 h, d\'une semaine à l\'autre', () => {
        const plage = () => cy.get('.vuecal__time-cell').should(($heures) => {
            expect($heures.first().text().trim()).to.eq('08:00');
            expect($heures.last().text().trim()).to.eq('18:00');
        });
        plage();
        cy.get('button[aria-label="Semaine suivante"]').click();
        cy.contains('[role="status"]', 'Semaine du');
        plage();
    });

    it('montre chaque cours en entier, de 45 min à 4 h 15', () => {
        cy.get('.cours-carte--ligne').should('exist');
        cy.get('.cours-carte--compacte').should('exist');
        cy.get('.cours-carte').each(($carte) => {
            const carte = $carte[0];
            expect(carte.scrollHeight, carte.innerText).to.be.at.most(carte.clientHeight + 1);
            const bas = carte.getBoundingClientRect().bottom;
            carte.querySelectorAll('p').forEach((ligne) => {
                expect(ligne.getBoundingClientRect().bottom, ligne.innerText).to.be.at.most(bas + 1);
            });
        });
    });

    it('n\'ajoute le samedi que la semaine où il porte un cours', () => {
        cy.contains('.vuecal__weekday', /samedi/i).should('exist');
        cy.get('button[aria-label="Semaine suivante"]').click();
        cy.contains('[role="status"]', 'Semaine du');
        cy.contains('.vuecal__weekday', /vendredi/i).should('exist');
        cy.contains('.vuecal__weekday', /samedi/i).should('not.exist');
    });

    it('colore chaque cours par son type de séance, avec les jetons du thème', () => {
        cy.contains('.cours-carte__type', /^CM$/).closest('.cours-carte').then(($carte) => {
            const win = $carte[0].ownerDocument.defaultView;
            const jeton = win.getComputedStyle($carte[0].ownerDocument.documentElement).getPropertyValue('--p-seance-cm-background').trim();
            expect(rapport($carte[0].ownerDocument, win.getComputedStyle($carte[0]).backgroundColor, jeton)).to.be.closeTo(1, 0.01);
        });
    });

    it('lit chaque carte à 4,5:1 au moins, en clair comme en sombre', () => {
        cy.get('.cours-carte').then(($cartes) => {
            expect(pireContrasteDesCartes($cartes)).to.be.at.least(4.5);
        });
        cy.get('button[aria-label="Mode sombre"]').click();
        cy.get('html').should('have.class', 'app-dark');
        cy.get('.cours-carte').then(($cartes) => {
            expect(pireContrasteDesCartes($cartes)).to.be.at.least(4.5);
        });
    });
});

describe('Agenda de l\'étudiant sur téléphone', () => {
    beforeEach(() => {
        cy.viewport(375, 812);
        cy.connexionInvite('etudiant');
        ouvrirAgendaEnSemaineDeCours();
    });

    it('s\'ouvre sur la journée, lisible sans rien couper', () => {
        cy.get('.vuecal__event', { timeout: 15000 }).should('have.length.greaterThan', 0);
        cy.contains('button', 'Jour').should('have.attr', 'aria-pressed', 'true');
        cy.get('.vuecal h2').invoke('text').should('match', /^Mercredi \d/);
        cy.contains(/^Semaine \d+ · \d+e semaine de formation$/);
        cy.contains('Semaine de formation :').should('not.exist');

        cy.contains('button', 'Aujourd\'hui').should(($bouton) => {
            expect($bouton[0].scrollWidth).to.be.at.most($bouton[0].clientWidth);
        });
        cy.get('#contenu-principal').should(($contenu) => {
            expect($contenu[0].scrollWidth).to.be.at.most($contenu[0].clientWidth);
        });
    });
});
