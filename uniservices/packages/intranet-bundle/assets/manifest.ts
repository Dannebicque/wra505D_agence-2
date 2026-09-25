import dashboardRoutes from './router/modules/dashboardRoutes.js';
import agendaRoutes from './router/modules/agendaRoutes.js';
import trombinoscopeRoutes from './router/modules/trombinoscopeRoutes.js';
import scolariteRoutes from './router/modules/scolariteRoutes.js';
import notificationsRoutes from './router/modules/notificationsRoutes.js';
import cahierDeTexteRoutes from './router/modules/cahierDeTexteRoutes.js';
import profilRoutes from './router/modules/profilRoutes.js';
import administrationRoutes from './router/modules/administrationRoutes.js';
import superAdministrationRoutes from './router/modules/superAdministrationRoutes.js';
import Logo from "@images/logo/logo_intranet_iut_troyes.svg";
import LayoutComponent from '@components/components/layout/AppLayout.vue';
import { registerWidgets } from './components/Personnel/dashboard/registerWidgets';

const intranetMenu = {
  label: 'Intranet',
  icon: 'pi pi-fw pi-desktop',
  items: [
    { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/intranet/' },
    { label: 'Agenda', icon: 'pi pi-fw pi-calendar', to: '/intranet/agenda' },
    { label: 'Trombinoscope',
      icon: 'pi pi-fw pi-users',
      to: '/intranet/trombinoscope',
      permission: 'isPersonnel'
    },
    {
      label: 'Scolarité',
      icon: 'pi pi-fw pi-graduation-cap',
      to: '/intranet/scolarite',
      permission: 'isEtudiant'
    },
    {
      label: 'Cahier de texte',
      icon: 'pi pi-fw pi-book',
      to: '/intranet/cahier-de-texte',
      permission: 'isEtudiant'
    },
    {
      label: 'Administration',
      icon: 'pi pi-fw pi-wrench',
      to: '/intranet/administration',
      permission: 'canViewAdministration'
    },
    {
      label: 'Super Admin',
      icon: 'pi pi-fw pi-cog',
      to: '/intranet/super-administration',
      permission: 'SUPER_ADMIN'
    }
  ]
};

// Libellés choisis pour l'étudiant (P5) : le menu du personnel garde les siens.
const studentMenu = [
  { label: 'Accueil', icon: 'pi pi-fw pi-home', to: '/intranet/', groupe: 'scolarite', ordre: 10, motsCles: ['tableau de bord', 'dashboard'] },
  { label: 'Emploi du temps', icon: 'pi pi-fw pi-calendar', to: '/intranet/agenda', groupe: 'scolarite', ordre: 20, motsCles: ['agenda', 'edt', 'planning', 'cours'] },
  { label: 'Notes et absences', icon: 'pi pi-fw pi-graduation-cap', to: '/intranet/scolarite', groupe: 'scolarite', ordre: 30, motsCles: ['scolarité', 'moyennes', 'bulletin', 'relevé'] },
  { label: 'Cahier de texte', icon: 'pi pi-fw pi-book', to: '/intranet/cahier-de-texte', groupe: 'scolarite', ordre: 50, motsCles: ['devoirs', 'travail à faire'] },
  { label: 'Notifications', icon: 'pi pi-fw pi-bell', to: '/intranet/notifications', groupe: 'scolarite', ordre: 60, motsCles: ['messages', 'mails', 'e-mails', 'messagerie', 'alertes'] },
];

export default {
  name: 'intranet',
  studentMenu,
  primaryColor: 'violet',
  registerWidgets,
  routes: [
    {
      path: '/intranet',
      component: LayoutComponent,
      props: route => ({
        logoUrl: Logo,
        appName: 'Intranet',
        breadcrumbItems: typeof route.meta.breadcrumb === 'function'
          ? route.meta.breadcrumb(route)
          : (route.meta.breadcrumb || [])
      }),
      children: [
        ...dashboardRoutes,
        ...agendaRoutes,
        ...trombinoscopeRoutes,
        ...scolariteRoutes,
        ...notificationsRoutes,
        ...cahierDeTexteRoutes,
        ...profilRoutes,
        ...administrationRoutes,
        ...superAdministrationRoutes,
      ]
    }
  ],
  menu: intranetMenu
};
