import {describe, expect, it} from 'vitest';
import {
    absencesARegler,
    chargeDeLaSemaine,
    evenementDuJour,
    joursAvant,
    libelleDelai,
    libelleJour,
    prochainesVacances,
    premierCoursApres,
    tonNote,
} from './accueil.js';

const cours = (codeModule, debut, fin) => ({codeModule, debut, fin});

describe('joursAvant', () => {
    it('compte 0 jour pour aujourd\'hui, même tard le soir', () => {
        expect(joursAvant('2026-10-13T08:00:00+00:00', new Date(2026, 9, 13, 23, 59))).toBe(0);
    });

    it('compte 1 jour pour demain', () => {
        expect(joursAvant('2026-10-14T08:00:00+00:00', new Date(2026, 9, 13, 12, 0))).toBe(1);
    });

    it('compte un nombre négatif pour une date passée', () => {
        expect(joursAvant('2026-10-10T08:00:00+00:00', new Date(2026, 9, 13, 12, 0))).toBe(-3);
    });

    it('compte 2 jours à travers le passage à l\'heure d\'hiver', () => {
        expect(joursAvant('2026-10-26T08:00:00+00:00', new Date(2026, 9, 24, 12, 0))).toBe(2);
    });

    // La nuit de l'heure d'été ne dure que 23 h : sans arrondi, deux jours en feraient un.
    it('compte 2 jours à travers le passage à l\'heure d\'été', () => {
        expect(joursAvant('2026-03-30', new Date(2026, 2, 28, 12, 0))).toBe(2);
    });
});

describe('libelleDelai', () => {
    it('annonce « aujourd\'hui » pour le jour même', () => {
        expect(libelleDelai('2026-10-13T08:00:00+00:00', new Date(2026, 9, 13, 9, 0))).toBe('aujourd\'hui');
    });

    it('annonce « demain » pour le lendemain', () => {
        expect(libelleDelai('2026-10-14T08:00:00+00:00', new Date(2026, 9, 13, 9, 0))).toBe('demain');
    });

    it('annonce « dans 18 jours » pour une date plus lointaine', () => {
        expect(libelleDelai('2026-10-31T08:00:00+00:00', new Date(2026, 9, 13, 9, 0))).toBe('dans 18 jours');
    });

    it('annonce « aujourd\'hui » pour une date passée', () => {
        expect(libelleDelai('2026-10-10T08:00:00+00:00', new Date(2026, 9, 13, 9, 0))).toBe('aujourd\'hui');
    });
});

describe('libelleJour', () => {
    it('annonce « aujourd\'hui » pour le même jour', () => {
        expect(libelleJour(new Date(2026, 9, 13, 8, 0), new Date(2026, 9, 13, 20, 0))).toBe('aujourd\'hui');
    });

    it('annonce « demain » pour le lendemain', () => {
        expect(libelleJour(new Date(2026, 9, 14, 8, 0), new Date(2026, 9, 13, 20, 0))).toBe('demain');
    });

    it('annonce « lundi » pour un lundi vu depuis un vendredi', () => {
        expect(libelleJour(new Date(2026, 9, 19, 8, 0), new Date(2026, 9, 16, 8, 0))).toBe('lundi');
    });
});

describe('premierCoursApres', () => {
    it('ignore les cours du jour même, même encore à venir', () => {
        const liste = [
            cours('CE_JOUR', '2026-10-13T18:00:00+00:00', '2026-10-13T20:00:00+00:00'),
            cours('DEMAIN', '2026-10-14T08:00:00+00:00', '2026-10-14T10:00:00+00:00'),
        ];
        expect(premierCoursApres(liste, new Date(2026, 9, 13, 12, 0)).codeModule).toBe('DEMAIN');
    });

    it('renvoie le plus tôt des jours suivants, même si la liste n\'est pas triée', () => {
        const liste = [
            cours('APRES_DEMAIN', '2026-10-15T08:00:00+00:00', '2026-10-15T10:00:00+00:00'),
            cours('DEMAIN_TARD', '2026-10-14T16:00:00+00:00', '2026-10-14T18:00:00+00:00'),
            cours('DEMAIN_TOT', '2026-10-14T08:00:00+00:00', '2026-10-14T10:00:00+00:00'),
        ];
        expect(premierCoursApres(liste, new Date(2026, 9, 13, 12, 0)).codeModule).toBe('DEMAIN_TOT');
    });

    it('renvoie null quand aucun cours n\'est à venir', () => {
        expect(premierCoursApres([], new Date(2026, 9, 13, 12, 0))).toBeNull();
        expect(premierCoursApres(
            [cours('CE_JOUR', '2026-10-13T18:00:00+00:00', '2026-10-13T20:00:00+00:00')],
            new Date(2026, 9, 13, 12, 0),
        )).toBeNull();
    });

    it('renvoie debut et fin convertis en Date', () => {
        const liste = [cours('DEMAIN', '2026-10-14T08:30:00+00:00', '2026-10-14T10:00:00+00:00')];
        const prochain = premierCoursApres(liste, new Date(2026, 9, 13, 12, 0));
        expect(prochain.debut).toBeInstanceOf(Date);
        expect(prochain.debut).toEqual(new Date(2026, 9, 14, 8, 30));
        expect(prochain.fin).toEqual(new Date(2026, 9, 14, 10, 0));
    });
});

describe('tonNote', () => {
    it('renvoie null pour une note absente', () => {
        expect(tonNote(null)).toBeNull();
        expect(tonNote(undefined)).toBeNull();
    });

    it.each([
        [9.99, 'faible'],
        [10, 'moyenne'],
        [13.99, 'moyenne'],
        [14, 'bonne'],
        [0, 'faible'],
    ])('%s sur 20 donne « %s »', (note, attendu) => {
        expect(tonNote(note)).toBe(attendu);
    });
});

describe('absencesARegler', () => {
    it('répartit les absences sans justificatif ou refusées, en attente, et ignore les validées', () => {
        const releve = {
            semestres: [{
                absences: {
                    liste: [
                        {id: 1, justification: 'aucune'},
                        {id: 2, justification: 'validee'},
                        {id: 3, justification: 'en_attente'},
                    ],
                },
            }],
        };
        const {aJustifier, enAttente} = absencesARegler(releve);
        expect(aJustifier.map(absence => absence.id)).toEqual([1]);
        expect(enAttente.map(absence => absence.id)).toEqual([3]);
    });

    it('réunit les absences de plusieurs semestres dans l\'ordre', () => {
        const releve = {
            semestres: [
                {absences: {liste: [
                    {id: 1, justification: 'aucune'},
                    {id: 2, justification: 'en_attente'},
                ]}},
                {absences: {liste: [
                    {id: 3, justification: 'refusee'},
                    {id: 4, justification: 'en_attente'},
                ]}},
            ],
        };
        const {aJustifier, enAttente} = absencesARegler(releve);
        expect(aJustifier.map(absence => absence.id)).toEqual([1, 3]);
        expect(enAttente.map(absence => absence.id)).toEqual([2, 4]);
    });

    it('renvoie deux listes vides sans relevé ou sans absences', () => {
        expect(absencesARegler(null)).toEqual({aJustifier: [], enAttente: []});
        expect(absencesARegler({semestres: [{absences: {liste: []}}]}))
            .toEqual({aJustifier: [], enAttente: []});
    });
});

describe('chargeDeLaSemaine', () => {
    const lundi = new Date(2026, 9, 12);
    const seance = (jour, debut, fin) => ({debut: `2026-10-${jour}T${debut}:00+00:00`, fin: `2026-10-${jour}T${fin}:00+00:00`});

    it('additionne les minutes de chaque jour, du lundi au vendredi', () => {
        const jours = chargeDeLaSemaine([seance(12, '08:00', '10:00'), seance(12, '13:30', '15:30'), seance(14, '10:15', '12:15')], lundi);
        expect(jours.map(jour => jour.minutes)).toEqual([240, 0, 120, 0, 0]);
        expect(jours[0].date).toEqual(new Date(2026, 9, 12));
    });

    it('n\'ajoute le samedi que s\'il porte un cours', () => {
        expect(chargeDeLaSemaine([], lundi)).toHaveLength(5);
        const jours = chargeDeLaSemaine([seance(17, '09:00', '12:00')], lundi);
        expect(jours).toHaveLength(6);
        expect(jours[5].minutes).toBe(180);
    });

    it('ignore un cours aux dates illisibles', () => {
        expect(chargeDeLaSemaine([{debut: null, fin: null}], lundi).every(jour => jour.minutes === 0)).toBe(true);
    });
});

describe('prochainesVacances', () => {
    const semaine = (lundi, minutes) => ({lundi, minutes});
    const annee = [
        semaine('2026-10-12', 1800),
        semaine('2026-10-19', 1500),
        semaine('2026-10-26', 0),
        semaine('2026-11-02', 1600),
    ];

    it('trouve la première semaine sans cours à venir, et la fait commencer au samedi d\'avant', () => {
        const vacances = prochainesVacances(annee, new Date(2026, 9, 14, 10, 0));
        expect(vacances.debut).toEqual(new Date(2026, 9, 24));
        expect(vacances.jours).toBe(10);
        expect(vacances.nom).toBe('de la Toussaint');
    });

    it('ignore la semaine en cours, même sans cours', () => {
        const vacances = prochainesVacances([semaine('2026-10-12', 0), ...annee.slice(1)], new Date(2026, 9, 14));
        expect(vacances.debut).toEqual(new Date(2026, 9, 24));
    });

    it('ne prend pas la fin de l\'année pour des vacances', () => {
        expect(prochainesVacances([semaine('2026-10-12', 1800), semaine('2026-10-19', 0)], new Date(2026, 9, 14))).toBeNull();
    });

    it('lit les semaines dans l\'ordre, quel que soit celui de l\'API', () => {
        expect(prochainesVacances([...annee].reverse(), new Date(2026, 9, 14)).jours).toBe(10);
    });
});

describe('evenementDuJour', () => {
    // Mercredi 14 octobre 2026, 4 cours ; semaines : vacances la semaine du 26.
    const mercredi = new Date(2026, 9, 14, 9, 0);
    const seance = (jour, debut, fin, extra = {}) => ({
        start: new Date(2026, 9, jour, ...debut.split(':').map(Number)),
        end: new Date(2026, 9, jour, ...fin.split(':').map(Number)),
        codeModule: 'R1.02',
        evaluation: false,
        ...extra,
    });
    const semaine = (minutes) => minutes.map((m, i) => ({date: new Date(2026, 9, 12 + i), minutes: m}));
    const base = {
        aujourdhui: mercredi,
        prenom: 'Jane',
        naissance: '2006-03-02',
        coursDuJour: [seance(14, '08:00', '10:00'), seance(14, '10:15', '12:15')],
        semaine: semaine([465, 300, 240, 480, 360]),
        semaines: [{lundi: '2026-10-05', minutes: 1800}, {lundi: '2026-10-12', minutes: 1845}, {lundi: '2026-10-19', minutes: 1500}, {lundi: '2026-10-26', minutes: 0}, {lundi: '2026-11-02', minutes: 1600}],
        semaineFormation: 6,
        vacances: {jours: 10, nom: 'de la Toussaint'},
        evaluations: [],
    };

    it('souhaite l\'anniversaire avant tout', () => {
        expect(evenementDuJour({...base, naissance: '2006-10-14'}).cle).toBe('anniversaire');
        expect(evenementDuJour({...base, naissance: '2006-10-14'}).texte).toBe('Joyeux anniversaire, Jane');
    });

    it('annonce une évaluation du jour, puis une évaluation plus tard dans la semaine', () => {
        expect(evenementDuJour({...base, coursDuJour: [seance(14, '08:00', '10:00', {evaluation: true})]}).texte)
            .toBe('Évaluation aujourd\'hui en R1.02');
        expect(evenementDuJour({...base, evaluations: [seance(15, '10:00', '12:00', {evaluation: true})]}).texte)
            .toBe('Évaluation jeudi en R1.02');
    });

    it('dit bonnes vacances pendant une semaine sans cours entre deux semaines de cours', () => {
        const vacances = evenementDuJour({...base, aujourdhui: new Date(2026, 9, 28), coursDuJour: [], semaine: semaine([0, 0, 0, 0, 0])});
        expect(vacances.texte).toBe('Bonnes vacances de la Toussaint');
    });

    it('repère la veille des vacances et le retour de vacances', () => {
        const vendredi = {...base, aujourdhui: new Date(2026, 9, 23), vacances: {jours: 1, nom: 'de la Toussaint'}, semaine: semaine([300, 300, 300, 300, 300])};
        expect(evenementDuJour(vendredi).cle).toBe('veille-de-vacances');
        const lundiRetour = {...base, aujourdhui: new Date(2026, 10, 2), semaine: [{date: new Date(2026, 10, 2), minutes: 300}], vacances: null};
        expect(evenementDuJour(lundiRetour).cle).toBe('retour');
    });

    it('fête la rentrée la première semaine de formation', () => {
        expect(evenementDuJour({...base, semaineFormation: 1}).cle).toBe('rentree');
    });

    it('distingue le week-end, le dernier jour de cours et une journée sans cours', () => {
        expect(evenementDuJour({...base, aujourdhui: new Date(2026, 9, 17), coursDuJour: []}).cle).toBe('week-end');
        expect(evenementDuJour({...base, aujourdhui: new Date(2026, 9, 16), vacances: null}).cle).toBe('fin-de-semaine');
        expect(evenementDuJour({...base, coursDuJour: []}).cle).toBe('sans-cours');
    });

    it('fait varier le message ordinaire d\'un jour à l\'autre', () => {
        const cles = new Set([12, 13, 14, 15].map((jour) => evenementDuJour({
            ...base,
            aujourdhui: new Date(2026, 9, jour, 9, 0),
            coursDuJour: [seance(jour, '08:00', '10:00')],
            semaine: semaine([120, 120, 120, 120, 480]),
            vacances: {jours: 20, nom: 'de la Toussaint'},
            semaineFormation: 5,
        }).cle));
        expect(cles.size).toBeGreaterThan(1);
    });

    it('n\'invente jamais un événement : il rend toujours un texte et une icône', () => {
        const evenement = evenementDuJour({aujourdhui: mercredi, prenom: 'Jane', coursDuJour: [seance(14, '08:00', '17:00')]});
        expect(evenement.texte).toBeTruthy();
        expect(evenement.icone).toMatch(/^pi pi-/);
    });
});

