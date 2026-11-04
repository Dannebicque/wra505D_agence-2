<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { TopbarComponent, WidgetCard, GlobalLoader, HeaderComponent, PermissionGuard } from '@components';
import { getApplicationMeta } from '@utils';
import { tools } from '@config/uniServices.js';
import { getWidgetDataByCodeService, getWidgetsCatalogService, updateDashboardWidgetLayoutService, getActualitesService } from '@requests';
import { useUsersStore, useAnneeUnivStore, useEtablissementStore } from "@stores";
import { formatDateLong } from "@helpers/date";

const router = useRouter();
const route = useRoute();
const userStore = useUsersStore();
const anneeUnivStore = useAnneeUnivStore();
const date = new Date();
const etablissementStore = useEtablissementStore();

defineProps({
  appName: {
    type: String,
    required: true,
  },
  logoUrl: {
    type: String,
    required: false,
    default: '',
  },
});

const activatedBundles = computed(() => tools
    .filter((bundle) => userStore.applications.includes(bundle.urlSlug))
    .map((bundle) => ({ ...bundle, meta: getApplicationMeta(bundle.urlSlug) }))
    .sort((a, b) => Number(b.urlSlug === 'intranet') - Number(a.urlSlug === 'intranet'))
);
const isLoadingBundles = ref(false);
const widgets = ref([]);
const isLoadingWidgets = ref(false);
const widgetData = ref({});
const selectedAnneeUniversitaireId = computed(() => anneeUnivStore.selectedAnneeUniv?.id ?? null);
const etablissement = ref([]);

const titrePortail = computed(() => {
  const libelle = userStore.departementDefaut?.libelle;
  return libelle ? `Portail - ${libelle}` : 'Portail';
});

onMounted(async () => {
  isLoadingBundles.value = true;
  try {
    etablissement.value = await etablissementStore.etablissement;
    await getWidgets();
  } finally {
    isLoadingBundles.value = false;
  }
});

const structureDepartementPersonnelId = computed(() => userStore.departementDefaut?.departementPersonnel?.id || null);

const updateWidgetSpan = async (widget, spanType, newValue) => {
  // Limiter colSpan à max 4 (largeur de la grille)
  if (spanType === 'colSpan') {
    newValue = Math.min(4, Math.max(1, newValue));
  }
  // rowSpan n'a pas de limite
  if (spanType === 'rowSpan') {
    newValue = Math.max(1, newValue);
  }

  const updateData = { [spanType]: newValue };

  if (spanType === 'colSpan') {
    widget.colSpan = newValue;
  } else if (spanType === 'rowSpan') {
    widget.rowSpan = newValue;
  }

  await updateDashboardWidgetLayoutService(
      widget.code,
      updateData,
      {
        dashboardCode: 'portail',
        structureDepartementPersonnelId: structureDepartementPersonnelId.value,
      }
  );
};

const toggleWidget = async (widget) => {
  widget.enabled = !widget.enabled;
  await updateDashboardWidgetLayoutService(
      widget.code,
      { enabled: widget.enabled },
      {
        dashboardCode: 'portail',
        structureDepartementPersonnelId: structureDepartementPersonnelId.value,
      }
  );
  await getWidgets();
};

const moveWidget = async (widget, direction) => {
  // tableau trier par position
  const sorted = [...widgets.value].sort((a, b) => a.position - b.position);
  // trouver l'index du widget dans le tableau
  const index = sorted.findIndex((item) => item.code === widget.code);

  // si le widget n'est pas trouvé, on arrête
  if (index === -1) {
    return;
  }

  // index cible
  const targetIndex = index + direction;

  // si l'index cible est en dehors du tableau, on arrête
  if (targetIndex < 0 || targetIndex >= sorted.length) {
    return;
  }

  // on retire le widget de son index et on l'insère à l'index cible
  const movedWidget = sorted.splice(index, 1)[0];
  sorted.splice(targetIndex, 0, movedWidget);

  // on met à jour les positions des widgets
  sorted.forEach((item, position) => {
    item.position = position;
  });

  // on met à jour le tableau des widgets
  widgets.value = sorted;

  // pour chaque widget
  widgets.value.map(async widget => {
    // on met à jour la position dans la base de données
    await updateDashboardWidgetLayoutService(
        widget.code,
        {position: widget.position},
        {
          dashboardCode: 'portail',
          structureDepartementPersonnelId: structureDepartementPersonnelId.value,
        }
    );
  });

};

const loadWidgetData = async (code) => {
  try {
    widgetData.value[code] = await getWidgetDataByCodeService(code);
  } catch(error) {
    console.error(error)
  }
};

const getWidgets = async () => {
  isLoadingWidgets.value = true;
  const params = {
    dashboardCode: 'portail',
    structureDepartementPersonnelId: structureDepartementPersonnelId.value,
  };
  const response = await getWidgetsCatalogService(params);
  widgets.value = response.widgets || [];
  await Promise.all(
      widgets.value.map(({ code }) => loadWidgetData(code))
  );
  isLoadingWidgets.value = false;
};

// Recharger les widgets quand on revient de la page de configuration
watch(() => route.path, async (newPath, oldPath) => {
  if (oldPath?.includes('/portail/widgets') && !newPath.includes('/portail/widgets')) {
    isLoadingWidgets.value = true;
    try {
      await getWidgets();
    } finally {
      isLoadingWidgets.value = false;
    }
  }
});
</script>

<template>
  <div>
    <TopbarComponent :app-name :logo-url/>

    <main v-if="route.path.includes('/portail/widgets')" id="contenu-principal">
      <RouterView class="mt-28 mx-10"/>
    </main>
    <div v-else class="px-4 lg:px-10">
      <div class="grid grid-cols-12 gap-4">
        <aside class="col-span-12 lg:col-span-2 pt-28 pb-14 lg:h-screen lg:sticky lg:top-0 lg:self-start">
          <div class="card card-body h-full overflow-y-auto flex flex-col">
            <div class="flex flex-col gap-4">
              <div class="flex items-center justify-between gap-3">
                <h2 id="portail-applications-title" class="m-0! text-base! font-semibold">Mes applications</h2>
              </div>
              <p class="m-0! text-sm text-muted-color">Retrouvez les outils de votre espace de travail.</p>
              <GlobalLoader v-if="isLoadingBundles" text="Chargement des applications..."/>
              <nav v-else-if="activatedBundles.length" aria-labelledby="portail-applications-title" class="flex flex-col gap-3">
                  <a
                      v-for="bundle in activatedBundles"
                      :key="bundle.urlSlug"
                      :href="bundle.url"
                      class="flex items-center gap-3 p-1.5 border border-surface rounded-md transition-colors duration-200 hover:bg-emphasis"
                  >
                    <ApplicationBadge :app="bundle.urlSlug" show-icon full-width />
                    <i class="pi pi-arrow-right ml-auto shrink-0 text-muted-color text-xs" aria-hidden="true" />
                  </a>
                <button v-if="userStore.isSuperAdmin" type="button" class="flex items-center gap-3 p-1.5 border border-surface rounded-md transition-colors duration-200 hover:bg-emphasis" @click="router.push('/configuration')">
                  <i class="pi pi-cog text-primary" aria-hidden="true" />
                  <span class="font-semibold text-sm">Configuration</span>
                  <i class="pi pi-arrow-right ml-auto shrink-0 text-muted-color text-xs" aria-hidden="true" />
                </button>
              </nav>
              <Message v-else severity="info">
                Aucune application accessible dans cet espace. Contactez votre administrateur pour faire vérifier vos accès.
              </Message>
            </div>
          </div>
        </aside>

        <main id="contenu-principal" class="col-span-12 lg:col-span-10 pt-28 pb-14">
          <div>
            <HeaderComponent
                icon="pi pi-home"
                :titre="titrePortail"
                description="Accédez à vos applications et personnalisez votre tableau de bord"
                :show-back="false"
            />
            <div v-if="!userStore.isLoading" class="flex items-center justify-between mb-4">
              <div class="flex items-center">
                <div class="w-20 h-20 bg-primary-400 rounded-full flex items-center justify-center shrink-0">
                  <template v-if="userStore.userPhoto">
                    <img :src="userStore.userPhoto" alt="photo de profil" class="rounded-full" />
                  </template>
                  <template v-else>
                    <span class="text-color text-xl">{{ initiales }}</span>
                  </template>
                </div>
                <div class="ml-4">
                  <h2 class="text-2xl! mb-0! font-bold flex items-center gap-2">
                    <span class="font-light">Bonjour,</span> {{ userStore.user.prenom }}
                  </h2>
                  <small class="text-muted-color">{{ formatDateLong(date) }}</small>
                </div>
              </div>
              <div class="card flex justify-between items-center gap-6 m-0! p-4!">
                <div>
                  <div class="text-xl font-semibold">Mon dashboard</div>
                  <div class="text-sm text-color-secondary">Personnalisez vos widgets.</div>
                </div>
                <Button icon="pi pi-cog" label="Configurer" size="small" @click="router.push({name: 'PortailDashboardWidgetsConfig', params: {bundle: 'portail'}})"/>
              </div>
            </div>
            <GlobalLoader v-if="isLoadingWidgets" text="Chargement des widgets..."/>
            <div v-else class="dashboard-grid">
              <WidgetCard
                  v-for="widget in widgets"
                  :key="widget.code"
                  :widget="widget"
                  :data="widgetData[widget.code]"
                  :first="widget.position == 0 ? true : false"
                  :last="widget.position == widgets.length - 1 ? true : false"
                  @move="moveWidget"
                  @updateSpan="updateWidgetSpan"
                  @toggle="toggleWidget"
                  :is-portail = true
              />
            </div>

            <div class="flex justify-between mt-4 gap-4">
              <div class="card w-2/3">
                <header class="card-header flex justify-between items-center w-full">
                  <div class="flex flex-col items-start">
                    <h2 class="m-0! text-xl!">
                      <i class="pi pi-link text-primary"></i> Liens utiles
                    </h2>
                  </div>
                  <PermissionGuard permission="isSuperAdmin">
                    <Button severity="primary" size="small" icon="pi pi-pencil" label="Modifier"/>
                  </PermissionGuard>
                </header>
                <div class="card-body flex items-start justify-around gap-4">
                  <div class="w-full flex flex-col items-center justify-center bg-surface-200/20 rounded-md p-2">
                    <i class="pi pi-crown text-2xl! text-primary" aria-hidden="true"></i>
                    <div class="font-bold">Site de l'URCA</div>
                    <Button
                        as="a"
                        href="https://www.univ-reims.fr"
                        target="_blank"
                        rel="noopener"
                        severity="secondary"
                        size="small"
                        icon="pi pi-external-link"
                        label="Accéder"
                        aria-label="Accéder au site de l'URCA, nouvel onglet"
                        class="touch-target"
                    />
                  </div>
                  <div v-if="etablissement?.site_web" class="w-full flex flex-col items-center justify-center bg-surface-200/20 rounded-md p-2">
                    <i class="pi pi-building-columns text-2xl! text-primary" aria-hidden="true"></i>
                    <div class="font-bold">Site de l'IUT</div>
                    <Button
                        as="a"
                        :href="etablissement.site_web"
                        target="_blank"
                        rel="noopener"
                        severity="secondary"
                        size="small"
                        icon="pi pi-external-link"
                        label="Accéder"
                        aria-label="Accéder au site de l'IUT, nouvel onglet"
                        class="touch-target"
                    />
                  </div>
                </div>
              </div>
              <div class="card w-1/3">
                <header class="card-header flex justify-between items-center w-full">
                  <div class="flex flex-col items-start">
                    <h2 class="m-0! text-xl!"><i class="pi pi-users text-primary" aria-hidden="true"></i> Contacts</h2>
                  </div>
                </header>
                <div class="card-body flex items-start justify-around gap-4">
                  <div class="w-full flex flex-col items-center justify-center">
                    <p class="uppercase text-xs font-bold mb-0! text-muted-color">établissement</p>
                    <div class="font-bold">Support technique</div>
                    <div>
                      CYNDEL HEROLT
                    </div>
                    <a href="mailto:cyndel.herolt@univ-reims.fr" class="text-sm underline! touch-target justify-center">
                      cyndel.herolt@univ-reims.fr
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-auto-rows: 1fr;
  gap: 1rem;
}
</style>
