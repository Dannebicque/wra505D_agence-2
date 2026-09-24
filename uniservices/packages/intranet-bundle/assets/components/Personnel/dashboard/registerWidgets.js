import { registerWidgetComponent } from '@components';
import EmploiDuTempsWidget from './widgets/EmploiDuTempsWidget.vue';
import ActionsUrgentesWidget from './widgets/ActionsUrgentesWidget.vue';
import DocumentsRecentsWidget from './widgets/DocumentsRecentsWidget.vue';
import NotesWidget from '@/components/Etudiant/dashboard/NotesWidget.vue';
import ContactsWidget from '@/components/Etudiant/dashboard/ContactsWidget.vue';

export const registerWidgets = () => {
    registerWidgetComponent('EmploiDuTempsWidget', EmploiDuTempsWidget);
    registerWidgetComponent('ActionsUrgentesWidget', ActionsUrgentesWidget);
    registerWidgetComponent('DocumentsRecentsWidget', DocumentsRecentsWidget);
    registerWidgetComponent('NotesWidget', NotesWidget);
    registerWidgetComponent('ContactsWidget', ContactsWidget);
};
