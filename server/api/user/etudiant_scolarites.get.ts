import { scolarites } from '../../data/structure'

export default defineEventHandler(() =>
  collectionHydra('EtudiantScolarite', '/api/user/etudiant_scolarites', scolarites),
)
