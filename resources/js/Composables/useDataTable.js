import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';

/**
 * Composable para gestionar la lógica de una tabla de datos paginada y filtrable con Inertia.js.
 * @param {string} routeName - El nombre de la ruta a la que se harán las peticiones.
 * @param {object} initialFilters - Los filtros iniciales que vienen del controlador.
 * @param {object} dataObject - El objeto de datos paginados de Laravel (con `data` y `meta`).
 */
export function useDataTable(routeName, initialFilters = {}, dataObject = {}) {
    // --- CORRECCIÓN: Se crea una ref separada para `search` que será retornada. ---
    const search = ref(initialFilters.search || '');

    // El resto de los parámetros se gestionan internamente.
    const params = ref({
        sort_by: initialFilters.sort_by || 'id',
        sort_direction: initialFilters.sort_direction || 'desc',
        page: dataObject.meta?.current_page || 1,
        per_page: dataObject.meta?.per_page || 20,
    });

    // Función centralizada para realizar la petición con los parámetros actuales
    const fetchData = (options = { preserveState: true, preserveScroll: true, replace: true }) => {
        const queryParams = {
            search: search.value,
            ...params.value
        };
        // Limpiamos los parámetros que no tengan valor para no ensuciar la URL
        Object.keys(queryParams).forEach(key => {
            if (queryParams[key] === '' || queryParams[key] === null || queryParams[key] === undefined) {
                delete queryParams[key];
            }
        });

        router.get(route(routeName), queryParams, options);
    };

    // --- CORRECCIÓN: El watcher ahora observa la `ref` de `search` directamente. ---
    watch(search, debounce(() => {
        params.value.page = 1; // Reinicia a la primera página al buscar
        fetchData();
    }, 300));

    // Manejador para el ordenamiento de columnas
    const onSort = (event) => {
        params.value.sort_by = event.sortField;
        params.value.sort_direction = event.sortOrder === 1 ? 'asc' : 'desc';
        params.value.page = 1; // Reinicia a la primera página al ordenar
        fetchData();
    };

    // Manejador para el cambio de página o de ítems por página
    const onPage = (event) => {
        params.value.page = event.page + 1; // Paginator es 0-index, Laravel es 1-index
        params.value.per_page = event.rows;
        fetchData();
    };

    // --- CORRECCIÓN: Se retorna la `ref` de `search` para que v-model funcione. ---
    return {
        search,
        onSort,
        onPage,
    };
}