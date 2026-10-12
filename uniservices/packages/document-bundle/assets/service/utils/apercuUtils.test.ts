import { describe, expect, it } from 'vitest';
import ExcelJS from 'exceljs';
import { COLONNES_MAX, LIGNES_MAX, lettreColonne, lireClasseur, texteCellule, typeApercu } from './apercuUtils';

const classeur = async (remplir: (classeur: ExcelJS.Workbook) => void): Promise<Blob> => {
  const nouveau = new ExcelJS.Workbook();
  remplir(nouveau);
  return new Blob([await nouveau.xlsx.writeBuffer()]);
};

describe('typeApercu', () => {
  it('prévisualise les PDF, les images et les tableurs, et rien d\'autre', () => {
    expect(typeApercu('pdf')).toBe('pdf');
    expect(typeApercu('image')).toBe('image');
    expect(typeApercu('excel')).toBe('tableur');
    expect(typeApercu('word')).toBeNull();
    expect(typeApercu('archive')).toBeNull();
  });
});

describe('lettreColonne', () => {
  it('nomme les colonnes comme un tableur', () => {
    expect(lettreColonne(0)).toBe('A');
    expect(lettreColonne(25)).toBe('Z');
    expect(lettreColonne(26)).toBe('AA');
    expect(lettreColonne(701)).toBe('ZZ');
  });
});

describe('texteCellule', () => {
  it('affiche le résultat d\'une formule, pas la formule', () => {
    expect(texteCellule({ formula: 'SUM(B2:B8)', result: 20 })).toBe('20');
    expect(texteCellule({ formula: 'B2*2' })).toBe('');
  });

  it('assemble un texte enrichi et garde le texte d\'un lien', () => {
    expect(texteCellule({ richText: [{ text: 'Note ' }, { text: 'finale' }] })).toBe('Note finale');
    expect(texteCellule({ text: 'Moodle', hyperlink: 'https://moodle.example' })).toBe('Moodle');
  });

  it('formate nombres, dates et booléens en français', () => {
    expect(texteCellule(1234.5)).toBe((1234.5).toLocaleString('fr-FR'));
    expect(texteCellule(new Date(2026, 9, 12))).toBe('12/10/2026');
    expect(texteCellule(true)).toBe('VRAI');
    expect(texteCellule(null)).toBe('');
  });

  it('montre le code d\'une erreur de calcul', () => {
    expect(texteCellule({ error: '#DIV/0!' })).toBe('#DIV/0!');
  });
});

describe('lireClasseur', () => {
  it('lit chaque feuille avec ses cellules en texte', async () => {
    const fichier = await classeur((nouveau) => {
      const grille = nouveau.addWorksheet('Grille');
      grille.addRow(['Critère', 'Points']);
      grille.addRow(['Audit', 4]);
      grille.getCell('B3').value = { formula: 'SUM(B2:B2)', result: 4 };
      nouveau.addWorksheet('Notes').addRow(['Vide']);
    });

    const feuilles = await lireClasseur(fichier);

    expect(feuilles.map((feuille) => feuille.nom)).toEqual(['Grille', 'Notes']);
    expect(feuilles[0].lignes).toEqual([['Critère', 'Points'], ['Audit', '4'], ['', '4']]);
    expect(feuilles[0].tronquee).toBe(false);
  });

  it('borne une grande feuille et le signale', async () => {
    const fichier = await classeur((nouveau) => {
      const feuille = nouveau.addWorksheet('Export');
      for (let l = 0; l < LIGNES_MAX + 5; l++) feuille.addRow([l]);
      feuille.getCell(1, COLONNES_MAX + 3).value = 'loin';
    });

    const [feuille] = await lireClasseur(fichier);

    expect(feuille.lignes).toHaveLength(LIGNES_MAX);
    expect(feuille.lignes[0]).toHaveLength(COLONNES_MAX);
    expect(feuille.tronquee).toBe(true);
  });
});
