/*
 * Toute page hors connexion exige une session. La verification passe par la couche
 * d'acces unique, donc elle vaudra aussi bien contre l'API reelle que contre les
 * donnees simulees.
 */
export default defineNuxtRouteMiddleware(async (vers) => {
  if (vers.path === '/connexion') return

  const { authenticated } = await useApi().session()
  if (!authenticated) {
    return navigateTo('/connexion')
  }
})
