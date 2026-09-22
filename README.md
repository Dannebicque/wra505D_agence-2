# Agence 2

Dépôt de l'agence. On ne repart pas de zéro : la base de travail est le projet du client,
[IUTTroyes/uniServices](https://github.com/IUTTroyes/uniServices), reprise ici et amenée à évoluer.

```
.
├── uniservices/   base reprise du client : API Symfony 7 + API Platform,
│                  et leur front Vue/Vite qui sert de référence fonctionnelle
├── front/         notre front Nuxt 4, qui remplacera progressivement le leur
├── docs/          contexte, audit de l'existant, questions client
└── CLAUDE.md      règles de travail : stack, GitFlow, conventions, accessibilité
```

Cible : **Nuxt en front, Symfony en back.** `uniservices/packages/*/assets` et `uniservices/shared`
sont leur interface Vue ; on s'en sert comme référence de comportement, elle n'est pas la cible.

Les données sont celles des fixtures, pas celles de la production. On ne touche ni à `intranetV3`,
ni à aucune donnée réelle.

## Prérequis

| Outil | Version | Pour |
|---|---|---|
| Node | 20 ou plus | les deux fronts |
| pnpm | 10.9 ou plus | les deux fronts |
| PHP | 8.2 ou plus | l'API |
| Composer | 2 | l'API |
| CLI Symfony | 5 ou plus | le serveur de développement de l'API |
| Docker | en marche | base MariaDB et serveur de mail |

`npm install` échoue sur le front Nuxt : npm 11.1.0 plante en résolvant les dépendances de pair de
`@nuxt/test-utils`. Le projet utilise pnpm, épinglé dans `package.json`. Si tu ne l'as pas :

```bash
corepack enable && corepack prepare pnpm@10.9.0 --activate
```

## Installation

```bash
git clone git@github.com:Dannebicque/wra505D_agence-2.git && cd wra505D_agence-2
```

### 1. L'API Symfony

Crée `uniservices/.env.local` :

```bash
MYSQL_ROOT_PASSWORD=root
MYSQL_DATABASE=uniservices
MYSQL_USER=uniservices
MYSQL_PASSWORD=uniservices
WEB_PORT=8080
DB_PORT=3307
PHPMYADMIN_PORT=8081
MAILDEV_PORT=1080
VITE_BASE_URL="http://127.0.0.1:8000"
VITE_API_URL="http://127.0.0.1:8000"
```

et `uniservices/back/.env.local` :

```bash
DATABASE_URL="mysql://uniservices:uniservices@127.0.0.1:3307/uniservices?serverVersion=10.8.0-MariaDB&charset=utf8mb4"
MAILER_DSN=smtp://127.0.0.1:1025
```

Ces deux fichiers ne sont pas versionnés, chacun les crée chez soi. Puis :

```bash
cd uniservices && docker compose -f docker/docker-compose.yml --env-file .env.local up -d db maildev && pnpm install
cd back && composer install && php bin/console lexik:jwt:generate-keypair --skip-if-exists && php bin/console doctrine:schema:create && php bin/console doctrine:fixtures:load --no-interaction
```

Il n'y a pas de dossier `migrations/` : le schéma se crée depuis les entités. Les clés JWT et
`vendor/` ne sont pas versionnés non plus, d'où les deux commandes ci-dessus.

### 2. Le front Nuxt

```bash
cd front && pnpm install
```

## Lancer

Trois services, trois ports, ils peuvent tourner ensemble :

| Commande | Depuis | Adresse |
|---|---|---|
| `symfony server:start -d --port=8000` | `uniservices/back` | <http://127.0.0.1:8000/api> |
| `pnpm run dev` | `uniservices` | <http://localhost:3000/app/> |
| `pnpm dev` | `front` | <http://localhost:3100> |

Comptes chargés par les fixtures, mot de passe `test` : `etudiant`, `personnel`, `superadmin`. Ils
se saisissent dans le bloc « Compte invité », pas via « Connexion URCA » qui pointe vers le CAS de
l'université.

Le mot de passe doit être tapé au clavier : PrimeVue ignore une valeur injectée et le bouton reste
inactif.

## Commandes du front Nuxt

Depuis `front/` :

| Commande | Effet |
|---|---|
| `pnpm dev` | serveur de développement |
| `pnpm build` | build de production |
| `pnpm lint` / `pnpm lint:fix` | ESLint |
| `pnpm typecheck` | vérification TypeScript |
| `pnpm test` | tests unitaires Vitest |
| `pnpm test:e2e` | tests Cypress, application à lancer avant |

Une tâche n'est terminée que si `lint`, `typecheck`, `test` et `build` passent tous les quatre.

## Comment les données arrivent au front Nuxt

Aucun composant n'appelle `$fetch`. Tout passe par `front/app/composables/useApi.ts`, couche
d'accès unique.

Par défaut les requêtes partent sur le serveur Nitro local (`front/server/api/`), qui rejoue le
contrat de l'API : mêmes chemins, même enveloppe Hydra, même authentification par cookie httpOnly.
C'est ce qui permet de travailler sans lancer Symfony.

Règle en ajoutant un point d'entrée simulé : **le mock reste un sous-ensemble strict du contrat
réel.** Un champ absent chez nous mais présent dans l'API ne casse rien ; l'inverse casserait. Ne
sers jamais un champ absent de leur schéma OpenAPI, que tu peux exporter avec :

```bash
cd uniservices/back && php bin/console api:openapi:export
```

Pour taper sur la vraie API plutôt que sur les données simulées :

```bash
cd front && NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000 pnpm dev
```

## Commandes de la base reprise

Depuis `uniservices/`, leur `Makefile` pilote l'ensemble : `make check` rejoue les validations de
leur CI, `make phpstan`, `make test-back`, `make build-front`.
