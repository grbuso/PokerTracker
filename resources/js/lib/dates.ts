/**
 * Normaliza qualquer data vinda do backend (Y-m-d, ISO com hora ou datetime
 * do banco) para a chave Y-m-d usada nos links e comparações.
 */
export const toDateKey = (value: string | null | undefined): string => {
    if (!value) {
        return '';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    return match ? `${match[1]}-${match[2]}-${match[3]}` : value.slice(0, 10);
};

/**
 * Formata uma data como DD/MM, independentemente de vir com hora ou não.
 */
export const formatShortDate = (value: string | null | undefined): string => {
    const [, month, day] = toDateKey(value).split('-');

    return month && day ? `${day}/${month}` : (value ?? '');
};

/**
 * Formata uma data como DD/MM/AAAA, independentemente de vir com hora ou não.
 */
export const formatDate = (value: string | null | undefined): string => {
    const [year, month, day] = toDateKey(value).split('-');

    return year && month && day ? `${day}/${month}/${year}` : (value ?? '');
};
