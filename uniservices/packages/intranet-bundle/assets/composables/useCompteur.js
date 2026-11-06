import {onBeforeUnmount, ref, watch} from 'vue';

/**
 * Un chiffre qui monte de zéro jusqu'à sa valeur à l'arrivée, puis suit ses changements. Il
 * s'affiche directement si l'utilisateur a demandé à réduire les animations. Le chiffre final est
 * celui que lit un lecteur d'écran : le composant le donne à part, en texte masqué.
 */
export function useCompteur(source, duree = 700) {
    const valeur = ref(0);
    const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let image = null;

    const animer = (cible) => {
        cancelAnimationFrame(image);
        if (reduit || typeof cible !== 'number') {
            valeur.value = cible;
            return;
        }
        const depart = valeur.value ?? 0;
        const debut = performance.now();
        const etape = (maintenant) => {
            const avancee = Math.min(1, (maintenant - debut) / duree);
            const adouci = 1 - (1 - avancee) ** 4;
            valeur.value = Math.round((depart + (cible - depart) * adouci) * 100) / 100;
            if (avancee < 1) {
                image = requestAnimationFrame(etape);
            }
        };
        image = requestAnimationFrame(etape);
    };

    watch(source, animer, {immediate: true});
    onBeforeUnmount(() => cancelAnimationFrame(image));
    return valeur;
}
