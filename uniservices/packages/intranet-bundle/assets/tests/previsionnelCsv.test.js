import { describe, expect, it } from 'vitest';
import { colonnesPrevisionnel, decouperCsv, lireCsvPrevisionnel } from '@/service/utils/previsionnelCsv.js';

const fichier = (texte) => ({ size: texte.length, text: async () => texte });
const ligne = (valeurs) => colonnesPrevisionnel.map((colonne) => valeurs[colonne] ?? '0').join(';');

describe('decouperCsv', () => {
  it('lit les guillemets, les points-virgules cités et les fins de ligne Windows', () => {
    expect(decouperCsv('﻿a;"b;c";"d ""e"""\r\n\r\n1;2;3\n')).toEqual([
      ['a', 'b;c', 'd "e"'],
      ['1', '2', '3'],
    ]);
  });
});

describe('lireCsvPrevisionnel', () => {
  it('convertit chaque ligne en heures et groupes', async () => {
    const csv = [colonnesPrevisionnel.join(';'), ligne({ personnel: '12', enseignement: 'R1.01', heures_CM: '10,5', groupes_TD: '2' })].join('\n');

    const [resultat] = await lireCsvPrevisionnel(fichier(csv));

    expect(resultat.personnel).toBe(12);
    expect(resultat.enseignement).toBe('R1.01');
    expect(resultat.heures.CM).toBe(10.5);
    expect(resultat.groupes.TD).toBe(2);
  });

  it('refuse un en-tête qui ne suit pas le modèle', async () => {
    await expect(lireCsvPrevisionnel(fichier('personnel;enseignement\n1;R1.01'))).rejects.toThrow('modèle');
  });
});
