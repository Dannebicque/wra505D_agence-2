export const LARGEUR_PANNEAU_DEFAUT = 480;
export const LARGEUR_PANNEAU_MIN = 360;
export const LARGEUR_PANNEAU_MAX = 960;

// La liste des documents garde au moins cette largeur à gauche du panneau.
const ESPACE_LISTE_MIN = 320;
const PAS_CLAVIER = 32;

export const largeurMax = (largeurFenetre: number): number =>
  Math.max(LARGEUR_PANNEAU_MIN, Math.min(LARGEUR_PANNEAU_MAX, largeurFenetre - ESPACE_LISTE_MIN));

export const bornerLargeur = (largeur: number, largeurFenetre: number): number =>
  Math.round(Math.min(largeurMax(largeurFenetre), Math.max(LARGEUR_PANNEAU_MIN, largeur)));

/**
 * Largeur après une touche sur la poignée, ou null si la touche ne la concerne pas. Le panneau
 * est ancré à droite : la flèche gauche éloigne son bord de l'écran, donc l'élargit.
 */
export const largeurAuClavier = (touche: string, largeur: number, largeurFenetre: number): number | null => {
  const cible: Record<string, number> = {
    ArrowLeft: largeur + PAS_CLAVIER,
    ArrowRight: largeur - PAS_CLAVIER,
    Home: LARGEUR_PANNEAU_MIN,
    End: largeurMax(largeurFenetre),
  };
  return touche in cible ? bornerLargeur(cible[touche], largeurFenetre) : null;
};
