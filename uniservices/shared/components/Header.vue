<script setup>
import { defineProps, computed } from "vue";
import { useUsersStore } from "@stores";

const props = defineProps({
  icon: {
    type: [String, Object, Function],
    default: null
  },
  titre: {
    type: String,
    required: true
  },
  description: {
    type: String,
    required: true
  },
  backUrl: {
    type: [String, Object],
    default: null
  },
  showBack: {
    type: Boolean,
    default: true
  },
  color: {
    type: String,
    default: ''
  },
  iconClass: {
    type: String,
    default: ''
  },
  iconBgClass: {
    type: String,
    default: ''
  }
});

const userStore = useUsersStore();

// L'étudiant retrouve le même menu et le fil d'Ariane sur chaque page : un « Retour » qui
// rejoue l'historique ne lui apprend rien, et depuis sa page d'arrivée il le renvoie à la connexion.
const afficherRetour = computed(() => props.showBack && !userStore.isEtudiant);

const colorMap = {
  primary: {
    bg: 'bg-primary-50 dark:bg-primary-950/20',
    text: 'text-primary dark:text-primary-300',
    border: 'border-primary-200 dark:border-primary-800'
  }
};

const colorClasses = computed(() => {
  if (!props.color) return null;
  return colorMap.primary;
});
</script>

<template>
  <div class="mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-[length:var(--ui-page-title-size)] font-bold text-color tracking-tight flex items-center gap-3 mb-0!">
        <span v-if="icon" :class="[
          colorClasses ? 'w-10 h-10 rounded-xl flex items-center justify-center border transition-all shrink-0' : '',
          colorClasses ? `${colorClasses.bg} ${colorClasses.text} ${colorClasses.border}` : '',
          iconBgClass
        ]">
          <!-- Support PrimeIcons string and Heroicons component -->
          <i v-if="typeof icon === 'string'" :class="[icon, colorClasses ? 'text-lg' : 'text-primary-500 text-2xl!', colorClasses ? '' : iconClass]" aria-hidden="true" />
          <component v-else :is="icon" :class="[colorClasses ? 'w-5 h-5' : 'w-7 h-7 text-primary-500', colorClasses ? '' : iconClass]" />
        </span>
          <span>{{ props.titre }}</span>
        </h1>
        <p class="text-muted-color mt-1" :class="[icon && colorClasses ? 'pl-[52px]' : '']">
          {{ props.description }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <Button v-if="afficherRetour" severity="secondary" text label="Retour" icon="pi pi-arrow-left" @click="props.backUrl ? $router.push(props.backUrl) : $router.go(-1)" />
      </div>
    </div>
    <template class="w-full flex justify-end items-center gap-2">
      <slot name="actions"/>
    </template>
  </div>
</template>

<style scoped>

</style>
