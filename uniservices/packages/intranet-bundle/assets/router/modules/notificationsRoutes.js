export default [
    {
        path: 'notifications',
        component: () => import('@/views/Notifications/NotificationsView.vue'),
        name: 'Notifications',
        meta: {
            permission: 'isEtudiant',
        },
    },
];
