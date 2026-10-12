import { describe, expect, it } from 'vitest';
import {
  LARGEUR_PANNEAU_MAX,
  LARGEUR_PANNEAU_MIN,
  bornerLargeur,
  largeurAuClavier,
  largeurMax,
} from './largeurPanneau';

describe('largeurMax', () => {
  it('laisse de la place à la liste sur un écran moyen', () => {
    expect(largeurMax(1280)).toBe(960);
    expect(largeurMax(1000)).toBe(680);
  });

  it('ne descend jamais sous la largeur minimale ni ne dépasse le plafond', () => {
    expect(largeurMax(500)).toBe(LARGEUR_PANNEAU_MIN);
    expect(largeurMax(2560)).toBe(LARGEUR_PANNEAU_MAX);
  });
});

describe('bornerLargeur', () => {
  it('garde une largeur dans les bornes et l\'arrondit au pixel', () => {
    expect(bornerLargeur(500.6, 1440)).toBe(501);
    expect(bornerLargeur(100, 1440)).toBe(LARGEUR_PANNEAU_MIN);
    expect(bornerLargeur(5000, 1000)).toBe(680);
  });
});

describe('largeurAuClavier', () => {
  it('élargit avec la flèche gauche et rétrécit avec la flèche droite', () => {
    expect(largeurAuClavier('ArrowLeft', 480, 1440)).toBe(512);
    expect(largeurAuClavier('ArrowRight', 480, 1440)).toBe(448);
  });

  it('va aux bornes avec Début et Fin', () => {
    expect(largeurAuClavier('Home', 600, 1440)).toBe(LARGEUR_PANNEAU_MIN);
    expect(largeurAuClavier('End', 600, 1000)).toBe(680);
  });

  it('ne dépasse pas les bornes en insistant', () => {
    expect(largeurAuClavier('ArrowRight', LARGEUR_PANNEAU_MIN, 1440)).toBe(LARGEUR_PANNEAU_MIN);
  });

  it('ignore les autres touches', () => {
    expect(largeurAuClavier('Enter', 480, 1440)).toBeNull();
  });
});
