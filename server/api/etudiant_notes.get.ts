import { notes } from '../data/pedagogie'

export default defineEventHandler(() =>
  collectionHydra('EtudiantNote', '/api/etudiant_notes', notes),
)
