import { scolariteSemestres } from '../data/structure'

export default defineEventHandler(() =>
  collectionHydra(
    'EtudiantScolariteSemestre',
    '/api/etudiant_scolarite_semestres',
    scolariteSemestres,
  ),
)
