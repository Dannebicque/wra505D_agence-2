/*
 * L'API reelle repond 204 et depose le JWT dans un cookie httpOnly. Le mock fait
 * pareil, avec un jeton opaque : le front n'a jamais a lire ce cookie.
 */
export default defineEventHandler(async (event) => {
  const { username, password } = await readBody<{ username?: string; password?: string }>(event)

  if (!username || !password) {
    throw createError({ statusCode: 400, statusMessage: 'Identifiants manquants' })
  }

  setCookie(event, 'BEARER', `mock.${username}`, {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    maxAge: 900,
  })

  setResponseStatus(event, 204)
  return null
})
