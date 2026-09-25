import { describe, expect, it } from 'vitest';
import type { Category, Document, DocumentEnseignement } from '@types';
import {
  aDesFiltres,
  ecrireFiltres,
  filtrerDocuments,
  lireFiltres,
  texteDuDocument,
  trierDocuments,
  type FiltresDocuments,
} from './filtresDocuments';

const anglais: DocumentEnseignement = { id: '1', code: 'R1.01', libelle: 'Anglais', type: 'ressource' };

const document = (id: string, champs: Partial<Document> = {}): Document => ({
  id,
  title: `Document ${id}`,
  type: 'pdf',
  size: 100,
  lastModified: new Date(2026, 8, 1),
  categoryId: '',
  isFavorite: false,
  author: 'Service Scolarité',
  version: '1',
  tags: [],
  ...champs,
});

const categorie = (id: string, children: Category[] = []): Category => ({
  id, name: `Catégorie ${id}`, children, documentCount: 0, icon: 'pi pi-folder', color: 'bg-blue-500',
});

const sansFiltre = lireFiltres({});
const avec = (champs: Partial<FiltresDocuments>): FiltresDocuments => ({ ...sansFiltre, ...champs });

describe('lireFiltres et ecrireFiltres', () => {
  it('lit une adresse sans filtre comme la liste complète, triée du plus récent au plus ancien', () => {
    expect(sansFiltre).toEqual({
      categorie: null, enseignement: null, favoris: false, recherche: '',
      tri: { field: 'lastModified', order: 'desc' },
    });
    expect(ecrireFiltres(sansFiltre)).toEqual({});
  });

  it('écrit chaque filtre dans l\'adresse et le relit à l\'identique', () => {
    const filtres = avec({
      categorie: '3', enseignement: '1', favoris: true, recherche: 'guide',
      tri: { field: 'title', order: 'asc' },
    });
    const query = ecrireFiltres(filtres);

    expect(query).toEqual({ categorie: '3', enseignement: '1', favoris: '1', q: 'guide', tri: 'titre' });
    expect(lireFiltres(query as Record<string, string>)).toEqual(filtres);
  });

  it('ignore un tri inconnu, une valeur répétée et une recherche faite d\'espaces', () => {
    expect(lireFiltres({ tri: 'nimporte' }).tri).toEqual(sansFiltre.tri);
    expect(lireFiltres({ categorie: ['3', '4'] }).categorie).toBeNull();
    expect(ecrireFiltres(avec({ recherche: '   ' }))).toEqual({});
  });
});

describe('aDesFiltres', () => {
  it('ne compte pas le tri comme un filtre', () => {
    expect(aDesFiltres(avec({ tri: { field: 'title', order: 'asc' } }))).toBe(false);
    expect(aDesFiltres(avec({ favoris: true }))).toBe(true);
    expect(aDesFiltres(avec({ recherche: 'guide' }))).toBe(true);
  });
});

describe('filtrerDocuments', () => {
  const categories = [categorie('10', [categorie('11')]), categorie('20')];
  const documents = [
    document('a', { categoryId: '10', enseignement: anglais, isFavorite: true }),
    document('b', { categoryId: '11', enseignement: anglais }),
    document('c', { categoryId: '20', enseignement: anglais, isFavorite: true }),
    document('d', { categoryId: '11', isFavorite: true }),
  ];
  const ids = (liste: Document[]) => liste.map(d => d.id);

  it('garde tout sans filtre', () => {
    expect(ids(filtrerDocuments(documents, categories, sansFiltre))).toEqual(['a', 'b', 'c', 'd']);
  });

  it('inclut les sous-catégories de la catégorie choisie', () => {
    expect(ids(filtrerDocuments(documents, categories, avec({ categorie: '10' })))).toEqual(['a', 'b', 'd']);
  });

  it('cumule favoris, enseignement et catégorie', () => {
    expect(ids(filtrerDocuments(documents, categories, avec({ favoris: true, enseignement: '1' })))).toEqual(['a', 'c']);
    expect(ids(filtrerDocuments(documents, categories, avec({ favoris: true, enseignement: '1', categorie: '10' })))).toEqual(['a']);
  });
});

describe('texteDuDocument', () => {
  it('réunit titre, description, auteur et étiquettes', () => {
    const texte = texteDuDocument(document('a', { title: 'Guide du stage', description: 'Pour la S4', tags: ['urgent'] }));
    expect(texte).toBe('Guide du stage Pour la S4 Service Scolarité urgent');
  });
});

describe('trierDocuments', () => {
  const documents = [
    document('z', { title: 'Zoom', size: 300, lastModified: new Date(2026, 8, 3) }),
    document('e', { title: 'Écriture', size: 100, lastModified: new Date(2026, 8, 1) }),
    document('a', { title: 'atelier', size: 200, lastModified: new Date(2026, 8, 2) }),
  ];
  const titres = (liste: Document[]) => liste.map(d => d.title);

  it('range les titres comme un lecteur français, accents et majuscules compris', () => {
    expect(titres(trierDocuments(documents, { field: 'title', order: 'asc' }))).toEqual(['atelier', 'Écriture', 'Zoom']);
    expect(titres(trierDocuments(documents, { field: 'title', order: 'desc' }))).toEqual(['Zoom', 'Écriture', 'atelier']);
  });

  it('trie par date et par taille, sans modifier la liste reçue', () => {
    expect(titres(trierDocuments(documents, { field: 'lastModified', order: 'desc' }))).toEqual(['Zoom', 'atelier', 'Écriture']);
    expect(titres(trierDocuments(documents, { field: 'size', order: 'asc' }))).toEqual(['Écriture', 'atelier', 'Zoom']);
    expect(titres(documents)).toEqual(['Zoom', 'Écriture', 'atelier']);
  });
});
