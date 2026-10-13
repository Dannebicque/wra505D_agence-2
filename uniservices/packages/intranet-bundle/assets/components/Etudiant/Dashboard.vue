<script setup>
import {computed, onMounted, ref} from 'vue';
import {VueDraggableNext as draggable} from 'vue-draggable-next';
import {useRouter} from 'vue-router';
import {useUsersStore, useAnneeUnivStore} from '@stores';
import {getWidgetsCatalogService, getWidgetDataByCodeService, updateDashboardWidgetLayoutService} from '@requests';
import {WidgetCard, GlobalLoader} from '@components';
import { formatDateLong } from "@helpers/date";
import MaintenantWidget from "@/components/Etudiant/dashboard/MaintenantWidget.vue";

const router = useRouter();
const userStore = useUsersStore();
const anneeUnivStore = useAnneeUnivStore();
const selectedAnneeUniversitaireId = computed(() => anneeUnivStore.selectedAnneeUniv?.id ?? null);
const widgets = ref([]);
const isLoadingWidgets = ref(false);
const widgetData = ref({});
const date = new Date();

const structureDepartementPersonnelId = computed(() => userStore.departementDefaut?.departementPersonnel?.id || null);

onMounted(() => {
  getDashboardWidgets();
});

const getDashboardWidgets = async () => {
  isLoadingWidgets.value = true;
  const params = {
    dashboardCode: 'intranet',
    structureDepartementPersonnelId: structureDepartementPersonnelId.value,
  };
  const response = await getWidgetsCatalogService(params);
  widgets.value = [...(response.widgets || [])].sort((a, b) => a.position - b.position);
  await Promise.all(
  widgets.value.map(({ code }) =>
  loadWidgetData(code)
  )
  );
  isLoadingWidgets.value = false;
}

const loadWidgetData = async (code) => {
  try {
    widgetData.value[code] = await getWidgetDataByCodeService(code);
  } catch {
    widgetData.value[code] = {message: 'Aucune donnée disponible'};
  }
};

const toggleWidget = async (widget) => {
  widget.enabled = !widget.enabled;
  await updateDashboardWidgetLayoutService(
    widget.code,
    { enabled: widget.enabled },
    paramsLayout()
  );
  await getDashboardWidgets();
};

const paramsLayout = () => ({
  dashboardCode: 'intranet',
  structureDepartementPersonnelId: structureDepartementPersonnelId.value,
});

// Une seule requête par déplacement : le serveur renumérote les autres. En envoyer une par widget,
// en parallèle, créait un jeu de préférences par requête et dupliquait les widgets.
const enregistrerPosition = async (widget, position) => {
  widgets.value.forEach((item, index) => {
    item.position = index;
  });
  try {
    await updateDashboardWidgetLayoutService(widget.code, { position }, paramsLayout());
  } catch {
    await getDashboardWidgets();
  }
};

// Flèches : la même opération que le glisser-déposer, pour qui n'utilise pas la souris.
const moveWidget = async (widget, direction) => {
  const index = widgets.value.findIndex((item) => item.code === widget.code);
  const targetIndex = index + direction;
  if (index === -1 || targetIndex < 0 || targetIndex >= widgets.value.length) {
    return;
  }

  widgets.value.splice(targetIndex, 0, widgets.value.splice(index, 1)[0]);
  await enregistrerPosition(widget, targetIndex);
};

// Le composant a déjà réordonné la liste quand le glisser se termine.
const finGlisser = async ({ newIndex, oldIndex }) => {
  if (newIndex === oldIndex) {
    return;
  }
  await enregistrerPosition(widgets.value[newIndex], newIndex);
};
</script>

<template>
  <section class="col-span-12 lg:col-span-10 pb-14">
    <div>
      <div v-if="!userStore.isLoading" class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex items-center">
          <div class="w-20 h-20 bg-primary-400 rounded-full flex items-center justify-center shrink-0">
            <template v-if="userStore.userPhoto">
              <img :src="userStore.userPhoto" alt="photo de profil" class="rounded-full" />
            </template>
            <template v-else>
              <span class="text-gray-700 text-xl">{{ initiales }}</span>
            </template>
          </div>
          <div class="ml-4">
            <h2 class="text-2xl! mb-0! font-bold flex items-center gap-2">
              <span class="font-light">Bonjour,</span> {{ userStore.user.prenom }}
            </h2>
            <small class="text-muted-color">{{ formatDateLong(date) }}</small>
          </div>
        </div>
        <div class="card flex justify-between items-center gap-6 m-0! p-4! max-md:w-full">
          <div>
            <div class="text-xl font-semibold">Mon dashboard</div>
            <div class="text-sm text-color-secondary">Personnalisez vos widgets.</div>
          </div>
          <Button @click="router.push({name: 'IntranetDashboardWidgetsConfig'})" icon="pi pi-cog" label="Configurer" size="small"/>
        </div>
      </div>
      <MaintenantWidget/>
      <GlobalLoader v-if="isLoadingWidgets" text="Chargement des widgets..."/>
      <draggable
          v-else
          v-model="widgets"
          tag="div"
          class="dashboard-grid"
          handle=".poignee-widget"
          ghost-class="widget-fantome"
          :animation="150"
          @end="finGlisser"
      >
        <WidgetCard
        v-for="(widget, index) in widgets"
        :key="widget.code"
        :widget="widget"
        :data="widgetData[widget.code]"
        :first="index === 0"
        :last="index === widgets.length - 1"
        reorderable
        :resizable="false"
        @move="moveWidget"
        @toggle="toggleWidget"
        />
      </draggable>
    </div>
  </section>
</template>

<style scoped>
.widget-fantome {
  opacity: 0.4;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

/* Sur un téléphone, quatre colonnes écraseraient les widgets : un par ligne, à pleine largeur.
   Chaque widget porte sa largeur en style en ligne, d'où le !important. */
@media (max-width: 767px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-grid > :deep(*) {
    grid-column: auto !important;
  }
}
</style>
