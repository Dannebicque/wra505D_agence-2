import { widgets } from '../../../data/portail'

/*
 * L'API renvoie tous les widgets du tableau de bord demande. Ceux qui ne sont pas
 * places ont `position` a null : c'est au front de ne pas les afficher.
 */
export default defineEventHandler(() => ({ widgets }))
