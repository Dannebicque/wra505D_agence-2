<script setup>
import {computed, onMounted, ref} from 'vue';
import {useUsersStore} from '@stores';
import {getWidgetsAvailableService, getWidgetDataByCodeService, updateDashboardWidgetLayoutService} from '@requests';
import {WidgetCard} from '@components';
import { formatDateLong } from "@helpers/date";
import { showDanger } from '@helpers/toast.js';
import EdtEtudiant from '@/components/Edt/EdtEtudiant.vue';
import MaintenantWidget from "@/components/Etudiant/dashboard/MaintenantWidget.vue";

// Les blocs que l'étudiant peut masquer, par ordre d'importance. Sa journée n'en fait pas partie :
// elle est fixe, en tête de page.
const BLOCS = ['intranet.notes', 'intranet.actualites', 'intranet.actualites_iut', 'intranet.contacts'];

const userStore = useUsersStore();
const widgets = ref([]);
const widgetData = ref({});
const isLoadingWidgets = ref(true);
const erreurBlocs = ref(false);
const enregistrement = ref({});
const panneau = ref(false);
const boutonPersonnaliser = ref(null);
const coursDuJour = ref(null);
const erreurEdt = ref(false);
const date = new Date();

const blocs = computed(() => widgets.value
    .filter(widget => BLOCS.includes(widget.code))
    .sort((a, b) => BLOCS.indexOf(a.code) - BLOCS.indexOf(b.code)));
const blocsAffiches = computed(() => blocs.value.filter(widget => widget.enabled));

onMounted(async () => {
  try {
    const response = await getWidgetsAvailableService('intranet');
    widgets.value = response.widgets || [];
    await Promise.all(blocsAffiches.value.map(({ code }) => loadWidgetData(code)));
  } catch {
    erreurBlocs.value = true;
  } finally {
    isLoadingWidgets.value = false;
  }
});

const loadWidgetData = async (code) => {
  try {
    widgetData.value[code] = await getWidgetDataByCodeService(code);
  } catch {
    widgetData.value[code] = {message: 'Aucune donnée disponible'};
  }
};

// Le bloc n'apparaît qu'une fois enregistré et chargé : l'interrupteur ne ment pas si l'API refuse.
const afficherBloc = async (widget, affiche) => {
  enregistrement.value[widget.code] = true;
  try {
    await updateDashboardWidgetLayoutService(widget.code, { enabled: affiche }, { dashboardCode: 'intranet' });
    if (affiche && !widgetData.value[widget.code]) {
      await loadWidgetData(widget.code);
    }
    widget.enabled = affiche;
  } catch {
    showDanger(`« ${widget.label} » n'a pas pu être ${affiche ? 'affiché' : 'masqué'}. Réessayez dans un instant.`);
  } finally {
    enregistrement.value[widget.code] = false;
  }
};
</script>

<template>
  <section class="col-span-12 lg:col-span-10 pb-14">
    <div class="flex items-center justify-between gap-4 mb-3">
      <h1 class="text-2xl! font-bold m-0!">Accueil</h1>
      <Button
          ref="boutonPersonnaliser"
          label="Personnaliser"
          icon="pi pi-sliders-h"
          text
          aria-haspopup="dialog"
          class="min-h-[44px]!"
          @click="panneau = true"
      />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
      <section aria-labelledby="accueil-bonjour" class="card m-0! p-4! lg:p-6! lg:col-span-2 flex flex-col gap-4">
        <div>
          <h2 id="accueil-bonjour" class="text-xl! font-bold m-0!">
            <span class="font-light">Bonjour,</span> {{ userStore.user?.prenom }}
          </h2>
          <p class="m-0! text-muted-color">{{ formatDateLong(date) }}</p>
        </div>
        <MaintenantWidget v-if="!erreurEdt" :cours="coursDuJour"/>
        <EdtEtudiant aujourdhui @cours-du-jour="cours => coursDuJour = cours" @erreur="erreurEdt = true"/>
        <Button as="router-link" to="/intranet/agenda" label="Voir l'emploi du temps" severity="secondary" size="small" class="w-full min-h-[44px]!"/>
      </section>

      <div class="accueil-blocs flex flex-col gap-4">
        <p v-if="erreurBlocs" class="card m-0! p-4! text-muted-color">Les autres blocs de l'accueil sont indisponibles pour le moment.</p>
        <template v-else-if="isLoadingWidgets">
          <Skeleton height="12rem" border-radius="1rem"/>
          <Skeleton height="8rem" border-radius="1rem"/>
        </template>
        <WidgetCard
            v-for="widget in blocsAffiches"
            v-else
            :key="widget.code"
            :widget="widget"
            :data="widgetData[widget.code] ?? {}"
            :configurable="false"
        />
      </div>
    </div>

    <Drawer
        v-model:visible="panneau"
        position="right"
        header="Personnaliser l'accueil"
        class="w-full! sm:w-[24rem]!"
        @after-hide="boutonPersonnaliser.$el.focus()"
    >
      <p class="mt-0! text-muted-color">Votre journée reste en tête de page. Choisissez les autres blocs à afficher.</p>
      <ul class="list-none p-0 m-0">
        <li v-for="widget in blocs" :key="widget.code" class="flex items-center justify-between gap-4 py-3 border-b border-surface last:border-b-0">
          <label :for="`bloc-${widget.code}`" class="flex items-center gap-2 cursor-pointer">
            <i :class="`${widget.icon} text-primary-500`" aria-hidden="true"/>
            {{ widget.label }}
          </label>
          <ToggleSwitch
              :input-id="`bloc-${widget.code}`"
              :model-value="widget.enabled"
              :disabled="enregistrement[widget.code]"
              @update:model-value="affiche => afficherBloc(widget, affiche)"
          />
        </li>
      </ul>
    </Drawer>
  </section>
</template>
