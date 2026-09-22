import type { Session } from '~~/types/domain'

export default defineEventHandler((event): Session => {
  const jeton = getCookie(event, 'BEARER')

  if (!jeton) {
    return { authenticated: false, userId: null, type: null, username: null }
  }

  return {
    authenticated: true,
    userId: 1,
    type: 'etudiants',
    username: jeton.replace(/^mock\./, ''),
  }
})
