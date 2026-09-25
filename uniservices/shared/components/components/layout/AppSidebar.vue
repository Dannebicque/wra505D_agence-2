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
            <i :class="replie ? 'pi pi-angle-double-right' : 'pi pi-angle-double-left'" class="layout-menuitem-icon" aria-hidden="true"></i>
            <span class="layout-menuitem-text">{{ libelleBouton }}</span>
        </button>
    </nav>
</template>

<style lang="scss" scoped></style>
