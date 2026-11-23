<script setup>
import EmptyState from './EmptyState.vue';
import { ref, onMounted, computed, watch, useSlots } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import ButtonInfo from '@components/components/Buttons/ButtonInfo.vue'
import ButtonEdit from '@components/components/Buttons/ButtonEdit.vue'
import ButtonDelete from '@components/components/Buttons/ButtonDelete.vue'
import { FilterMatchMode } from '@primevue/core/api'
import api from '@helpers/axios.js'
import { normalizeCollectionResponse } from '@helpers/collectionResponse.js'

/**
 * Tableau générique.
 *
 * Deux modes :
 *  - serveur (lazy) : `apiEndpoint` fourni, pagination / tri / recherche délégués à l'API ;
 *  - local : `value` fourni (tableau déjà chargé), pagination / tri / filtres faits par PrimeVue.
 *
 * Colonnes : { field, header, key?, sortable?, style?, type?, bodyClass?, headerClass?, filterField?,
 *              filter?: { type: 'text' | 'select', matchMode?, placeholder?, options?, optionLabel?, optionValue?, param? } }
 * Une cellule peut être personnalisée via le slot `body-<key|field>` ({ data, index }).
 * Expansion de ligne : `expandable` + slot `expansion` ({ data }) + v-model:expandedRows.
 */
const props = defineProps({
  columns: {
    type: Array,
    required: true
  },
  actionAdd: {
    type: Object,
    required: false,
    default: () => ({})
  },
  actions: {
    type: Array,
    required: false,
    default: () => []
  },
  extraActions: {
    type: Array,
    required: false,
    default: () => []
  },
  apiEndpoint: {
    type: String,
    required: false,
    default: null
  },
  value: {
    type: Array,
    required: false,
    default: null
  },
  loading: {
    type: Boolean,
    required: false,
    default: false
  },
  refreshKey: {
    type: Number,
    required: false,
    default: 0
  },
  searchParameter: {
    type: String,
    required: false,
    default: null
  },
  globalFilterFields: {
    type: Array,
    required: false,
    default: null
  },
  filterDisplay: {
    type: String,
    required: false,
    default: null // 'row' | 'menu' : active les filtres par colonne
  },
  expandable: {
    type: Boolean,
    required: false,
    default: false
  },
  showHeader: {
    type: Boolean,
    required: false,
    default: true
  },
  showFooter: {
    type: Boolean,
    required: false,
    default: true
  },
  rowsPerPageOptions: {
    type: Array,
    required: false,
    default: () => [30, 60, 120]
  },
  dataKey: {
    type: String,
    required: false,
    default: 'id'
  },
  tableClass: {
    type: [String, Array, Object],
    required: false,
    default: null
  },
  emptyTitle: {
    type: String,
    required: false,
    default: 'Aucun enregistrement trouvé.'
  }
})

const expandedRows = defineModel('expandedRows', { default: () => ({}) })
const globalFilter = defineModel('globalFilter', { default: null })

const slots = useSlots()
const modeLocal = computed(() => Array.isArray(props.value))

const data = ref([])
const totalRecords = ref(0)
const loadingApi = ref(!modeLocal.value)
const page = ref(0)
const limit = ref(props.rowsPerPageOptions[0])
const offset = computed(() => Number(limit.value * page.value))
const sortField = ref(null)
const sortOrder = ref(null)

const colKey = (col) => col.key ?? col.field
const colFilterField = (col) => col.filterField ?? col.field
const colonnesFiltrables = computed(() => props.filterDisplay ? props.columns.filter(col => col.filter) : [])

const defaultMatchMode = (col) => col.filter.matchMode
  ?? (col.filter.type === 'select' ? FilterMatchMode.EQUALS : FilterMatchMode.CONTAINS)

const construireFiltres = () => {
  const f = { global: { value: globalFilter.value, matchMode: FilterMatchMode.CONTAINS } }
  colonnesFiltrables.value.forEach((col) => {
    f[colFilterField(col)] = { value: null, matchMode: defaultMatchMode(col) }
  })
  return f
}
const filters = ref(construireFiltres())

// Synchronise la recherche globale avec un éventuel v-model:globalFilter du parent.
watch(globalFilter, (v) => { if (filters.value.global.value !== v) filters.value.global.value = v })
watch(() => filters.value.global.value, (v) => { if (globalFilter.value !== v) globalFilter.value = v })

const lignes = computed(() => modeLocal.value ? props.value : data.value)
const nombreTotal = computed(() => modeLocal.value ? props.value.length : totalRecords.value)
const chargementEnCours = computed(() => modeLocal.value ? props.loading : loadingApi.value)
const champsRecherche = computed(() => props.globalFilterFields ?? props.columns.map(col => col.field).filter(Boolean))

const fetchData = async () => {
  if (modeLocal.value || !props.apiEndpoint) return
  loadingApi.value = true
  const endpoint = props.apiEndpoint.startsWith('/') || props.apiEndpoint.startsWith('http')
    ? props.apiEndpoint
    : '/' + props.apiEndpoint

  const params = {
    limit: limit.value,
    offset: offset.value,
    sortField: sortField.value,
    sortOrder: sortOrder.value
  }

  if (props.searchParameter && filters.value.global.value) {
    params[props.searchParameter] = filters.value.global.value
  }

  colonnesFiltrables.value.forEach((col) => {
    const valeur = filters.value[colFilterField(col)]?.value
    if (valeur !== null && valeur !== undefined && valeur !== '') {
      params[col.filter.param ?? colFilterField(col)] = valeur
    }
  })

  try {
    const response = await api.get(endpoint, { params })
    const normalized = normalizeCollectionResponse(response.data)
    totalRecords.value = normalized.totalRecords
    data.value = normalized.items
  } finally {
    loadingApi.value = false
  }
}

onMounted(fetchData)

watch(() => props.refreshKey, async () => {
  await fetchData()
});

let searchTimeout = null
watch(() => filters.value.global.value, () => {
  if (modeLocal.value) return
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    page.value = 0
    await fetchData()
  }, 400)
})

const onPageChange = async (event) => {
  page.value = event.page
  await fetchData()
}

const onSortChange = async (event) => {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
  await fetchData()
}

function getNestedValue(obj, path) {
  return path.split('.').reduce((acc, part) => acc && acc[part], obj);
}
</script>

<template>
  <DataTable :value="data" lazy stripedRows paginator :first="offset" :rows="limit" :rowsPerPageOptions="rowOptions"
    :totalRecords="totalRecords" dataKey="id" :loading="loading" v-model:filterModel="filters" @page="onPageChange"
    @sort="onSortChange" @update:rows="limit = $event" :globalFilterFields="columns.map(col => col.field)">
    <template #header>
      <div class="flex justify-end">
        <Button label="Ajouter" icon="pi pi-plus" class="mr-2" @click="actionAdd.handler()" v-if="actionAdd" />
        <IconField>
          <InputIcon>
            <i class="pi pi-search" aria-hidden="true" />
          </InputIcon>
          <InputText v-model="filters.global.value" placeholder="Rechercher..." />
        </IconField>
      </div>
    </template>
    <template #empty> Aucun enregistrement trouvé.</template>
    <template #loading> Chargement des données. Patientez.</template>
    <Column v-for="col in columns" :key="col.field" :field="col.field" :header="col.header" :style="col.style"
      :sortable="col.sortable">
      <template #body="slotProps" v-if="col.type === undefined">
        {{ getNestedValue(slotProps.data, col.field) }}
      </template>
      <template #body="slotProps" v-else-if="col.type === 'date'">
        {{ new Date(getNestedValue(slotProps.data, col.field)).toLocaleDateString() }}
      </template>
      <template #body="slotProps" v-else-if="col.type === 'boolean'">
        <ToggleSwitch @change="col.handler(slotProps.data)" v-model="slotProps.data[col.field]" />
      </template>
      <!--      <template #filter="slotProps">-->
      <!--        <InputText v-model="slotProps.filterModel.value" type="text" @input="slotProps.filterCallback()" :placeholder="`Filter by ${col.header}`"/>-->
      <!--      </template>-->
    </Column>
    <Column v-if="actions.length" header="Actions" :style="{ minWidth: '10rem' }">
      <template #body="slotProps">
        <template v-for="action in actions">
          <ButtonInfo tooltip="Détails" v-if="action.type === 'show'" @click="action.handler(slotProps.data)" />
          <ButtonEdit v-if="action.type === 'edit'" tooltip="Modifier" @click="action.handler(slotProps.data)" />
          <ButtonDelete v-if="action.type === 'delete'" tooltip="Supprimer"
            @confirm-delete="action.handler(slotProps.data)" />
        </template>
      </template>
    </Column>
    <Column v-if="extraActions.length" header="Actions" :style="{ minWidth: '10rem' }">
      <template #body="slotProps">
        <Button v-for="action in extraActions" :key="action.label" :label="action.label" :icon="action.icon"
          @click="action.handler(slotProps.data)" />
      </template>
    </Column>

    <template v-if="expandable && slots.expansion" #expansion="slotProps">
      <slot name="expansion" v-bind="slotProps" />
    </template>
    <template v-if="showFooter" #footer> {{ nombreTotal }} résultat(s).</template>
  </DataTable>
</template>
