import {useUsersStore} from '@stores';

const filPersonnel = [{ label: 'Accueil', route: '/' }, { label: 'Profil', route: null, icon: 'pi pi-user' }];

export default [
    {
        path: 'profil',
        component: () => import('@/views/ProfilView.vue'),
        // Comme sur l'agenda, l'étudiant n'a pas de fil d'Ariane : l'accueil est déjà dans son menu.
        meta: { breadcrumb: () => (useUsersStore().isEtudiant ? [] : filPersonnel) },
    }
];
