/*
 * API Platform enveloppe toute collection dans une structure Hydra. Les mocks la
 * reproduisent pour que la bascule sur l'API reelle ne change rien cote appelant.
 *
 * Regle : le mock reste un sous-ensemble strict du contrat reel. Un champ absent ici
 * mais present la-bas ne casse personne ; l'inverse casserait a la bascule. C'est
 * pourquoi `view` et `search`, que l'API n'ajoute que sur certaines collections
 * paginees ou filtrables, ne sont pas emis ici.
 */
export function collectionHydra<T>(type: string, chemin: string, membres: T[]) {
  return {
    '@context': `/api/contexts/${type}`,
    '@id': chemin,
    '@type': 'Collection' as const,
    totalItems: membres.length,
    member: membres,
  }
}

/** Ressource JSON-LD : l'@id est l'IRI, il sert de reference entre ressources. */
export function ressource(type: string, id: number, chemin = type) {
  return {
    '@id': `/api/${chemin}/${id}`,
    '@type': type,
    id,
  }
}
