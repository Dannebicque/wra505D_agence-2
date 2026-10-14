export default [
    {
        path: 'scolarite',
        component: () => import('@/views/Scolarite/ScolariteView.vue'),
        name: 'Scolarité',
        // Comme l'agenda et l'accueil : le fil ne menait qu'à l'accueil, déjà dans le menu.
        meta: { breadcrumb: [] },
    },
];
