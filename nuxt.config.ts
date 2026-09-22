export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: ['@nuxt/eslint', '@pinia/nuxt'],

  typescript: {
    strict: true,
    typeCheck: false,
  },

  css: ['~/assets/styles/tokens.css'],

  runtimeConfig: {
    public: {
      // Vide : les requetes partent sur le Nitro local, qui simule l'API reelle.
      // Basculer sur l'URL de uniServices (http://127.0.0.1:8000) suffit a passer
      // sur la vraie API, les chemins et les formats de reponse etant identiques.
      apiBase: '',
    },
  },

  eslint: {
    config: {
      stylistic: false,
    },
  },
})
