/*
 * Memes identifiants que les fixtures de uniServices, pour qu'un aller-retour entre
 * les donnees simulees et la vraie API ne demande pas de changer de compte.
 */
export const comptes = [
  { username: 'etudiant', motDePasse: 'test', userId: 1, type: 'etudiants' },
  { username: 'personnel', motDePasse: 'test', userId: 2, type: 'personnels' },
  { username: 'superadmin', motDePasse: 'test', userId: 3, type: 'personnels' },
]
