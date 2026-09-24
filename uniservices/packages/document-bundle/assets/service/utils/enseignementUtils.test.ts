import { describe, expect, it } from 'vitest';
import type { Document, DocumentEnseignement } from '@types';
import { classerParEnseignement, libelleEnseignement } from './enseignementUtils';

const anglais: DocumentEnseignement = { id: '1', code: 'R1.01', libelle: 'Anglais', type: 'ressource' };
const integration: DocumentEnseignement = { id: '2', code: 'R1.10', libelle: 'Intégration', type: 'ressource' };
const culture: DocumentEnseignement = { id: '3', code: 'R1.02', libelle: 'Culture numérique', type: 'matiere' };
const sae: DocumentEnseignement = { id: '4', code: 'SAE1.01', libelle: 'Recommandation', type: 'sae' };

const document = (id: string, enseignement?: DocumentEnseignement): Document => ({
  id,
  title: `Document ${id}`,
  type: 'pdf',
  size: 0,
  lastModified: new Date(0),
  categoryId: '',
  isFavorite: false,
  author: '',
  version: 'v1.0',
  tags: [],
  enseignement
});

describe('classerParEnseignement', () => {
  it('compte les documents de chaque enseignement et ignore ceux qui n\'en ont pas', () => {
    const { matieres } = classerParEnseignement([
      document('a', anglais),
      document('b', anglais),
      document('c')
    ]);

    expect(matieres).toEqual([{ enseignement: anglais, documentCount: 2 }]);
  });

  it('sépare les SAÉ des matières, ressources comprises', () => {
    const { matieres, saes } = classerParEnseignement([
      document('a', sae),
      document('b', culture),
      document('c', anglais)
    ]);

    expect(matieres.map(g => g.enseignement.code)).toEqual(['R1.01', 'R1.02']);
    expect(saes.map(g => g.enseignement.code)).toEqual(['SAE1.01']);
  });

  it('range les codes dans l\'ordre numérique', () => {
    const { matieres } = classerParEnseignement([
      document('a', integration),
      document('b', culture),
      document('c', anglais)
    ]);

    expect(matieres.map(g => g.enseignement.code)).toEqual(['R1.01', 'R1.02', 'R1.10']);
  });

  it('ne renvoie rien sans document rattaché', () => {
    expect(classerParEnseignement([document('a')])).toEqual({ matieres: [], saes: [] });
  });
});

describe('libelleEnseignement', () => {
  it('écrit le code puis le libellé', () => {
    expect(libelleEnseignement(anglais)).toBe('R1.01 Anglais');
  });
});
