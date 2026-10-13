export default [
    {
        path: 'trombinoscope',
        component: () => import('@/views/TrombinoscopeView.vue'),
        name: 'Trombinoscope',
        meta: { permission: 'isPersonnel', breadcrumb: [{ label: 'Dashboard', route: '/'}, { label: 'Trombinoscope', route: null, icon: 'pi pi-users'}] },
    },
];
