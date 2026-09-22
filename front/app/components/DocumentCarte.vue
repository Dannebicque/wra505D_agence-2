<script setup lang="ts">
import type { Document } from '~~/types/domain'

defineProps<{ document: Document }>()
</script>

<template>
  <li class="carte">
    <h3>{{ document.titre }}</h3>
    <p v-if="document.description" class="description">{{ document.description }}</p>

    <!--
      dl plutot qu'une suite de spans : chaque valeur garde son intitule, y compris
      pour un lecteur d'ecran qui parcourt la carte hors contexte.
    -->
    <dl>
      <div>
        <dt>Categorie</dt>
        <dd>{{ document.category.libelle }}</dd>
      </div>
      <div>
        <dt>Depose le</dt>
        <dd>
          <time :datetime="document.createdAt">{{ formaterDate(document.createdAt) }}</time>
        </dd>
      </div>
      <div>
        <dt>Format</dt>
        <dd>{{ document.type }}, {{ formaterTaille(document.fileSize) }}</dd>
      </div>
    </dl>
  </li>
</template>

<style scoped>
.carte {
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  border-radius: var(--rayon);
  padding: 1rem;
}

h3 {
  margin: 0;
  font-size: 1rem;
}

.description {
  margin: 0.25rem 0 0.75rem;
  color: var(--couleur-texte-attenue);
}

dl {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 1.5rem;
  margin: 0;
}

dl > div {
  display: flex;
  gap: 0.375rem;
}

dt {
  color: var(--couleur-texte-attenue);
}

dt::after {
  content: ' :';
}

dd {
  margin: 0;
}
</style>
