<script setup lang="ts">
import type { OptionSelect } from '~/components/AppChampSelect.vue'

const api = useApi()
const route = useRoute()
const router = useRouter()

useHead({ title: 'Documents' })

const { data: documents } = await useAsyncData('documents', async () => {
  const { member } = await api.documents()
  return member
})

const { data: categories } = await useAsyncData('categories-documents', async () => {
  const { member } = await api.categoriesDocument()
  return member
})

/*
 * Les filtres vivent dans l'URL : une vue filtree se partage, se met en favori et
 * survit a un rechargement. L'audit relevait que les categories de l'intranet
 * n'avaient aucune URL propre.
 */
function filtreUrl(nom: string, defaut = '') {
  return computed({
    get: () => (route.query[nom] as string | undefined) ?? defaut,
    set: (valeur: string) => {
      const query = Object.fromEntries(Object.entries(route.query).filter(([cle]) => cle !== nom))
      if (valeur !== defaut) query[nom] = valeur
      router.replace({ query })
    },
  })
}

const recherche = filtreUrl('q')
const categorie = filtreUrl('categorie')
const type = filtreUrl('type')
const tri = filtreUrl('tri', 'recent')

const optionsCategorie = computed<OptionSelect[]>(() => {
  const toutes = categories.value ?? []
  const parents = toutes.filter((c) => !c.parent)

  return [
    { valeur: '', libelle: 'Toutes les categories' },
    ...parents.flatMap((parent) => [
      { valeur: String(parent.id), libelle: parent.libelle },
      ...toutes
        .filter((enfant) => enfant.parent === parent['@id'])
        .map((enfant) => ({
          valeur: String(enfant.id),
          libelle: enfant.libelle,
          groupe: parent.libelle,
        })),
    ]),
  ]
})

const optionsType = computed<OptionSelect[]>(() => [
  { valeur: '', libelle: 'Tous les formats' },
  ...[...new Set((documents.value ?? []).map((document) => document.type))]
    .sort()
    .map((valeur) => ({ valeur, libelle: valeur })),
])

const optionsTri: OptionSelect[] = [
  { valeur: 'recent', libelle: 'Les plus recents' },
  { valeur: 'ancien', libelle: 'Les plus anciens' },
  { valeur: 'titre', libelle: 'Titre, de A a Z' },
]

const unFiltreEstActif = computed(
  () => Boolean(recherche.value) || Boolean(categorie.value) || Boolean(type.value),
)

const resultats = computed(() => {
  const terme = recherche.value.trim().toLowerCase()

  const filtres = (documents.value ?? []).filter((document) => {
    if (categorie.value && String(document.category.id) !== categorie.value) return false
    if (type.value && document.type !== type.value) return false
    if (!terme) return true

    return (
      document.titre.toLowerCase().includes(terme) ||
      (document.description ?? '').toLowerCase().includes(terme) ||
      document.category.libelle.toLowerCase().includes(terme)
    )
  })

  return [...filtres].sort((a, b) => {
    if (tri.value === 'titre') return a.titre.localeCompare(b.titre, 'fr')
    const ecart = Date.parse(a.createdAt) - Date.parse(b.createdAt)
    return tri.value === 'ancien' ? ecart : -ecart
  })
})

const recents = computed(() =>
  [...(documents.value ?? [])]
    .sort((a, b) => Date.parse(b.createdAt) - Date.parse(a.createdAt))
    .slice(0, 3),
)

function reinitialiser() {
  router.replace({ query: {} })
}
</script>

<template>
  <div class="documents">
    <h1>Documents</h1>

    <section v-if="!unFiltreEstActif && recents.length" aria-labelledby="titre-recents">
      <h2 id="titre-recents">Recemment ajoutes</h2>
      <ul class="liste">
        <DocumentCarte v-for="document in recents" :key="document['@id']" :document="document" />
      </ul>
    </section>

    <section aria-labelledby="titre-recherche">
      <h2 id="titre-recherche">Rechercher un document</h2>

      <div class="filtres">
        <AppChampTexte v-model="recherche" label="Mot cle" autocomplete="off" />
        <AppChampSelect v-model="categorie" label="Categorie" :options="optionsCategorie" />
        <AppChampSelect v-model="type" label="Format" :options="optionsType" />
        <AppChampSelect v-model="tri" label="Trier par" :options="optionsTri" />
      </div>

      <!--
        aria-live pour que le nombre de resultats soit annonce a chaque changement
        de filtre : sans cela, un utilisateur au lecteur d'ecran ne sait pas que la
        liste a bouge.
      -->
      <p class="compte" role="status" aria-live="polite">
        {{ resultats.length }}
        {{ resultats.length > 1 ? 'documents trouves' : 'document trouve' }}
        <template v-if="unFiltreEstActif"> sur {{ documents?.length ?? 0 }}</template>
      </p>

      <AppBouton v-if="unFiltreEstActif" variante="secondaire" @click="reinitialiser">
        Effacer les filtres
      </AppBouton>
    </section>

    <section aria-labelledby="titre-resultats">
      <h2 id="titre-resultats" class="invisible">Resultats</h2>

      <ul v-if="resultats.length" class="liste">
        <DocumentCarte v-for="document in resultats" :key="document['@id']" :document="document" />
      </ul>

      <p v-else-if="unFiltreEstActif">
        Aucun document ne correspond a ces filtres. Essayez un autre mot cle, ou effacez les
        filtres.
      </p>
      <p v-else>Aucun document n'est disponible pour le moment.</p>
    </section>
  </div>
</template>

<style scoped>
.documents {
  max-width: 64rem;
  margin: 0 auto;
  padding: 2rem 1rem;
}

h1 {
  margin: 0 0 1.5rem;
}

h2 {
  font-size: 1.25rem;
  margin: 2rem 0 1rem;
}

/* Le titre reste dans l'arbre d'accessibilite, il n'est que masque a l'ecran. */
.invisible {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.filtres {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: 1rem;
  align-items: end;
}

.compte {
  margin: 1rem 0;
  font-weight: 500;
}

.liste {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr));
  gap: 1rem;
}
</style>
