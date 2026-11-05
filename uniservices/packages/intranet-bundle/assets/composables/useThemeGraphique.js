import {onBeforeUnmount, onMounted, ref} from 'vue';

const JETONS = {
    texte: '--p-text-color',
    attenue: '--p-text-muted-color',
    grille: '--p-content-border-color',
    fond: '--p-content-background',
    violet: '--p-seance-cm-color',
    rose: '--p-seance-sae-color',
    vert: '--p-seance-tp-color',
    ocre: '--p-seance-td-color',
    rouge: '--p-seance-evaluation-color',
    gris: '--p-seance-autre-color',
};

const lireCouleurs = () => {
    const style = getComputedStyle(document.documentElement);
    return Object.fromEntries(Object.entries(JETONS).map(([nom, jeton]) => [nom, style.getPropertyValue(jeton).trim()]));
};

/**
 * Les couleurs des graphiques, lues dans les jetons du thème et relues quand l'utilisateur change
 * de mode : Chart.js dessine sur un canvas, que les variables CSS n'atteignent pas. Les couleurs de
 * séance sont celles déjà mesurées sur la carte, 5,65:1 au moins dans les deux modes.
 */
export function useThemeGraphique() {
    const couleurs = ref(lireCouleurs());
    const animation = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let observateur = null;

    onMounted(() => {
        observateur = new MutationObserver(() => {
            couleurs.value = lireCouleurs();
        });
        observateur.observe(document.documentElement, {attributes: true, attributeFilter: ['class']});
    });
    onBeforeUnmount(() => observateur?.disconnect());

    return {couleurs, animation};
}

/**
 * Le trait du seuil de validation, à 10, sur l'axe des notes, en pointillé pour ne pas reposer sur
 * la couleur. À la verticale, il porte son chiffre au-dessus ; à l'horizontale, l'axe le donne déjà.
 */
export const pluginSeuil = (axe, couleur) => ({
    id: 'seuil',
    afterDatasetsDraw(chart) {
        const echelle = chart.scales[axe];
        if (!echelle) {
            return;
        }
        const {ctx, chartArea} = chart;
        const position = echelle.getPixelForValue(10);
        ctx.save();
        ctx.strokeStyle = couleur;
        ctx.fillStyle = couleur;
        ctx.lineWidth = 1.5;
        ctx.setLineDash([5, 4]);
        ctx.font = '500 12px Roboto, sans-serif';
        ctx.beginPath();
        if (axe === 'x') {
            ctx.moveTo(position, chartArea.top);
            ctx.lineTo(position, chartArea.bottom);
            ctx.stroke();
            ctx.textAlign = 'center';
            ctx.fillText('10', position, chartArea.top - 6);
        } else {
            ctx.moveTo(chartArea.left, position);
            ctx.lineTo(chartArea.right, position);
            ctx.stroke();
        }
        ctx.restore();
    },
});

/**
 * Des hachures, pour une part qui doit se distinguer sans la couleur (la pénalité d'absence).
 */
export const hachures = (couleur) => {
    const motif = document.createElement('canvas');
    motif.width = 8;
    motif.height = 8;
    const ctx = motif.getContext('2d');
    ctx.strokeStyle = couleur;
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0, 8);
    ctx.lineTo(8, 0);
    ctx.stroke();
    return ctx.createPattern(motif, 'repeat');
};

export const policeGraphique = {family: 'Roboto, sans-serif', size: 13};
