export default [
    {
        path: 'notifications',
        component: () => import('@/views/Notifications/NotificationsView.vue'),
        name: 'Notifications',
        meta: {
            permission: 'isEtudiant',
            breadcrumb: [{ label: 'Accueil', route: '/intranet/'}, { label: 'Notifications', route: null, icon: 'pi pi-bell'}],
        },
    },
];
