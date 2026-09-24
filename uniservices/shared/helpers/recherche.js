/**
 * Recherche côté front : les pages de l'intranet, que l'API ne connaît pas, et la destination de
 * chaque résultat. La comparaison suit les règles de CorrespondanceTolerante côté serveur.
 */

export const TYPES_RESULTAT = [
    {type: 'page', libelle: 'Pages'},
    {type: 'enseignement', libelle: 'Matières'},
    {type: 'personnel', libelle: 'Personnels'},
    {type: 'etudiant', libelle: 'Étudiants'},
    {type: 'document', libelle: 'Documents'},
    {type: 'actualite', libelle: 'Actualités'},
];

export const normaliser = (texte) => (texte ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

const mots = (texte) => {
    const normalise = normaliser(texte);
    return normalise === '' ? [] : normalise.split(' ');
};

const fautesAdmises = (mot) => (mot.length <= 3 ? 0 : (mot.length <= 7 ? 1 : 2));

// Une inversion de deux lettres voisines compte pour une seule faute, comme côté serveur.
const distance = (a, b) => {
    const d = Array.from({length: a.length + 1}, (_, i) => [i, ...Array(b.length).fill(0)]);
    for (let j = 1; j <= b.length; j++) {
        d[0][j] = j;
    }
    for (let i = 1; i <= a.length; i++) {
        for (let j = 1; j <= b.length; j++) {
            const cout = a[i - 1] === b[j - 1] ? 0 : 1;
            d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + cout);
            if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
            }
        }
    }
    return d[a.length][b.length];
};

const motCorrespond = (motRequete, motTexte) => {
    if (motTexte.startsWith(motRequete)) {
        return true;
    }
    const admises = fautesAdmises(motRequete);
    if (admises === 0) {
        return false;
    }
    if (distance(motRequete, motTexte) <= admises) {
        return true;
    }
    // Mot en cours de frappe : début du mot du texte, à une ou deux lettres près en longueur.
    for (let longueur = motRequete.length - admises; longueur <= Math.min(motTexte.length - 1, motRequete.length + admises); longueur++) {
        if (distance(motRequete, motTexte.slice(0, longueur)) <= admises) {
            return true;
        }
    }
    return false;
};

/**
 * Vrai si chaque mot de la requête retrouve un mot du texte.
 */
export const correspond = (requete, texte) => {
    const motsRequete = mots(requete);
    const motsTexte = mots(texte);
    if (motsRequete.length === 0 || motsTexte.length === 0) {
        return false;
    }
    return motsRequete.every((motRequete) => motsTexte.some((motTexte) => motCorrespond(motRequete, motTexte)));
};

/**
 * @param {Array<{libelle: string}>} pages
 */
export const filtrerPages = (pages, requete) => pages.filter((page) => correspond(requete, page.libelle));

/**
 * Où mène un résultat. Les personnes n'ont pas de page publique : on leur écrit.
 * Il n'existe encore ni fiche matière ni fiche document : on ouvre l'écran qui les montre.
 *
 * @returns {{to?: string, href?: string} | null}
 */
export const destinationResultat = (resultat) => {
    switch (resultat.type) {
        case 'page':
            return {to: resultat.to};
        case 'etudiant':
        case 'personnel':
            return resultat.mail ? {href: `mailto:${resultat.mail}`} : null;
        case 'enseignement':
            return {to: '/intranet/agenda'};
        case 'document':
            return {to: '/documents'};
        case 'actualite':
            return {to: '/auth/portail'};
        default:
            return null;
    }
};

/**
 * Regroupe les résultats par type, dans l'ordre de TYPES_RESULTAT, en ignorant les groupes vides.
 */
export const grouperResultats = (resultats) => TYPES_RESULTAT
    .map(({type, libelle}) => ({libelle, items: resultats.filter((resultat) => resultat.type === type)}))
    .filter((groupe) => groupe.items.length > 0);
