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
        },
        // Bordure d'un élément d'interface (champ, case, carte de choix) : 3,49:1 en clair et
        // 3,15:1 en sombre, au-dessus du seuil RGAA de 3:1, là où les gris d'Aura n'y arrivent pas.
        // Type de séance de l'emploi du temps, dans les couleurs de la DA : violet pour les CM, jaune
        // pour les TD, vert pour les TP, rouge pour les évaluations. Pour chacun, un fond pâle, une
        // couleur de texte sur ce fond, et une pastille foncée à texte blanc, plus lisible (retour de
        // LOU) : 6,52:1 au moins, en clair comme en sombre. Texte courant sur le fond 6,78:1 au moins
        // en clair et 9,72:1 en sombre, couleur sur le fond 5,65:1 et 6,91:1.
        colorScheme: {
            light: {
                uiBorder: { color: '#7E848D' },
                seance: {
                    cm: { background: '#F1ECFC', color: '#4D3677', pill: { background: '#4D3677', color: '#FFFFFF' } },
                    td: { background: '#FEF4D9', color: '#7A5200', pill: { background: '#7A5200', color: '#FFFFFF' } },
                    tp: { background: '#E2F7EC', color: '#0B6B3F', pill: { background: '#0B6B3F', color: '#FFFFFF' } },
                    sae: { background: '#FCE9F3', color: '#9E2A63', pill: { background: '#9E2A63', color: '#FFFFFF' } },
                    evaluation: { background: '#FEEAEA', color: '#B42424', pill: { background: '#B42424', color: '#FFFFFF' } },
                    autre: { background: '#EEF0F3', color: '#565C64', pill: { background: '#565C64', color: '#FFFFFF' } }
                }
            },
            dark: {
                uiBorder: { color: '#666B73' },
                seance: {
                    cm: { background: '#2B2240', color: '#C7B6F1', pill: { background: '#4D3677', color: '#FFFFFF' } },
                    td: { background: '#3A3020', color: '#F2C14E', pill: { background: '#7A5200', color: '#FFFFFF' } },
                    tp: { background: '#193A2B', color: '#6CD9A2', pill: { background: '#0B6B3F', color: '#FFFFFF' } },
                    sae: { background: '#3B2232', color: '#F09AC4', pill: { background: '#9E2A63', color: '#FFFFFF' } },
                    evaluation: { background: '#422124', color: '#F7A1A1', pill: { background: '#B42424', color: '#FFFFFF' } },
                    autre: { background: '#2E3237', color: '#B9BEC6', pill: { background: '#565C64', color: '#FFFFFF' } }
                }
            }
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
