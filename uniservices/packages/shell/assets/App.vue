<script setup>
import { initializeToast } from '@helpers/toast.js'
import Toast from 'primevue/toast'
import AppSkipLink from '@components/components/layout/AppSkipLink.vue'

initializeToast()
</script>

<template>
  <AppSkipLink />
  <Toast />
  <ConfirmDialog></ConfirmDialog>
  <router-view />
</template>

<style scoped></style>
