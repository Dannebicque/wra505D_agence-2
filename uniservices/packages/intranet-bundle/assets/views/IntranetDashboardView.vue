<script setup>
import { PermissionGuard } from "@components";
import { useUsersStore } from "@stores";
import DashboardPersonnel from "@/views/DashboardView.vue";
import DashboardEtudiant from "@/components/Etudiant/Dashboard.vue";

const userStore = useUsersStore();
</script>

<template>
  <div>
    <!-- Le tableau de bord du personnel est celui du client ; l'accueil de l'étudiant reste le nôtre (C18, C19). -->
    <PermissionGuard permission="isPersonnel">
      <DashboardPersonnel v-if="userStore.isPersonnel"/>
    </PermissionGuard>
    <PermissionGuard permission="isEtudiant">
      <DashboardEtudiant v-if="userStore.isEtudiant" />
    </PermissionGuard>
  </div>
</template>
