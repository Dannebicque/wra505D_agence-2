<script setup>
import { computed, defineProps, ref } from 'vue';
import { resolveWidgetComponent } from './widgets/widgetRegistry';

const isEditing = ref(false);

const props = defineProps({
    widget: {
        type: Object,
        required: true
    },
    data: {
        type: Object,
        required: true
    },
    first: {
        type: Boolean,
        default: false
    },
    last: {
        type: Boolean,
        default: false
    },
    isPortail: {
        type: Boolean,
        default: false
    },
    // Affiche, dans le panneau de réglage, la poignée de glisser-déposer. Le conteneur qui l'active
    // fournit le glisser lui-même (handle=".poignee-widget") ; les boutons fléchés restent
    // l'alternative au clavier.
    reorderable: {
        type: Boolean,
        default: false
    },
    // Propose de changer la largeur et la hauteur du widget.
    resizable: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['move', 'updateSpan', 'toggle']);

const gridStyle = computed(() => ({
    gridColumn: `span ${props.widget.colSpan || 1}`,
    gridRow: `span ${props.widget.rowSpan || 1}`,
}));

const resolvedComponent = computed(() => resolveWidgetComponent(props.widget?.component));

const moveWidget = (direction) => {
    emit('move', props.widget, direction);
};

const increaseColSpan = () => {
    const newColSpan = Math.min(4, (props.widget.colSpan || 1) + 1);
    emit('updateSpan', props.widget, 'colSpan', newColSpan);
};

const decreaseColSpan = () => {
    const newColSpan = Math.max(1, (props.widget.colSpan || 1) - 1);
    emit('updateSpan', props.widget, 'colSpan', newColSpan);
};

const increaseRowSpan = () => {
    emit('updateSpan', props.widget, 'rowSpan', (props.widget.rowSpan || 1) + 1);
};

const decreaseRowSpan = () => {
    const newRowSpan = Math.max(1, (props.widget.rowSpan || 1) - 1);
    emit('updateSpan', props.widget, 'rowSpan', newRowSpan);
};

const toggleWidget = () => {
    emit('toggle', props.widget);
};
</script>

<template>
    <article
    :key="widget.code"
    :style="gridStyle"
    class="card m-0! lg:p-6! p-4!"
    >
    <div v-if="isEditing">
        <div class="flex justify-between">
            <div class="flex items-center gap-1">
                <template v-if="resizable">
                    <Button icon="pi pi-plus" size="small" text rounded title="Augmenter largeur" aria-label="Augmenter largeur" @click="increaseColSpan"/>
                    <Button icon="pi pi-minus" size="small" text rounded title="Diminuer largeur" aria-label="Diminuer largeur" @click="decreaseColSpan"/>
                    <Button icon="pi pi-chevron-down" size="small" text rounded title="Augmenter hauteur" aria-label="Augmenter hauteur" @click="increaseRowSpan"/>
                    <Button icon="pi pi-chevron-up" size="small" text rounded title="Diminuer hauteur" aria-label="Diminuer hauteur" @click="decreaseRowSpan"/>
                </template>
                <span
                    v-if="reorderable"
                    class="poignee-widget flex h-[44px] w-[44px] cursor-grab items-center justify-center rounded-full text-muted-color hover:bg-surface-100 active:cursor-grabbing dark:hover:bg-surface-800"
                    title="Glisser pour déplacer le widget"
                    aria-hidden="true"
                >
                    <i class="pi pi-arrows-alt"></i>
                </span>
                <Button v-if="!first" icon="pi pi-arrow-left" size="small" title="Déplacer sur la gauche" aria-label="Déplacer sur la gauche" text rounded severity="secondary" @click="moveWidget(-1)"/>
                <Button v-if="!last" icon="pi pi-arrow-right" size="small" title="Déplacer sur la droite" aria-label="Déplacer sur la droite" text rounded severity="secondary" @click="moveWidget(1)"/>
                <Button icon="pi pi-trash" size="small" title="Retirer le widget" aria-label="Retirer le widget" text rounded severity="danger" @click="toggleWidget()"/>
            </div>
            <Button icon="pi pi-times" size="small" text rounded title="Fermer le panneau de configuration" aria-label="Fermer le panneau de configuration" @click="isEditing = !isEditing"/>
        </div>
        <Divider/>
    </div>
    <div class="mb-3 flex items-start justify-between gap-2">
        <div class="flex flex-col">
            <div class="font-semibold flex items-start gap-2">
                <i :class="`${widget.icon} text-primary-500`" aria-hidden="true"/>
                <span>{{ widget.label }}</span>
            </div>
            <div v-if="widget.bundle && props.isPortail" class="mt-1">
                <span v-if="widget.bundle !== 'auth'" class="inline-flex items-center rounded bg-primary-50 dark:bg-primary-900/30 px-1.5 py-0.5 text-xs font-semibold text-primary-700 dark:text-primary-300 border border-primary-200/50 dark:border-primary-900/50 uppercase tracking-wider">
                    {{ widget.bundle }}
                </span>
            </div>
        </div>
        <Button v-if="!isEditing" icon="pi pi-cog" size="small" text rounded title="Configurer le widget" aria-label="Configurer le widget" @click="isEditing = !isEditing"/>
    </div>
    <component :is="resolvedComponent" :data="data" :widget="widget" />
</article>
</template>

<style scoped>
/* Sans cela, le navigateur prend le geste du doigt pour un défilement et le glisser n'a jamais lieu. */
.poignee-widget {
    touch-action: none;
}
</style>
