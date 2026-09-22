export default defineEventHandler((event) => {
  deleteCookie(event, 'BEARER', { path: '/' })
  setResponseStatus(event, 204)
  return null
})
