<script setup lang="ts">
withDefaults(defineProps<{ ton?: 'danger' | 'succes' }>(), { ton: 'danger' })
</script>

<template>
  <!--
    role="alert" pour que l'echec de connexion soit annonce sans que l'utilisateur
    ait a retrouver le message a l'ecran.
  -->
  <p role="alert" :class="ton">
    <slot />
  </p>
</template>

<style scoped>
p {
  margin: 0;
  padding: 0.75rem;
  border-radius: var(--rayon);
  border: 1px solid;
  font-weight: 500;
}

.danger {
  background: var(--couleur-danger);
  color: var(--couleur-sur-danger);
  border-color: var(--couleur-sur-danger);
}

.succes {
  background: var(--couleur-succes);
  color: var(--couleur-sur-succes);
  border-color: var(--couleur-sur-succes);
}
</style>
