export default [
    {
        path: 'scolarite',
        component: () => import('@/views/Scolarite/ScolariteView.vue'),
        name: 'Scolarité',
        meta: { breadcrumb: [{ label: 'Accueil', route: '/'}, { label: 'Scolarité', route: null, icon: 'pi pi-graduation-cap'}] },
    },
];
