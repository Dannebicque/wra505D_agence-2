import { documents } from '../data/documents'

/*
 * L'API n'accepte aucun filtre sur cette collection, uniquement la pagination :
 * 30 elements par page, `pagination=false` renvoie tout et supprime `view`. Le mock
 * reproduit ce comportement, sinon un filtrage cote serveur marcherait ici et
 * serait ignore par la vraie API.
 */
export default defineEventHandler((event) => {
  const { page, itemsPerPage, pagination } = getQuery(event)

  const enveloppe = collectionHydra('Document', '/api/documents', documents)

  if (pagination === 'false') {
    return enveloppe
  }

  const parPage = Number(itemsPerPage ?? 30)
  const numeroPage = Number(page ?? 1)
  const debut = (numeroPage - 1) * parPage

  return {
    ...enveloppe,
    member: documents.slice(debut, debut + parPage),
    view: { '@id': '/api/documents', '@type': 'PartialCollectionView' as const },
  }
})
