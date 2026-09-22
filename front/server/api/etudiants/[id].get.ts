import { fiche } from '../../data/etudiant'

export default defineEventHandler((event) => {
  const id = Number(getRouterParam(event, 'id'))

  if (id !== fiche.id) {
    throw createError({ statusCode: 404, statusMessage: 'Not Found' })
  }

  return fiche
})
