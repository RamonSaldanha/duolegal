import { onMounted, ref, computed } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

export function updateTheme(value: Appearance) {
    if (value === 'system') {
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.classList.toggle('dark', systemTheme === 'dark');
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

const handleSystemThemeChange = () => {
    const currentAppearance = localStorage.getItem('appearance') as Appearance | null;
    updateTheme(currentAppearance || 'system');
};

export function initializeTheme() {
    // Initialize theme from saved preference or default to system...
    const savedAppearance = localStorage.getItem('appearance') as Appearance | null;
    updateTheme(savedAppearance || 'system');

    // Set up system theme change listener...
    mediaQuery.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const appearance = ref<Appearance>('system');
    const isDark = ref(false);

    // Computed para o tema do AppLogo
    const logoTheme = computed(() => isDark.value ? 'dark' : 'light');

    // Função para verificar se o tema dark está ativo
    const checkDarkMode = () => {
        if (typeof window !== 'undefined') {
            return document.documentElement.classList.contains('dark');
        }
        return false;
    };

    // Atualiza o estado isDark
    const updateDarkState = () => {
        isDark.value = checkDarkMode();
    };

    onMounted(() => {
        initializeTheme();

        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }

        // Inicializa o estado dark
        updateDarkState();

        // Observer para mudanças na classe dark do documento
        const observer = new MutationObserver(() => {
            updateDarkState();
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });

        // Cleanup function será automática quando o componente for desmontado
        return () => {
            observer.disconnect();
        };
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);
        updateTheme(value);
        // Atualiza o estado após mudança de tema
        setTimeout(updateDarkState, 0);
    }

    return {
        appearance,
        updateAppearance,
        isDark,
        logoTheme,
    };
}
