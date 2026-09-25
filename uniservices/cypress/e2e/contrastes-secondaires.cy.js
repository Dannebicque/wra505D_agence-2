/**
 * Rapport de contraste d'un texte avec son fond, en remontant jusqu'au premier fond non
 * transparent. Les fonds mesurés ici sont pleins : pas de transparence à composer.
 */
const contraste = (element) => {
    const win = element.ownerDocument.defaultView;
    const canal = (v) => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    // Le canevas ramène en RGB les formats que renvoie le navigateur, oklch de Tailwind compris.
    const pinceau = element.ownerDocument.createElement('canvas').getContext('2d');
    const luminance = (couleur) => {
        pinceau.fillStyle = couleur;
        pinceau.fillRect(0, 0, 1, 1);
        const [r, g, b] = [...pinceau.getImageData(0, 0, 1, 1).data].slice(0, 3).map(canal);
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    let fond = 'rgb(255, 255, 255)';
    for (let e = element; e; e = e.parentElement) {
        const couleur = win.getComputedStyle(e).backgroundColor;
        if (couleur && couleur !== 'rgba(0, 0, 0, 0)' && couleur !== 'transparent') {
            fond = couleur;
            break;
        }
    }
    const [clair, fonce] = [luminance(win.getComputedStyle(element).color), luminance(fond)].sort((a, b) => b - a);
    return (clair + 0.05) / (fonce + 0.05);
};

const opaciteTotale = (element) => {
    let opacite = 1;
    for (let e = element; e; e = e.parentElement) {
        opacite *= Number(element.ownerDocument.defaultView.getComputedStyle(e).opacity);
    }
    return opacite;
};

describe('Contrastes des textes secondaires', () => {
    beforeEach(() => {
        cy.viewport(1280, 800);
        cy.connexionInvite('etudiant');
    });

    it('lit les heures de l\'emploi du temps sans les estomper', () => {
        cy.visit('/app/intranet/agenda');
        cy.get('.vuecal__time-cell label', { timeout: 15000 }).first().then(($heure) => {
            expect(opaciteTotale($heure[0])).to.eq(1);
            expect(contraste($heure[0])).to.be.at.least(4.5);
        });
    });

    it('lit la date du jour et les filtres non choisis', () => {
        cy.visit('/app/intranet/');
        cy.contains('small', /\d{4}/, { timeout: 15000 }).then(($date) => {
            expect(contraste($date[0])).to.be.at.least(4.5);
        });

        cy.visit('/app/intranet/notifications');
        cy.contains('.p-togglebutton', 'Non lues', { timeout: 15000 }).find('.p-togglebutton-label').then(($filtre) => {
            expect(contraste($filtre[0])).to.be.at.least(4.5);
        });
    });

    it('lit le nombre de documents', () => {
        cy.visit('/app/documents');
        cy.contains('p', 'documents', { timeout: 15000 }).then(($compteur) => {
            expect(contraste($compteur[0])).to.be.at.least(4.5);
        });
    });
});
