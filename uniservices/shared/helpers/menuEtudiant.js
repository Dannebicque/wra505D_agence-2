// L'étudiant ne choisit pas d'application : ses entrées, réparties entre les modules, forment un
// seul menu, le même sur chaque page.
export const GROUPES_MENU_ETUDIANT = [
    { cle: 'scolarite', label: 'Mon espace', icon: 'pi pi-fw pi-graduation-cap' },
    { cle: 'demarches', label: 'Démarches', icon: 'pi pi-fw pi-briefcase' },
];

/**
 * @param {Array<{name: string, studentMenu?: Array<{label: string, icon: string, to: string, groupe: string, ordre: number, onglet?: string, motsCles?: string[]}>}>} bundles
 * @param {(paquet: string) => boolean} aLePaquet
 */
export const menuEtudiant = (bundles, aLePaquet) => {
    const entrees = bundles
        .filter(bundle => Array.isArray(bundle.studentMenu) && aLePaquet(bundle.name))
        .flatMap(bundle => bundle.studentMenu);

    return GROUPES_MENU_ETUDIANT
        .map(({ cle, label, icon }) => ({
            label,
            icon,
            items: entrees
                .filter(entree => entree.groupe === cle)
                .sort((a, b) => a.ordre - b.ordre)
                .map(({ label: libelle, icon: icone, to, onglet, motsCles }) => ({ label: libelle, icon: icone, to, onglet, motsCles })),
        }))
        .filter(groupe => groupe.items.length > 0);
};

/** Au plus quatre onglets : avec « Plus », cinq cibles tiennent sur un téléphone de 320 px. */
export const ONGLETS_MAX = 4;

/**
 * Le menu étudiant partagé pour le téléphone : les entrées marquées d'un `onglet` forment la barre
 * du bas, dans l'ordre du menu ; les autres restent groupées comme sur le bureau, pour « Plus ».
 *
 * @param {Array<{label: string, icon: string, items: Array<{label: string, icon: string, to: string, onglet?: string}>}>} menu
 */
export const navigationMobile = (menu) => {
    const onglets = menu.flatMap(groupe => groupe.items.filter(item => item.onglet)).slice(0, ONGLETS_MAX);
    const autres = menu
        .map(groupe => ({ ...groupe, items: groupe.items.filter(item => !onglets.includes(item)) }))
        .filter(groupe => groupe.items.length > 0);
    return { onglets, autres };
};

/**
 * L'entrée du menu qui correspond à la page, ou rien. Une entrée qui mène à la racine d'un module
 * (« /intranet/ ») ne vaut que pour cette page : sinon l'accueil s'allumerait sur tout l'intranet.
 *
 * @param {Array<{to: string}>} entrees
 * @param {string} chemin
 */
export const entreeActive = (entrees, chemin) => {
    const page = chemin.replace(/\/+$/, '') || '/';
    return entrees
        .filter(({ to }) => {
            const cible = to.replace(/\/+$/, '') || '/';
            return to.endsWith('/') ? page === cible : page === cible || page.startsWith(`${cible}/`);
        })
        .sort((a, b) => b.to.length - a.to.length)[0] ?? null;
};
