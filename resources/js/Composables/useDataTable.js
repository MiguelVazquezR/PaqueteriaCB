import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';

export function useDataTable(routeName, initialFilters = {}, dataObject = {}) {
    const search = ref(initialFilters.search || '');
    const rowsPerPage = ref(dataObject.per_page || 10);

    watch(search, debounce((value) => {
        router.get(route(routeName), {
            ...initialFilters,
            search: value,
            per_page: rowsPerPage.value,
            page: 1, // Reinicia a la primera página al buscar
        }, { preserveState: true, replace: true });
    }, 300));

    const onSort = (event) => {
        router.get(route(routeName), {
            ...initialFilters,
            search: search.value,
            sort_by: event.sortField,
            sort_direction: event.sortOrder === 1 ? 'asc' : 'desc',
            per_page: rowsPerPage.value,
        }, { preserveState: true, replace: true });
    };

    const onPage = (event) => {
        rowsPerPage.value = event.rows;
        router.get(route(routeName), {
            ...initialFilters,
            search: search.value,
            page: event.page + 1,
            per_page: event.rows,
        }, { preserveState: true, replace: true });
    };

    return { search, onSort, onPage };
}