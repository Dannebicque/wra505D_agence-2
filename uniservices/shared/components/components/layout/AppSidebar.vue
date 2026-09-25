<script setup>
import { computed } from 'vue';
import AppMenu from './AppMenu.vue';
import { relayerSurvol, useLayout } from './composables/layout.js';

const props = defineProps({
    menuItems: {
        type: Array,
        required: true
    }
});

const { layoutState, onMenuToggle } = useLayout();

const replie = computed(() => layoutState.staticMenuDesktopInactive);
const libelleBouton = computed(() => (replie.value ? 'Déplier le menu' : 'Réduire le menu'));
</script>

<template>
    <nav id="menu-principal" class="layout-sidebar" aria-label="Menu principal">
        <app-menu :model="menuItems"></app-menu>
        <button
            type="button"
            class="layout-sidebar-toggle"
            :aria-expanded="replie ? 'false' : 'true'"
            aria-controls="menu-principal"
            v-tooltip.right="{ value: libelleBouton, disabled: !replie }"
            @click="onMenuToggle"
            @focus="relayerSurvol($event, 'mouseenter')"
            @blur="relayerSurvol($event, 'mouseleave')"
        >
            <!-- Ni PrimeIcons ni Heroicons n'ont d'icône de panneau latéral : la colonne pleine dit que le menu est ouvert. -->
            <svg class="layout-menuitem-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
                <rect x="3" y="4.5" width="18" height="15" rx="2"></rect>
                <path v-if="!replie" d="M5 4.5h4v15H5a2 2 0 0 1-2-2v-11a2 2 0 0 1 2-2Z" fill="currentColor" stroke="none"></path>
                <line v-else x1="9" y1="4.5" x2="9" y2="19.5"></line>
            </svg>
            <span class="layout-menuitem-text">{{ libelleBouton }}</span>
        </button>
    </nav>
</template>

<style lang="scss" scoped></style>
