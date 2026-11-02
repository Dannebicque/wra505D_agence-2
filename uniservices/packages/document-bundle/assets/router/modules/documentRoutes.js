export default [
    {
        path: 'documents',
        component: () => import('@/views/DocumentsView.vue'),
        name: 'Documents',
    },
    {
        path: '',
        component: () => import('@/views/DocumentsView.vue'),
        name: 'DocumentsRoot',
    },
];
