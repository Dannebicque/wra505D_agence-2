<script setup>
import {computed} from 'vue';

// La ligne commune aux blocs de l'accueil : un repère, un libellé, un détail, une valeur. Un seul
// motif à apprendre pour l'œil (TB-14).
const props = defineProps({
  to: {type: [String, Object], default: null},
  // Lien extérieur, ouvert dans un nouvel onglet.
  href: {type: String, default: null},
  icone: {type: String, default: null},
  // « action » : ce que l'étudiant doit faire, seul usage du rouge sur l'accueil (TB-11).
  ton: {type: String, default: 'neutre'},
});

const balise = computed(() => {
  if (props.to) {
    return 'RouterLink';
  }
  return props.href ? 'a' : 'div';
});

// Seuls les attributs de la balise choisie : un href vide passé à RouterLink écraserait le sien.
const attributs = computed(() => {
  if (props.to) {
    return {to: props.to};
  }
  return props.href ? {href: props.href, target: '_blank', rel: 'noopener'} : {};
});
</script>

<template>
  <li :class="['ligne', `ligne--${ton}`]">
    <component
        :is="balise"
        v-bind="attributs"
        :class="['ligne__corps', { 'ligne__corps--lien': to || href }]"
    >
      <span v-if="icone" class="ligne__icone" aria-hidden="true"><i :class="icone"></i></span>
      <span class="ligne__texte">
        <span class="ligne__titre"><slot name="titre"/></span>
        <span v-if="$slots.detail" class="ligne__detail"><slot name="detail"/></span>
      </span>
      <span v-if="$slots.valeur" class="ligne__valeur"><slot name="valeur"/></span>
      <span v-if="href" class="sr-only">, nouvel onglet</span>
    </component>
  </li>
</template>

<style scoped>
.ligne {
  list-style: none;
}

.ligne__corps {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 44px;
  margin-inline: -0.5rem;
  padding: 0.375rem 0.5rem;
  border-radius: 12px;
  color: inherit;
}

.ligne__corps--lien:hover {
  background: var(--p-content-hover-background);
}

.ligne__corps--lien:hover .ligne__titre {
  text-decoration: underline;
}

.ligne__corps--lien:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 0;
}

.ligne__icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: 999px;
  font-size: 0.875rem;
  background: var(--p-content-hover-background);
  color: var(--p-text-muted-color);
}

.ligne__texte {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.ligne__titre {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ligne__detail {
  font-size: 0.9375rem;
  color: var(--p-text-muted-color);
}

.ligne__valeur {
  flex-shrink: 0;
  max-width: 45%;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.ligne--action .ligne__icone {
  background: var(--p-seance-evaluation-background);
  color: var(--p-seance-evaluation-color);
}

.ligne--action .ligne__titre {
  font-weight: 600;
  color: var(--p-seance-evaluation-color);
}
</style>
