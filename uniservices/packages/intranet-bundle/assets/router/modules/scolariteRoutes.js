export default [
    {
        path: 'scolarite',
        component: () => import('@/views/Scolarite/ScolariteView.vue'),
        name: 'Scolarité',
        // Comme l'agenda et l'accueil : le fil ne menait qu'à l'accueil, déjà dans le menu.
        meta: { breadcrumb: [] },
    },
    {
        path: 'cahier-de-texte',
        component: () => import('@/views/Etudiants/CahierDeTexteView.vue'),
        name: 'Cahier de texte',
        meta: { breadcrumb: [{ label: 'Dashboard', route: '/'}, { label: 'Cahier de texte', route: null, icon: 'pi pi-book'}] },
    },
];
