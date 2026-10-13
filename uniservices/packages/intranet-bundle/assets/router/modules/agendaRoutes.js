import {useUsersStore} from '@stores';

const filPersonnel = [{ label: 'Dashboard', route: '/' }, { label: 'Agenda', route: null, icon: 'pi pi-calendar' }];

export default [
    {
        path: 'agenda',
        component: () => import('@/views/AgendaView.vue'),
        // L'agenda de l'étudiant doit tenir entier à l'écran : son fil d'Ariane, qui ne menait qu'à
        // l'accueil déjà dans le menu, lui rend sa hauteur.
        meta: { breadcrumb: () => (useUsersStore().isEtudiant ? [] : filPersonnel) },
    }
];
