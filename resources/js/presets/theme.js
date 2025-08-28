import Aura from '@primeuix/themes/aura';

// Paleta de colores para 'neutral'
const neutralSurface = { 0: '#ffffff', 50: '#fafafa', 100: '#f5f5f5', 200: '#e5e5e5', 300: '#d4d4d4', 400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040', 800: '#262626', 900: '#171717', 950: '#0a0a0a' };

// Lógica para el color primario 'noir'
const noirPrimary = {
    semantic: {
        primary: { 50: '{surface.50}', 100: '{surface.100}', 200: '{surface.200}', 300: '{surface.300}', 400: '{surface.400}', 500: '{surface.500}', 600: '{surface.600}', 700: '{surface.700}', 800: '{surface.800}', 900: '{surface.900}', 950: '{surface.950}' },
        colorScheme: {
            light: {
                primary: { color: '{primary.950}', contrastColor: '#ffffff', hoverColor: '{primary.800}', activeColor: '{primary.700}' },
                highlight: { background: '{primary.950}', focusBackground: '{primary.700}', color: '#ffffff', focusColor: '#ffffff' }
            },
            dark: {
                primary: { color: '{primary.50}', contrastColor: '{primary.950}', hoverColor: '{primary.200}', activeColor: '{primary.300}' },
                highlight: { background: '{primary.50}', focusBackground: '{primary.300}', color: '{primary.950}', focusColor: '{primary.950}' }
            }
        }
    }
};

// ✨ --- SOLUCIÓN AQUÍ --- ✨
// Exportamos el objeto de tema con la estructura que PrimeVue espera.
export const theme = {
    preset: Aura,
    options: {
        // prefix: 'p',
        darkModeSelector: '.app-dark', // El selector debe estar en este nivel
        // cssLayer: {
        //     // name: 'primeui',
        //     order: 'tailwind-base, primeui, tailwind-utilities'
        // }
    },
    semantic: noirPrimary.semantic,
    surface: neutralSurface
};
