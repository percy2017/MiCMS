import { useEffect, useMemo, useRef, useState } from 'react';

type UseClientTableSearchOptions<T> = {
    initialData: T[];
    searchFields: (keyof T)[];
    debounceMs?: number;
    perPage?: number;
    initialFilters?: Record<string, string>;
};

export function useClientTableSearch<T extends Record<string, unknown>>(opts: UseClientTableSearchOptions<T>) {
    const { initialData, searchFields, debounceMs = 250, perPage = 10, initialFilters = {} } = opts;

    const [search, setSearch] = useState<string>(initialFilters.search ?? '');
    const [filters, setFilters] = useState<Record<string, string>>(initialFilters);
    const [currentPage, setCurrentPage] = useState(1);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        const activeFilters = Object.entries(filters).filter(([k, v]) => v && k !== 'search');

        return initialData.filter((row) => {
            if (q) {
                const matches = searchFields.some((field) => {
                    const value = row[field];
                    if (value === null || value === undefined) return false;
                    return String(value).toLowerCase().includes(q);
                });
                if (!matches) return false;
            }

            for (const [key, value] of activeFilters) {
                const fieldValue = row[key];
                if (fieldValue === null || fieldValue === undefined) return false;
                if (Array.isArray(fieldValue)) {
                    if (!fieldValue.includes(value)) return false;
                } else if (String(fieldValue) !== value) {
                    return false;
                }
            }

            return true;
        });
    }, [initialData, search, filters, searchFields]);

    const total = filtered.length;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const start = (currentPage - 1) * perPage;
    const data = filtered.slice(start, start + perPage);

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            setCurrentPage(1);
        }, debounceMs);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [search, filters, debounceMs]);

    function setFilter(key: string, value: string) {
        setFilters((prev) => {
            const next = { ...prev };
            if (value) next[key] = value;
            else delete next[key];
            return next;
        });
    }

    function goPage(page: number) {
        setCurrentPage(page);
    }

    return {
        data,
        total,
        currentPage,
        lastPage,
        search,
        setSearch,
        filters,
        setFilter,
        goPage,
    };
}
