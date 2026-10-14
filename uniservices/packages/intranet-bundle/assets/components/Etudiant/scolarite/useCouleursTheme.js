import {onBeforeUnmount, onMounted, ref} from 'vue';

// Un canevas ne lit pas les variables CSS : les graphiques reçoivent les jetons du preset en
// valeurs, relues quand le mode sombre change la classe de la page.
const JETONS = {
    texte: '--p-text-color',
    attenue: '--p-text-muted-color',
    grille: '--p-content-border-color',
    primaire: '--p-primary-color',
    faible: '--p-seance-evaluation-color',
    faibleFond: '--p-seance-evaluation-background',
    moyenne: '--p-seance-cm-color',
    bonne: '--p-seance-tp-color',
};

const lire = () => {
    const style = getComputedStyle(document.documentElement);
    return Object.fromEntries(Object.entries(JETONS).map(([nom, variable]) => [nom, style.getPropertyValue(variable).trim()]));
};

export function useCouleursTheme() {
    const couleurs = ref(lire());
    let observateur = null;

    onMounted(() => {
        observateur = new MutationObserver(() => {
            couleurs.value = lire();
        });
        observateur.observe(document.documentElement, {attributes: true, attributeFilter: ['class']});
    });
    onBeforeUnmount(() => observateur?.disconnect());

    return couleurs;
}
