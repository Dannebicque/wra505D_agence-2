// L'étudiant ne choisit pas d'application : ses entrées, réparties entre les modules, forment un
// seul menu, le même sur chaque page.
export const GROUPES_MENU_ETUDIANT = [
    { cle: 'scolarite', label: 'Mon espace', icon: 'pi pi-fw pi-graduation-cap' },
    { cle: 'demarches', label: 'Démarches', icon: 'pi pi-fw pi-briefcase' },
];

/**
 * @param {Array<{name: string, studentMenu?: Array<{label: string, icon: string, to: string, groupe: string, ordre: number, motsCles?: string[]}>}>} bundles
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
                .map(({ label: libelle, icon: icone, to, motsCles }) => ({ label: libelle, icon: icone, to, motsCles })),
        }))
        .filter(groupe => groupe.items.length > 0);
};
