<script setup>
import AppMenu from './AppMenu.vue';

const props = defineProps({
    menuItems: {
        type: Array,
        required: true
    }
});
</script>

<template>
    <nav id="menu-principal" class="layout-sidebar" aria-label="Menu principal">
        <app-menu :model="menuItems"></app-menu>
    </nav>
</template>

<style lang="scss" scoped></style>
