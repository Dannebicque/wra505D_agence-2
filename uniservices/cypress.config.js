const { defineConfig } = require('cypress');

module.exports = defineConfig({
    e2e: {
        baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost:3000',
        specPattern: 'cypress/e2e/**/*.cy.js',
        supportFile: 'cypress/support/e2e.js',
        // Sur le runner GitHub, un Vite froid dépasse souvent les 4 s par défaut : recherche et
        // notifications y ont échoué au hasard, alors qu'elles passent en 3 à 5 s en local.
        defaultCommandTimeout: 10000,
        video: false,
        screenshotsFolder: 'cypress/reports/screenshots',
    },
});
