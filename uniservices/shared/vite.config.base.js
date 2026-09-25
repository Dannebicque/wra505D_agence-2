import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import fs from "fs";
import path from "path";
import Components from "unplugin-vue-components/vite";
import { PrimeVueResolver } from "@primevue/auto-import-resolver";
import tailwindcss from "@tailwindcss/vite";
import { loadEnv } from "vite";

/**
 * Tous les composants de PrimeVue, sous leur chemin d'import (« primevue/accordion »).
 *
 * unplugin-vue-components n'ajoute leurs imports qu'à la compilation : l'analyse de Vite ne les
 * voit pas au démarrage. La première page qui utilise un composant encore inconnu le fait
 * pré-optimiser, et Vite recharge alors toute la page, en perdant la navigation en cours. On les
 * pré-optimise donc d'emblée. Seul le serveur de développement est concerné, pas le build.
 */
function composantsPrimeVue(rootDir) {
  const modules = path.resolve(rootDir, "node_modules");
  const racine = path.join(modules, "primevue");
  if (!fs.existsSync(racine)) {
    return [];
  }

  // Editor charge quill à la demande, que le projet n'installe pas : un composant dont un
  // chargement à la demande vise un paquet absent ne peut pas être pré-optimisé.
  const chargeUnPaquetAbsent = (fichier) => [...fs.readFileSync(fichier, "utf8").matchAll(/import\(\s*["']([^"'.][^"']*)["']\s*\)/g)]
    .some(([, paquet]) => !fs.existsSync(path.join(modules, paquet)));

  return fs.readdirSync(racine, { withFileTypes: true })
    .filter((entree) => entree.isDirectory())
    .map((entree) => ({ nom: entree.name, fichier: path.join(racine, entree.name, "index.mjs") }))
    .filter(({ fichier }) => fs.existsSync(fichier) && !chargeUnPaquetAbsent(fichier))
    .map(({ nom }) => `primevue/${nom}`);
}

/**
 * Returns a Vite configuration customized for a bundle.
 * 
 * @param {string} bundleDir - The __dirname of the bundle calling this
 * @param {string} baseName - The URL base path / directory name in public (e.g. 'auth', 'questionnaire')
 * @param {object} [customConfig] - Optional custom config overrides
 */
export function getBaseConfig(bundleDir, baseName, customConfig = {}) {
  return defineConfig(({ mode }) => {
    const rootDir = path.resolve(bundleDir, "../../");
    const env = loadEnv(mode, rootDir, "");

    const apiUrl = env.VITE_API_URL || env.VITE_BASE_URL || "https://localhost:8000";
    process.env.VITE_API_URL = apiUrl;

    const baseConfig = {
      plugins: [
        vue(),
        tailwindcss(),
        Components({
          resolvers: [PrimeVueResolver()],
          dts: path.resolve(bundleDir, "assets/components.d.ts"),
        }),
      ],
      optimizeDeps: {
        include: composantsPrimeVue(rootDir),
      },
      root: path.resolve(bundleDir, "assets"),
      base: `/${baseName}/`,
      build: {
        outDir: path.resolve(rootDir, `back/public/${baseName}`),
        emptyOutDir: true,
      },
      server: {
        // unplugin-vue-components réécrit ce fichier dès qu'il découvre un composant : surveillé, il
        // faisait recharger toute la page, en perdant la navigation en cours.
        watch: {
          ignored: ["**/components.d.ts"],
        },
        proxy: {
          "/api": {
            target: apiUrl,
            changeOrigin: true,
            secure: false,
          },
        },
      },
      resolve: {
        alias: {
          "@": path.resolve(bundleDir, "assets"),
          "@types": path.resolve(rootDir, "shared/types"),
          "@components": path.resolve(rootDir, "shared/components"),
          "@config": path.resolve(rootDir, "shared/global-data"),
          "@styles": path.resolve(rootDir, "shared/styles"),
          "@images": path.resolve(rootDir, "shared/images"),
          "@helpers": path.resolve(rootDir, "shared/helpers"),
          "@requests": path.resolve(rootDir, "shared/requests"),
          "@stores": path.resolve(rootDir, "shared/stores"),
          "@utils": path.resolve(rootDir, "shared/utils"),
          "@composables": path.resolve(rootDir, "shared/composables"),
          "@dialogs": path.resolve(rootDir, "shared/dialogs"),
          "@common-images": path.resolve(rootDir, "shared/images"),
        },
      },
    };

    // Merge plugins
    const mergedPlugins = [
      ...baseConfig.plugins,
      ...(customConfig.plugins || []),
    ];

    // Merge aliases (supporting both array and object formats)
    let mergedAlias;
    if (Array.isArray(customConfig.resolve?.alias)) {
      // If custom is an array, compile base alias as an array and merge
      const baseAliasArray = Object.entries(baseConfig.resolve.alias).map(([find, replacement]) => ({ find, replacement }));
      mergedAlias = [...customConfig.resolve.alias];
      // Add base aliases that are not overridden by custom aliases
      baseAliasArray.forEach(baseAlias => {
        if (!mergedAlias.some(a => String(a.find) === String(baseAlias.find))) {
          mergedAlias.push(baseAlias);
        }
      });
    } else {
      // Standard object merge
      mergedAlias = {
        ...baseConfig.resolve.alias,
        ...(customConfig.resolve?.alias || {}),
      };
    }

    // Return merged config
    return {
      ...baseConfig,
      ...customConfig,
      plugins: mergedPlugins,
      resolve: {
        ...baseConfig.resolve,
        ...customConfig.resolve,
        alias: mergedAlias,
      },
      build: {
        ...baseConfig.build,
        ...customConfig.build,
      },
      server: {
        ...baseConfig.server,
        ...customConfig.server,
        proxy: {
          ...baseConfig.server.proxy,
          ...(customConfig.server?.proxy || {}),
        },
      },
    };
  });
}
