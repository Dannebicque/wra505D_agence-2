import { absences } from '../../data/pedagogie'

export default defineEventHandler(() =>
  collectionHydra('EtudiantAbsence', '/api/absence/etudiant_scolarite_semestres', absences),
)
