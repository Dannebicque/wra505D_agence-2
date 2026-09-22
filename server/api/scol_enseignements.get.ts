import { matieres } from '../data/pedagogie'

export default defineEventHandler(() =>
  collectionHydra('ScolEnseignement', '/api/scol_enseignements', matieres),
)
