import { ref } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';

export function useActionMenu() {
    const menuComponentRef = ref(null);
    const menuItems = ref([]);
    const { hasPermission } = usePermissions();

    const generateAndShowMenu = (event, item, config) => {
        const visibleItems = config
            .filter(menuItem => !menuItem.permission || hasPermission(menuItem.permission))
            .map(menuItem => ({
                ...menuItem,
                command: () => menuItem.action(item),
            }));

        if (visibleItems.length > 0) {
            menuItems.value = visibleItems;
            menuComponentRef.value.toggle(event);
        }
    };

    return { menuComponentRef, menuItems, generateAndShowMenu };
}