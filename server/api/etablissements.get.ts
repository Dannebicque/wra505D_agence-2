import { etablissement } from '../data/structure'

/*
 * Cette route ne renvoie pas une collection : l'API expose l'etablissement courant
 * directement sous /api/etablissements. Verifie sur l'API reelle.
 */
export default defineEventHandler(() => etablissement)
