import {useUsersStore} from '@stores';

const filPersonnel = [{ label: 'Dashboard', route: '/intranet' }];

export default [
    {
        path: '',
        alias: '/',
        component: () => import('@/views/IntranetDashboardView.vue'),
        name: 'IntranetDashboard',
        // Comme l'agenda : pour l'étudiant, un fil d'Ariane qui ne mène qu'à la page où il se trouve
        // ne dit rien, la carte de bienvenue ouvre la page.
        meta: { breadcrumb: () => (useUsersStore().isEtudiant ? [] : filPersonnel) },
    },
    {
        path: 'widgets',
        redirect: { name: 'PortailDashboardWidgetsConfig', params: { bundle: 'portail' } },
        name: 'IntranetDashboardWidgetsConfig',
    },
];
