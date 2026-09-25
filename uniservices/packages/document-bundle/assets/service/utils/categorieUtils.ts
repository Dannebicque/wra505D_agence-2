import type { Category } from '@types';

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

/**
 * La catégorie et toutes ses descendantes : choisir une catégorie montre aussi les documents de
 * ses sous-catégories.
 */
export function idsDeLaCategorie(categories: Category[], id: string): Set<string> {
  const ids = new Set<string>([id]);
  const ajouterDescendantes = (categorie: Category) => {
    for (const enfant of categorie.children ?? []) {
      ids.add(enfant.id);
      ajouterDescendantes(enfant);
    }
  };
  const trouver = (liste: Category[]): Category | undefined => {
    for (const categorie of liste) {
      const trouvee = categorie.id === id ? categorie : trouver(categorie.children ?? []);
      if (trouvee) {
        return trouvee;
      }
    }
    return undefined;
  };

  const categorie = trouver(categories);
  if (categorie) {
    ajouterDescendantes(categorie);
  }

  return ids;
}
