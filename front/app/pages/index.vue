<script setup lang="ts">
import type {
  DonneesEmploiDuTemps,
  DonneesProgressionPortfolio,
  DonneesQuestionnairesEnAttente,
} from '~~/types/domain'

const api = useApi()

useHead({ title: 'Portail' })

const { data: contexte } = await useAsyncData('contexte', () => api.contexteSecurite())

const { data: widgets } = await useAsyncData('widgets-portail', async () => {
  const { widgets } = await api.widgetsDuTableauDeBord('portail')

  // `position` a null signale un widget disponible mais non place par l'etudiant.
  return widgets
    .filter((widget) => widget.position !== null)
    .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
})

const { data: donnees } = await useAsyncData('donnees-widgets', async () => {
  const codes = (widgets.value ?? []).map((widget) => widget.code)
  const resultats = await Promise.all(codes.map((code) => api.donneesWidget<unknown>(code)))

  return Object.fromEntries(codes.map((code, index) => [code, resultats[index]]))
})
</script>

<template>
  <main class="portail">
    <h1>Portail</h1>
    <p v-if="contexte" class="bonjour">Bonjour, {{ contexte.user.prenom }}</p>

    <h2>Mon tableau de bord</h2>

    <div v-if="widgets?.length" class="grille">
      <AppWidget
        v-for="widget in widgets"
        :key="widget.key"
        :titre="widget.label"
        :colonnes="widget.colSpan"
      >
        <WidgetEmploiDuTemps
          v-if="widget.code === 'intranet.emploi_du_temps'"
          :donnees="donnees?.[widget.code] as DonneesEmploiDuTemps"
        />
        <WidgetProgressionPortfolio
          v-else-if="widget.code === 'portfolio.progress'"
          :donnees="donnees?.[widget.code] as DonneesProgressionPortfolio"
        />
        <WidgetQuestionnairesEnAttente
          v-else-if="widget.code === 'questionnaire.pending'"
          :donnees="donnees?.[widget.code] as DonneesQuestionnairesEnAttente"
        />
      </AppWidget>
    </div>
    <p v-else>Aucun widget sur votre tableau de bord.</p>
  </main>
</template>

<style scoped>
.portail {
  max-width: 64rem;
  margin: 0 auto;
  padding: 2rem 1rem;
}

h1 {
  margin: 0;
}

.bonjour {
  margin: 0.25rem 0 2rem;
  color: var(--couleur-texte-attenue);
  font-size: 1.125rem;
}

h2 {
  font-size: 1.25rem;
  margin: 0 0 1rem;
}

.grille {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  align-items: start;
}

@media (max-width: 48rem) {
  .grille {
    grid-template-columns: 1fr;
  }
}
</style>
