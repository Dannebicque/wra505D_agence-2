<script setup lang="ts">
const valeur = defineModel<string>({ required: true })

const props = defineProps<{
  label: string
  type?: 'text' | 'password' | 'email'
  autocomplete?: string
  erreur?: string | null
  requis?: boolean
}>()

const id = useId()
const idErreur = `${id}-erreur`
const motDePasseVisible = ref(false)

const typeEffectif = computed(() => {
  if (props.type !== 'password') return props.type ?? 'text'
  return motDePasseVisible.value ? 'text' : 'password'
})
</script>

<template>
  <div class="champ">
    <label :for="id">
      {{ label }}
      <span v-if="requis" aria-hidden="true">*</span>
    </label>

    <div class="entree">
      <input
        :id="id"
        v-model="valeur"
        :type="typeEffectif"
        :autocomplete="autocomplete"
        :required="requis"
        :aria-invalid="erreur ? true : undefined"
        :aria-describedby="erreur ? idErreur : undefined"
      />
      <!--
        Le bouton d'affichage porte son etat dans aria-pressed plutot que de changer
        de libelle : un lecteur d'ecran annonce ainsi le changement sans que le nom
        accessible bouge sous le curseur de l'utilisateur.
      -->
      <button
        v-if="type === 'password'"
        type="button"
        class="bascule"
        :aria-pressed="motDePasseVisible"
        @click="motDePasseVisible = !motDePasseVisible"
      >
        Afficher le mot de passe
      </button>
    </div>

    <p v-if="erreur" :id="idErreur" class="erreur">{{ erreur }}</p>
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

.entree {
  display: flex;
  gap: 0.5rem;
}

input {
  flex: 1;
  min-height: var(--cible-tactile);
  padding: 0 0.75rem;
  border: 1px solid var(--couleur-bordure);
  border-radius: var(--rayon);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
}

input[aria-invalid='true'] {
  border-color: var(--couleur-sur-danger);
  border-width: 2px;
}

.bascule {
  min-height: var(--cible-tactile);
  min-width: var(--cible-tactile);
  padding: 0 0.75rem;
  border: 1px solid var(--couleur-bordure);
  border-radius: var(--rayon);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
  cursor: pointer;
}

.bascule[aria-pressed='true'] {
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  border-color: var(--couleur-sur-primaire);
}

.erreur {
  margin: 0;
  color: var(--couleur-sur-danger);
  font-weight: 500;
}
</style>
