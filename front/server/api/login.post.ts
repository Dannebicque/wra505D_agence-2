import { comptes } from '../data/comptes'

/*
 * L'API reelle repond 204 et depose le JWT dans un cookie httpOnly, ou 401 sur un
 * couple invalide. Le mock reprend les deux, avec un jeton opaque : le front n'a
 * jamais a lire ce cookie.
 */
export default defineEventHandler(async (event) => {
  const { username, password } = await readBody<{ username?: string; password?: string }>(event)

  const compte = comptes.find((c) => c.username === username)
  if (!compte || password !== compte.motDePasse) {
    throw createError({ statusCode: 401, statusMessage: 'Invalid credentials.' })
  }

  setCookie(event, 'BEARER', `mock.${compte.username}`, {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    maxAge: 900,
  })

  setResponseStatus(event, 204)
  return null
})
