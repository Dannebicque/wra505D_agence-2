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

const MyPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{violet.50}',
            100: '{violet.100}',
            200: '{violet.200}',
            300: '{violet.300}',
            400: '{violet.400}',
            500: '{violet.500}',
            600: '{violet.600}',
            700: '{violet.700}',
            800: '{violet.800}',
            900: '{violet.900}',
            950: '{violet.950}'
        },
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
