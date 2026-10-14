import {describe, expect, it} from 'vitest';
import {
    adresseAgenda,
    annonceAgenda,
    coursParJour,
    densiteCarte,
    etatCours,
    joursDeLaSemaine,
    libelleCours,
    libelleSemaine,
    lireAdresseAgenda,
    pausesDuJour,
    saisonDeVacances,
    titrePeriode,
    typeSeance,
} from './agenda.js';

const cours = (jour, debut, fin, champs = {}) => {
    const [hd, md] = debut.split(':').map(Number);
    const [hf, mf] = fin.split(':').map(Number);
    return {
        codeModule: 'SAE1.01',
        libModule: 'Portfolio',
        type: 'TD',
        location: 'Salle B204',
        evaluation: false,
        start: new Date(2026, 8, jour, hd, md),
        end: new Date(2026, 8, jour, hf, mf),
        ...champs,
    };
};

describe('libelleCours', () => {
    it('donne l\'intitulé, le type, le jour, l\'horaire et la salle', () => {
        expect(libelleCours(cours(22, '08:00', '12:15')))
            .toBe('SAE1.01 - Portfolio, TD, mardi 22 septembre de 8 h 00 à 12 h 15, Salle B204');
    });

    it('commence par le code du module, seul texte visible quand deux cours se chevauchent', () => {
        expect(libelleCours(cours(22, '08:00', '10:00'))).toMatch(/^SAE1\.01/);
    });

    it('signale une évaluation', () => {
        expect(libelleCours(cours(22, '14:00', '16:00', {evaluation: true}))).toMatch(/, évaluation$/);
    });

    it('omet ce que Celcat ne renseigne pas', () => {
        expect(libelleCours(cours(22, '14:00', '16:00', {libModule: null, type: null, location: ''})))
            .toBe('SAE1.01, mardi 22 septembre de 14 h 00 à 16 h 00');
    });
});

describe('annonceAgenda', () => {
    const semaine = [
        cours(21, '08:00', '10:00'),
        cours(22, '08:00', '12:15'),
        cours(22, '14:00', '16:00'),
        cours(28, '08:00', '10:00'),
    ];
    const lundi = new Date(2026, 8, 21);
    const finVendredi = new Date(2026, 8, 25, 23, 59, 59, 999);

    it('annonce la semaine affichée et le nombre de ses cours', () => {
        expect(annonceAgenda({vue: 'week', debut: lundi, fin: finVendredi, cours: semaine}))
            .toBe('Semaine du lundi 21 septembre au vendredi 25 septembre : 3 cours');
    });

    it('annonce le jour affiché en vue jour', () => {
        const debut = new Date(2026, 8, 22);
        const fin = new Date(2026, 8, 22, 23, 59, 59, 999);
        expect(annonceAgenda({vue: 'day', debut, fin, cours: semaine}))
            .toBe('Journée du mardi 22 septembre : 2 cours');
    });

    it('dit qu\'il n\'y a aucun cours plutôt que « 0 cours »', () => {
        expect(annonceAgenda({vue: 'week', debut: lundi, fin: finVendredi, cours: []}))
            .toBe('Semaine du lundi 21 septembre au vendredi 25 septembre : aucun cours');
    });
});

describe('titrePeriode', () => {
    it('nomme le jour affiché en entier, avec une majuscule', () => {
        expect(titrePeriode({vue: 'day', debut: new Date(2026, 8, 16)})).toBe('Mercredi 16 septembre 2026');
        expect(titrePeriode({vue: 'day', debut: new Date(2026, 9, 1)})).toBe('Jeudi 1er octobre 2026');
    });

    it('ne répète ni le mois ni l\'année d\'une semaine qui tient dans un mois', () => {
        expect(titrePeriode({vue: 'week', debut: new Date(2026, 8, 14), fin: new Date(2026, 8, 18, 23, 59)}))
            .toBe('14 – 18 septembre 2026');
    });

    it('nomme les deux mois, puis les deux années, d\'une semaine à cheval', () => {
        expect(titrePeriode({vue: 'week', debut: new Date(2026, 8, 28), fin: new Date(2026, 9, 2)}))
            .toBe('28 septembre – 2 octobre 2026');
        expect(titrePeriode({vue: 'week', debut: new Date(2026, 11, 28), fin: new Date(2027, 0, 1)}))
            .toBe('28 décembre 2026 – 1er janvier 2027');
    });
});

describe('libelleSemaine', () => {
    it('explique les deux numérotations sur une ligne', () => {
        expect(libelleSemaine(38, 2)).toBe('Semaine 38 · 2e semaine de formation');
        expect(libelleSemaine(37, 1)).toBe('Semaine 37 · 1re semaine de formation');
    });

    it('garde la seule semaine du calendrier hors des semaines de formation', () => {
        expect(libelleSemaine(52, 0)).toBe('Semaine 52');
    });
});

describe('typeSeance', () => {
    it('priorise l\'évaluation sur tout le reste', () => {
        expect(typeSeance(cours(14, '08:00', '10:00', {evaluation: true, type: 'CM', enseignement: {type: 'sae'}})))
            .toBe('evaluation');
    });

    it('reconnaît une SAE', () => {
        expect(typeSeance(cours(14, '08:00', '10:00', {type: 'TD', enseignement: {type: 'sae'}}))).toBe('sae');
    });

    it('reconnaît les trois types de séance, sans tenir compte de la casse', () => {
        expect(typeSeance(cours(14, '08:00', '10:00', {type: 'CM'}))).toBe('cm');
        expect(typeSeance(cours(14, '08:00', '10:00', {type: 'td'}))).toBe('td');
        expect(typeSeance(cours(14, '08:00', '10:00', {type: 'TP'}))).toBe('tp');
    });

    it('range le reste et l\'absence de type dans « autre »', () => {
        expect(typeSeance(cours(14, '08:00', '10:00', {type: 'examen'}))).toBe('autre');
        expect(typeSeance(cours(14, '08:00', '10:00', {type: undefined}))).toBe('autre');
    });

    it('laisse une ressource non SAE suivre le type de séance', () => {
        expect(typeSeance(cours(14, '08:00', '10:00', {type: 'CM', enseignement: {type: 'ressource'}}))).toBe('cm');
    });
});

describe('densiteCarte', () => {
    it('affiche une carte complète à partir de 86 pixels', () => {
        expect(densiteCarte(86)).toBe('complete');
        expect(densiteCarte(140)).toBe('complete');
    });

    it('passe en carte compacte entre 50 et 85 pixels', () => {
        expect(densiteCarte(85)).toBe('compacte');
        expect(densiteCarte(50)).toBe('compacte');
    });

    it('se réduit à une ligne sous 50 pixels', () => {
        expect(densiteCarte(49)).toBe('ligne');
        expect(densiteCarte(0)).toBe('ligne');
    });
});

describe('pausesDuJour', () => {
    it('remet les cours dans l\'ordre pour calculer la pause', () => {
        const journee = [cours(14, '10:30', '12:00'), cours(14, '08:00', '10:00')];
        expect(pausesDuJour(journee)).toEqual([
            {start: new Date(2026, 8, 14, 10, 0), end: new Date(2026, 8, 14, 10, 30), minutes: 30},
        ]);
    });

    it('fusionne les cours qui se chevauchent', () => {
        const journee = [cours(14, '08:00', '10:00'), cours(14, '09:30', '11:00'), cours(14, '11:30', '12:30')];
        expect(pausesDuJour(journee)).toEqual([
            {start: new Date(2026, 8, 14, 11, 0), end: new Date(2026, 8, 14, 11, 30), minutes: 30},
        ]);
    });

    it('fusionne deux cours qui se touchent', () => {
        const journee = [cours(14, '08:00', '10:00'), cours(14, '10:00', '11:00'), cours(14, '11:30', '12:00')];
        expect(pausesDuJour(journee)).toEqual([
            {start: new Date(2026, 8, 14, 11, 0), end: new Date(2026, 8, 14, 11, 30), minutes: 30},
        ]);
    });

    it('écarte un trou de moins de 15 minutes', () => {
        expect(pausesDuJour([cours(14, '08:00', '10:00'), cours(14, '10:10', '12:00')])).toEqual([]);
    });

    it('garde un trou d\'1 h 15 avec ses minutes', () => {
        expect(pausesDuJour([cours(14, '08:00', '10:00'), cours(14, '11:15', '12:00')])).toEqual([
            {start: new Date(2026, 8, 14, 10, 0), end: new Date(2026, 8, 14, 11, 15), minutes: 75},
        ]);
    });

    it('ne calcule rien avec moins de deux cours', () => {
        expect(pausesDuJour([])).toEqual([]);
        expect(pausesDuJour([cours(14, '08:00', '10:00')])).toEqual([]);
    });
});

describe('coursParJour', () => {
    it('regroupe les cours par jour et les trie', () => {
        const semaine = [
            cours(15, '14:00', '16:00'),
            cours(14, '14:00', '16:00'),
            cours(14, '08:00', '10:00'),
            cours(21, '09:00', '11:00'),
        ];
        const jours = coursParJour(semaine);
        expect(jours.map(({date}) => date.getDate())).toEqual([14, 15, 21]);
        expect(jours[0].cours.map(({start}) => start.getHours())).toEqual([8, 14]);
        expect(jours[0].date.getHours()).toBe(0);
        expect(jours[0].date.getMinutes()).toBe(0);
    });

    it('ne modifie pas le tableau reçu', () => {
        const semaine = [cours(15, '14:00', '16:00'), cours(14, '08:00', '10:00')];
        const ordre = semaine.map(({start}) => start.getDate());
        coursParJour(semaine);
        expect(semaine.map(({start}) => start.getDate())).toEqual(ordre);
    });
});

describe('joursDeLaSemaine', () => {
    const lundi = new Date(2026, 8, 14, 8, 30);

    it('rend du lundi au vendredi sans cours le week-end', () => {
        const jours = joursDeLaSemaine(lundi, [cours(14, '08:00', '10:00'), cours(16, '14:00', '16:00')]);
        expect(jours.map((date) => date.getDate())).toEqual([14, 15, 16, 17, 18]);
        expect(jours[0].getHours()).toBe(0);
    });

    it('ajoute le samedi quand un cours y a lieu', () => {
        const jours = joursDeLaSemaine(lundi, [cours(16, '14:00', '16:00'), cours(19, '08:00', '10:00')]);
        expect(jours).toHaveLength(6);
        expect(jours.at(-1).getDay()).toBe(6);
        expect(jours.at(-1).getDate()).toBe(19);
    });

    it('ajoute le week-end complet quand des cours y ont lieu', () => {
        const jours = joursDeLaSemaine(lundi, [cours(19, '08:00', '10:00'), cours(20, '08:00', '10:00')]);
        expect(jours.map((date) => date.getDate())).toEqual([14, 15, 16, 17, 18, 19, 20]);
    });

    it('ignore les cours des autres semaines', () => {
        const jours = joursDeLaSemaine(lundi, [cours(21, '08:00', '10:00'), cours(26, '08:00', '10:00')]);
        expect(jours.map((date) => date.getDate())).toEqual([14, 15, 16, 17, 18]);
    });
});

describe('etatCours', () => {
    const seance = cours(14, '08:00', '10:00');

    it('marque un cours passé, fin comprise', () => {
        expect(etatCours(seance, new Date(2026, 8, 14, 10, 0))).toEqual({etat: 'passe', dans: null});
        expect(etatCours(seance, new Date(2026, 8, 14, 12, 0))).toEqual({etat: 'passe', dans: null});
    });

    it('marque un cours en cours, début compris', () => {
        expect(etatCours(seance, new Date(2026, 8, 14, 8, 0))).toEqual({etat: 'en-cours', dans: null});
        expect(etatCours(seance, new Date(2026, 8, 14, 9, 30))).toEqual({etat: 'en-cours', dans: null});
    });

    it('annonce les minutes restantes avant le début', () => {
        expect(etatCours(seance, new Date(2026, 8, 14, 7, 40))).toEqual({etat: 'a-venir', dans: 20});
    });

    it('arrondit au supérieur les minutes avant le début', () => {
        expect(etatCours(seance, new Date(2026, 8, 14, 7, 40, 30))).toEqual({etat: 'a-venir', dans: 20});
    });
});

describe('saisonDeVacances', () => {
    it('reconnaît chaque période de vacances au mois de son lundi', () => {
        expect(saisonDeVacances('2026-10-26')).toBe('toussaint');
        expect(saisonDeVacances('2026-11-02')).toBe('toussaint');
        expect(saisonDeVacances('2026-12-21')).toBe('noel');
        expect(saisonDeVacances('2027-02-15')).toBe('hiver');
        expect(saisonDeVacances('2027-04-19')).toBe('printemps');
        expect(saisonDeVacances('2027-07-12')).toBe('ete');
    });
});

describe('adresse de l\'agenda', () => {
    it('lit la vue et la date de l\'adresse', () => {
        const {date, vue} = lireAdresseAgenda({affichage: 'jour', date: '2026-10-14'});
        expect(vue).toBe('day');
        expect([date.getFullYear(), date.getMonth(), date.getDate()]).toEqual([2026, 9, 14]);
        expect(lireAdresseAgenda({affichage: 'semaine'})).toEqual({date: null, vue: 'week'});
    });

    it('ignore une vue inconnue et une date mal formée ou impossible', () => {
        expect(lireAdresseAgenda({affichage: 'mois', date: '14/10/2026'})).toEqual({date: null, vue: null});
        expect(lireAdresseAgenda({date: '2026-02-31'}).date).toBeNull();
        expect(lireAdresseAgenda({date: ['2026-10-14']}).date).toBeNull();
        expect(lireAdresseAgenda({})).toEqual({date: null, vue: null});
    });

    it('n\'écrit que ce qui s\'écarte de l\'ouverture par défaut', () => {
        expect(adresseAgenda({date: '2026-10-14', vue: 'week'})).toEqual({affichage: 'semaine', date: '2026-10-14'});
        expect(adresseAgenda({date: null, vue: 'day'})).toEqual({affichage: 'jour'});
        expect(adresseAgenda({date: null, vue: null})).toEqual({});
    });

    it('relit ce qu\'elle écrit', () => {
        const {date, vue} = lireAdresseAgenda(adresseAgenda({date: '2027-01-04', vue: 'day'}));
        expect(vue).toBe('day');
        expect(date.toDateString()).toBe(new Date(2027, 0, 4).toDateString());
    });
});
