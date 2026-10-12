import type { DocumentType } from '@types';

export type TypeApercu = 'pdf' | 'image' | 'tableur';

export interface FeuilleApercu {
  nom: string;
  lignes: string[][];
  tronquee: boolean;
}

// Au-delà, le rendu du tableau ralentit nettement sur mobile ; le fichier complet reste téléchargeable.
export const LIGNES_MAX = 200;
export const COLONNES_MAX = 26;

const TYPES_APERCU: Partial<Record<DocumentType, TypeApercu>> = {
  pdf: 'pdf',
  image: 'image',
  excel: 'tableur',
};

export const typeApercu = (type: DocumentType): TypeApercu | null => TYPES_APERCU[type] ?? null;

export const lettreColonne = (index: number): string => {
  let lettres = '';
  for (let n = index + 1; n > 0; n = Math.floor((n - 1) / 26)) {
    lettres = String.fromCharCode(65 + ((n - 1) % 26)) + lettres;
  }
  return lettres;
};

/**
 * Texte affiché pour une valeur de cellule exceljs : résultat des formules, texte des liens et
 * des textes enrichis, nombres et dates au format français.
 */
export const texteCellule = (valeur: unknown): string => {
  if (valeur === null || valeur === undefined) return '';
  if (valeur instanceof Date) return valeur.toLocaleDateString('fr-FR');
  if (typeof valeur === 'number') return valeur.toLocaleString('fr-FR');
  if (typeof valeur === 'boolean') return valeur ? 'VRAI' : 'FAUX';
  if (typeof valeur !== 'object') return String(valeur);

  if ('richText' in valeur && Array.isArray(valeur.richText)) {
    return valeur.richText.map((morceau: { text?: string }) => morceau.text ?? '').join('');
  }
  if ('formula' in valeur || 'sharedFormula' in valeur) {
    return 'result' in valeur ? texteCellule(valeur.result) : '';
  }
  if ('error' in valeur) return String(valeur.error);
  if ('text' in valeur) return texteCellule(valeur.text);
  return '';
};

export const lireClasseur = async (fichier: Blob): Promise<FeuilleApercu[]> => {
  // Import à la demande : exceljs pèse lourd et n'est utile qu'à l'ouverture d'un tableur.
  const { default: ExcelJS } = await import('exceljs');
  const classeur = new ExcelJS.Workbook();
  await classeur.xlsx.load(await fichier.arrayBuffer());

  return classeur.worksheets.map((feuille) => {
    const nombreLignes = Math.min(feuille.rowCount, LIGNES_MAX);
    const nombreColonnes = Math.min(feuille.columnCount, COLONNES_MAX);
    const lignes: string[][] = [];
    for (let l = 1; l <= nombreLignes; l++) {
      const ligne = feuille.getRow(l);
      lignes.push(Array.from({ length: nombreColonnes }, (_, c) => texteCellule(ligne.getCell(c + 1).value)));
    }
    return {
      nom: feuille.name,
      lignes,
      tronquee: feuille.rowCount > LIGNES_MAX || feuille.columnCount > COLONNES_MAX,
    };
  });
};
