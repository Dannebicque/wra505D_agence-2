<script setup lang="ts">
export interface OptionSelect {
  valeur: string
  libelle: string
  groupe?: string
}

const valeur = defineModel<string>({ required: true })

const props = defineProps<{ label: string; options: OptionSelect[] }>()

const id = useId()

const groupes = computed(() => {
  const sansGroupe = props.options.filter((option) => !option.groupe)
  const noms = [...new Set(props.options.map((o) => o.groupe).filter(Boolean))] as string[]

  return {
    sansGroupe,
    groupes: noms.map((nom) => ({
      nom,
      options: props.options.filter((option) => option.groupe === nom),
    })),
  }
})
</script>

<template>
  <div class="champ">
    <label :for="id">{{ label }}</label>
    <select :id="id" v-model="valeur">
      <option v-for="option in groupes.sansGroupe" :key="option.valeur" :value="option.valeur">
        {{ option.libelle }}
      </option>
      <optgroup v-for="groupe in groupes.groupes" :key="groupe.nom" :label="groupe.nom">
        <option v-for="option in groupe.options" :key="option.valeur" :value="option.valeur">
          {{ option.libelle }}
        </option>
      </optgroup>
    </select>
  </div>
</template>

<style scoped>
.champ {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

label {
  font-weight: 500;
}

select {
  min-height: var(--cible-tactile);
  padding: 0 0.5rem;
  border: 1px solid var(--couleur-bordure);
  border-radius: var(--rayon);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
}
</style>
