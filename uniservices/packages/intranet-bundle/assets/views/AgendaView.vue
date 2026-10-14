<script setup>
import {ref, markRaw, onMounted} from 'vue';
import EdtPersonnel from "@/components/Edt/EdtPersonnel.vue";
import EdtDepartement from "@/components/Edt/EdtDepartement.vue";
import EdtEtudiant from "../components/Edt/EdtEtudiant.vue";
import EdtStatistiques from "../components/Edt/EdtStatistiques.vue";
import {useUsersStore} from "@stores";
import {HeaderComponent} from '@components';

const store = useUsersStore();

const tabs = ref([]);

const description = 'Consultez votre emploi du temps, celui du département et les statistiques';

onMounted( () => {
  if (store.isPersonnel) {
    tabs.value =[
      { title: 'Personnel', component: markRaw(EdtPersonnel), value: '0' },
      { title: 'Département', component: markRaw(EdtDepartement), value: '1' },
      { title: 'Statistiques', component: markRaw(EdtStatistiques), value: '2' },
    ];
  }
  if (store.isEtudiant) {
    tabs.value =[
      { title: 'Personnel', component: markRaw(EdtEtudiant), value: '0' },
    ];
  }
});

</script>

<template>
  <!-- L'étudiant n'a que son agenda, qui doit tenir entier à l'écran : son titre reste pour les
       lecteurs d'écran, la barre de l'agenda dit déjà la période. -->
  <h1 v-if="store.isEtudiant" class="sr-only">Emploi du temps</h1>
  <HeaderComponent
      v-else
      icon="pi pi-calendar"
      titre="Emploi du temps"
      :description="description"
  />
  <!-- Sans fil d'Ariane, rien ne le sépare plus de la barre du haut, qui le recouvre en mobile. -->
  <div class="card" :class="{ 'mt-10 md:mt-0': store.isEtudiant }">
    <component v-if="tabs.length === 1" :is="tabs[0].component" />
    <Tabs v-else value="0">
      <TabList>
        <Tab v-for="tab in tabs" :key="tab.value" :value="tab.value">
          {{ tab.title }}
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel v-for="tab in tabs" :key="tab.value" :value="tab.value">
          <component :is="tab.component" />
        </TabPanel>
      </TabPanels>
    </Tabs>

  </div>
</template>
