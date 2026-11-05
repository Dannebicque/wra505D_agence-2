import {onBeforeUnmount, onMounted, ref} from 'vue';

const JETONS = {
    texte: '--p-text-color',
    attenue: '--p-text-muted-color',
    fond: '--p-content-background',
    violet: '--p-seance-cm-color',
    rose: '--p-seance-sae-color',
    vert: '--p-seance-tp-color',
    ocre: '--p-seance-td-color',
    rouge: '--p-seance-evaluation-color',
    vertFond: '--p-seance-tp-background',
    rougeFond: '--p-seance-evaluation-background',
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

export const policeGraphique = {family: 'Roboto, sans-serif', size: 14};

/**
 * Deux zones de part et d'autre du seuil : un voile rouge sous 10, un voile vert au-dessus. Très
 * légers, ils situent une valeur d'un coup d'œil sans gêner la lecture des barres et des courbes.
 */
export const pluginZones = (axe, couleurs) => ({
    id: 'zones',
    beforeDatasetsDraw(chart) {
        const echelle = chart.scales[axe];
        if (!echelle) {
            return;
        }
        const {ctx, chartArea} = chart;
        const seuil = echelle.getPixelForValue(10);
        const rayon = 10;
        const zone = (couleur, x, y, largeur, hauteur, rayons) => {
            ctx.fillStyle = couleur;
            ctx.beginPath();
            ctx.roundRect(x, y, largeur, hauteur, rayons);
            ctx.fill();
        };
        ctx.save();
        ctx.globalAlpha = 0.08;
        if (axe === 'x') {
            zone(couleurs.rouge, chartArea.left, chartArea.top, seuil - chartArea.left, chartArea.height, [rayon, 0, 0, rayon]);
            zone(couleurs.vert, seuil, chartArea.top, chartArea.right - seuil, chartArea.height, [0, rayon, rayon, 0]);
        } else {
            zone(couleurs.vert, chartArea.left, chartArea.top, chartArea.width, seuil - chartArea.top, [rayon, rayon, 0, 0]);
            zone(couleurs.rouge, chartArea.left, seuil, chartArea.width, chartArea.bottom - seuil, [0, 0, rayon, rayon]);
        }
        ctx.restore();
    },
});

/**
 * Une valeur écrite dans une pastille teintée, comme les notes de l'accueil : texte foncé sur fond
 * pâle du même type, 5,65:1 au moins.
 */
export const dessinerPastille = (ctx, texte, x, y, fond, couleur) => {
    ctx.font = '600 12px Roboto, sans-serif';
    const largeur = ctx.measureText(texte).width + 14;
    ctx.fillStyle = fond;
    ctx.beginPath();
    ctx.roundRect(x, y - 11, largeur, 22, 11);
    ctx.fill();
    ctx.fillStyle = couleur;
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'left';
    ctx.fillText(texte, x + 7, y + 0.5);
};

/**
 * L'entrée d'un graphique : les éléments arrivent l'un après l'autre, en partant de zéro. Rien du
 * tout si l'utilisateur a demandé à réduire les animations.
 */
export const animationEntree = (actif, ecart = 70) => (actif
    ? {
        duration: 700,
        easing: 'easeOutQuart',
        delay: (contexte) => (contexte.type === 'data' && contexte.mode === 'default' ? contexte.dataIndex * ecart + contexte.datasetIndex * 120 : 0),
    }
    : false);
