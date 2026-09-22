import { contexte } from '../../data/portail'
import { comptes } from '../../data/comptes'

export default defineEventHandler((event) => {
  const jeton = getCookie(event, 'BEARER')
  const compte = jeton
    ? comptes.find((c) => c.username === jeton.replace(/^mock\./, ''))
    : undefined

  if (!compte) {
    throw createError({ statusCode: 401, statusMessage: 'Unauthorized' })
  }

  return contexte
})
