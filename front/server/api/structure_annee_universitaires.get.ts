import { anneesUniversitaires } from '../data/structure'

export default defineEventHandler((event) => {
  const { actif } = getQuery(event)
  const membres =
    actif === undefined
      ? anneesUniversitaires
      : anneesUniversitaires.filter((a) => a.actif === (actif === 'true'))

  return collectionHydra(
    'StructureAnneeUniversitaire',
    '/api/structure_annee_universitaires',
    membres,
  )
})
