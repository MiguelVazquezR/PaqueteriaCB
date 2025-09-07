import { router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';

export function useConfirmDelete() {
    const confirm = useConfirm();

    /**
     * Muestra un diálogo de confirmación para eliminar un elemento.
     * @param {object} options - Opciones para la confirmación.
     * @param {object} options.item - El objeto a eliminar (debe tener un `id`).
     * @param {string} options.routeName - El nombre de la ruta para la acción de eliminar.
     * @param {string} [options.itemNameKey='name'] - La clave del objeto para mostrar en el mensaje.
     * @param {string} [options.message] - Un mensaje personalizado para anular el predeterminado.
     * @param {function} [options.onSuccess] - Un callback para ejecutar después de una eliminación exitosa.
     */
    const confirmDelete = ({ item, routeName, itemNameKey = 'name', message, onSuccess }) => {
        const finalMessage = message || `¿Estás seguro de que quieres eliminar "${item[itemNameKey]}"? Esta acción no se puede deshacer.`;

        confirm.require({
            message: finalMessage,
            header: 'Confirmar Eliminación',
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: 'Sí, eliminar',
            rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
            acceptProps: { label: 'Eliminar', severity: 'danger' },
            accept: () => {
                const options = {
                    preserveScroll: true,
                };
                if (onSuccess) {
                    options.onSuccess = onSuccess;
                }
                router.delete(route(routeName, item.id), options);
            }
        });
    };

    return { confirmDelete };
}