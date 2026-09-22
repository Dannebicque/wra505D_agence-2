import type { Session } from '~~/types/domain'
import { comptes } from '../../data/comptes'

export default defineEventHandler((event): Session => {
  const jeton = getCookie(event, 'BEARER')
  const compte = jeton
    ? comptes.find((c) => c.username === jeton.replace(/^mock\./, ''))
    : undefined

  if (!compte) {
    return { authenticated: false, userId: null, type: null, username: null }
  }

  return {
    authenticated: true,
    userId: compte.userId,
    type: compte.type,
    username: compte.username,
  }
})
