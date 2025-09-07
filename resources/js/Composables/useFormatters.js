import { format } from 'date-fns';
import es from 'date-fns/locale/es';

export function useFormatters() {
    const formatTime = (timeString) => {
        if (!timeString) return '';
        const [hours, minutes] = timeString.split(':');
        const date = new Date();
        date.setHours(parseInt(hours), parseInt(minutes));
        return format(date, 'hh:mm a');
    };

    const formatDate = (dateString, formatStr = 'dd MMMM yyyy') => {
        if (!dateString) return '';
        return format(new Date(dateString), formatStr, { locale: es });
    };

    const getStatusInfo = (statusKey, statusMap, defaultInfo = { label: 'Desconocido', severity: 'secondary' }) => {
        return statusMap[statusKey] || defaultInfo;
    };
    
    const getDisplaySchedule = (businessHours) => {
        if (!businessHours || Object.keys(businessHours).length === 0) return 'No definido';
        const firstActiveDay = Object.values(businessHours).find(day => day.is_active);
        if (firstActiveDay) {
            return `${firstActiveDay.day_name}: ${formatTime(firstActiveDay.start_time)} - ${formatTime(firstActiveDay.end_time)}`;
        }
        return 'Cerrado todos los días';
    };

    const formatHolidayDateRule = (rule, months) => {
        if (!rule || !rule.month) return 'N/A';
        const monthName = months.find(m => m.value === rule.month)?.label || '';
        if (rule.type === 'fixed') {
            return `${rule.day} de ${monthName}`;
        }
        if (rule.type === 'dynamic') {
            return `${rule.order} ${rule.weekday} de ${monthName}`;
        }
        return 'N/A';
    };

    return { formatTime, formatDate, getStatusInfo, getDisplaySchedule, formatHolidayDateRule };
}