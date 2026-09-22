import { donneesWidgets } from '../../../data/portail'

export default defineEventHandler((event) => {
  const code = getRouterParam(event, 'code')
  const donnees = code ? donneesWidgets[code] : undefined

  if (!donnees) {
    throw createError({ statusCode: 404, statusMessage: 'Widget not found' })
  }

  return donnees
})
