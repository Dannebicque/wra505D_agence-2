import { createApp } from 'vue';
import { createPinia } from "pinia";
import App from './App.vue';
import '@styles/main.scss';
import router from './router';
import { initializeAppData } from '@requests/initializeData';
import { registerPermissionDirective } from '@utils';
import { setupInactivityTimer } from '@helpers/authService';
import { registerAllBundleWidgets } from './widgets/registerBundleWidgets';

import Aura from '@primeuix/themes/aura';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import Ripple from 'primevue/ripple';
import StyleClass from 'primevue/styleclass';
import Tooltip from 'primevue/tooltip';
import { definePreset } from '@primeuix/themes';
import fr from '@config/fr.json';

import './assets/styles.scss';
import './assets/tailwind.css';

// Une seule primaire pour tous les modules : le violet de la DA, #4D3677, en 500. Les autres
// nuances en sont dérivées en OKLab. En clair, Aura pose du blanc dessus (9,95:1) ; en sombre, il
// prend la 400, à 6,54:1 sur le fond sombre.
const VIOLET_IUT = {
    50: '#F8F5FF',
    100: '#EFE9FF',
    200: '#DED4FB',
    300: '#C7B6F1',
    400: '#AC95E1',
    500: '#4D3677',
    600: '#3D2763',
    700: '#2E184F',
    800: '#210C3F',
    900: '#170331',
    950: '#0D0023'
};

const MyPreset = definePreset(Aura, {
    semantic: {
        primary: VIOLET_IUT,
        // Texte atténué de CLAUDE.md : le gris d'Aura tombait à 4,34:1 sur le fond des pages.
        colorScheme: {
            light: {
                text: { mutedColor: '#676D75' }
            },
            dark: {
                text: { mutedColor: '#A7ACB4' }
            }
        }
    },
    // La primaire d'origine de la DA devient l'accent. Seul sur blanc, le jaune ne fait que
    // 1,88:1 : il porte du texte #4D3677 (5,29:1) et ne délimite jamais un élément sans bordure.
    extend: {
        accent: {
            color: '#F7B000',
            contrastColor: '#4D3677'
        }
    },
    components: {
        button: {
            colorScheme: {
                light: {
                    root: {
                        success: {
                            background: '#15C377', hoverBackground: '#3DD68F', activeBackground: '#5FE0A3',
                            borderColor: '#15C377', hoverBorderColor: '#3DD68F', activeBorderColor: '#5FE0A3',
                            color: '#0B3D26', hoverColor: '#0B3D26', activeColor: '#0B3D26',
                            focusRing: { color: '#0B3D26' }
                        },
                        danger: {
                            background: '#F96868', hoverBackground: '#FA8585', activeBackground: '#FBA0A0',
                            borderColor: '#F96868', hoverBorderColor: '#FA8585', activeBorderColor: '#FBA0A0',
                            color: '#4A1010', hoverColor: '#4A1010', activeColor: '#4A1010',
                            focusRing: { color: '#4A1010' }
                        }
                    },
                    outlined: {
                        success: { color: '{green.800}', borderColor: '{green.800}' },
                        danger: { color: '{red.700}', borderColor: '{red.700}' }
                    },
                    text: {
                        success: { color: '{green.800}' },
                        danger: { color: '{red.700}' }
                    }
                }
            }
        },
        badge: {
            colorScheme: {
                light: {
                    success: { background: '#15C377', color: '#0B3D26' },
                    danger: { background: '#F96868', color: '#4A1010' }
                }
            }
        },
        // Les boutons non choisis d'un SelectButton prennent un gris à 4,34:1 sur le fond des pages.
        togglebutton: {
            colorScheme: {
                light: {
                    root: { color: '#676D75' },
                    icon: { color: '#676D75' }
                }
            }
        },
        // En clair, le texte des messages info et erreur d'Aura tombe à 3,76:1 et 4,02:1.
        message: {
            colorScheme: {
                light: {
                    info: { color: '{blue.700}' },
                    error: { color: '{red.700}' }
                }
            }
        }
    }
});

const pinia = createPinia();
const app = createApp(App);

registerAllBundleWidgets();

app.use(router);

app.use(PrimeVue, {
    locale : fr.fr,
    theme: {
        preset: MyPreset,
        options: {
            darkModeSelector: '.app-dark'
        }
    },
    // Les icônes de ces composants accompagnent un libellé ou un nom accessible : décoratives,
    // elles sont masquées aux lecteurs d'écran une fois pour toutes.
    pt: {
        button: { icon: { 'aria-hidden': 'true' } },
        message: { icon: { 'aria-hidden': 'true' } },
        tag: { icon: { 'aria-hidden': 'true' } },
    },
    ripple: true
});
app.directive('ripple', Ripple);
app.directive('styleclass', StyleClass);
app.directive('tooltip', Tooltip);
app.use(ToastService);
app.use(ConfirmationService);

app.use(pinia);

registerPermissionDirective(app);

// Ajouter un contenu temporaire avant le montage
import GlobalLoader from '@components/loader/GlobalLoader.vue';
const loadingElement = document.createElement('div');
loadingElement.id = 'loading';
document.body.appendChild(loadingElement);

const loaderApp = createApp(GlobalLoader);
loaderApp.mount(loadingElement);

// Initialiser les données et monter l'application
(async () => {
    try {
        await initializeAppData();
        setupInactivityTimer();
    } catch (error) {
        console.error('Erreur lors de l\'initialisation de l\'application:', error);
    } finally {
        // Supprimer le contenu temporaire après l'initialisation
        document.body.removeChild(loadingElement);
        // Monter l'application
        app.mount('#app');
    }
})();
