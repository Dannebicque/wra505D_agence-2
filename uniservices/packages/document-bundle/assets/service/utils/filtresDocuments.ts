import type { LocationQuery, LocationQueryRaw } from 'vue-router';
import type { Category, Document, SortField, SortOrder } from '@types';
import { idsDeLaCategorie } from './categorieUtils';

export interface Tri {
  field: SortField;
  order: SortOrder;
}

/**
 * Tout ce qui restreint ou ordonne la liste. Les filtres se cumulent, et l'adresse les porte :
 * une vue filtrée se partage, se met en favori et résiste au rechargement.
 */
export interface FiltresDocuments {
  categorie: string | null;
  enseignement: string | null;
  favoris: boolean;
  recherche: string;
  tri: Tri;
}

/**
 * Les tris, nommés en français dans l'adresse. Le tri par défaut n'y figure pas.
 */
const TRIS: Record<string, Tri> = {
  recent: { field: 'lastModified', order: 'desc' },
  ancien: { field: 'lastModified', order: 'asc' },
  titre: { field: 'title', order: 'asc' },
  'titre-inverse': { field: 'title', order: 'desc' },
  lourd: { field: 'size', order: 'desc' },
  leger: { field: 'size', order: 'asc' },
};

const TRI_PAR_DEFAUT = 'recent';

const texte = (valeur: LocationQuery[string]): string => (typeof valeur === 'string' ? valeur : '');

export function lireFiltres(query: LocationQuery): FiltresDocuments {
  return {
    categorie: texte(query.categorie) || null,
    enseignement: texte(query.enseignement) || null,
    favoris: query.favoris === '1',
    recherche: texte(query.q),
    tri: TRIS[texte(query.tri)] ?? TRIS[TRI_PAR_DEFAUT],
  };
}

export function ecrireFiltres(filtres: FiltresDocuments): LocationQueryRaw {
  const query: LocationQueryRaw = {};
  const tri = Object.keys(TRIS).find(nom => TRIS[nom].field === filtres.tri.field && TRIS[nom].order === filtres.tri.order);

  if (filtres.categorie) query.categorie = filtres.categorie;
  if (filtres.enseignement) query.enseignement = filtres.enseignement;
  if (filtres.favoris) query.favoris = '1';
  if (filtres.recherche.trim() !== '') query.q = filtres.recherche;
  if (tri && tri !== TRI_PAR_DEFAUT) query.tri = tri;

  return query;
}

export function aDesFiltres(filtres: FiltresDocuments): boolean {
  return filtres.categorie !== null || filtres.enseignement !== null || filtres.favoris || filtres.recherche.trim() !== '';
}

/**
 * Ce que la recherche parcourt pour un document.
 */
export function texteDuDocument(document: Document): string {
  return [document.title, document.description, document.author, ...document.tags].filter(Boolean).join(' ');
}

/**
 * Favoris, enseignement et catégorie, sous-catégories comprises. La recherche s'ajoute ensuite.
 */
export function filtrerDocuments(documents: Document[], categories: Category[], filtres: FiltresDocuments): Document[] {
  const categoriesRetenues = filtres.categorie === null ? null : idsDeLaCategorie(categories, filtres.categorie);

  return documents.filter(document =>
    (!filtres.favoris || document.isFavorite)
    && (filtres.enseignement === null || document.enseignement?.id === filtres.enseignement)
    && (categoriesRetenues === null || categoriesRetenues.has(document.categoryId))
  );
}

const valeurDeTri = (document: Document, field: SortField): string | number => {
  switch (field) {
    case 'title':
      return document.title;
    case 'lastModified':
      return new Date(document.lastModified).getTime();
    case 'size':
      return document.size;
    case 'type':
      return document.type;
  }
};

export function trierDocuments(documents: Document[], tri: Tri): Document[] {
  const sens = tri.order === 'asc' ? 1 : -1;

  return [...documents].sort((a, b) => {
    const valeurA = valeurDeTri(a, tri.field);
    const valeurB = valeurDeTri(b, tri.field);

    // Une comparaison de chaînes brute rangerait « Écriture » après « Zoom ».
    if (typeof valeurA === 'string' && typeof valeurB === 'string') {
      return sens * valeurA.localeCompare(valeurB, 'fr', { sensitivity: 'base' });
    }

    return valeurA < valeurB ? -sens : valeurA > valeurB ? sens : 0;
  });
}
