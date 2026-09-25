import type { LocationQuery } from 'vue-router';
import type { Category } from '@types';

/**
 * La catégorie affichée vit dans l'adresse (`?categorie=3`) : un lien la partage, un
 * rechargement la restitue.
 */
export function categorieDeLAdresse(query: LocationQuery): string | null {
  const categorie = query.categorie;

  return typeof categorie === 'string' && categorie !== '' ? categorie : null;
}

/**
 * Vrai si la catégorie cherchée se trouve parmi ces catégories ou leurs descendantes : son
 * parent se déplie pour qu'elle reste visible après un rechargement.
 */
export function contientCategorie(categories: Category[], id: string | null): boolean {
  if (id === null) {
    return false;
  }

  return categories.some(categorie => categorie.id === id || contientCategorie(categorie.children ?? [], id));
}
