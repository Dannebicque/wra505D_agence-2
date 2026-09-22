import { semestres } from '../data/structure'

export default defineEventHandler(() =>
  collectionHydra('StructureSemestre', '/api/structure_semestres', semestres),
)
