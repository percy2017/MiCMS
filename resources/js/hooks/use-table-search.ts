import { useCallback, useEffect, useRef, useState } from 'react';

type FetchResult<T> = {
    data: T[];
    total: number;
    current_page?: number;
    last_page?: number;
    currentPage?: number;
    lastPage?: number;
};

type UseTableSearchOptions = {
    endpoint: string;
    initialData: T[] | { data: T[]; total?: number; current_page?: number; last_page?: number };
    initialTotal?: number;
    initialCurrentPage?: number;
    initialLastPage?: number;
    perPage: number;
    debounceMs?: number;
    extraParams?: () => Record<string, string>;
    initialFilters?: Record<string, string>;
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.getAttribute('content') ?? '';
}

export function useTableSearch<T = unknown>(opts: UseTableSearchOptions) {
    const {
        endpoint,
        initialData,
        initialTotal,
        initialCurrentPage,
        initialLastPage,
        perPage,
        debounceMs = 400,
        extraParams,
        initialFilters = {},
    } = opts;

    const initialArr: T[] = Array.isArray(initialData)
        ? (initialData as T[])
        : (initialData?.data ?? []);
    const initTotal: number = Array.isArray(initialData)
        ? (initialTotal ?? initialArr.length)
        : (initialData?.total ?? initialTotal ?? initialArr.length);
    const initCurrent: number = Array.isArray(initialData)
        ? (initialCurrentPage ?? 1)
        : (initialData?.current_page ?? initialCurrentPage ?? 1);
    const initLast: number = Array.isArray(initialData)
        ? (initialLastPage ?? 1)
        : (initialData?.last_page ?? initialLastPage ?? 1);

    const [data, setData] = useState<T[]>(initialArr);
    const [total, setTotal] = useState<number>(initTotal);
    const [currentPage, setCurrentPage] = useState<number>(initCurrent);
    const [lastPage, setLastPage] = useState<number>(initLast);
    const [loading, setLoading] = useState(false);
    const [filters, setFilters] = useState<Record<string, string>>(initialFilters);
    const [search, setSearch] = useState<string>(initialFilters.search ?? '');
    const isFirst = useRef(true);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();

    const fetchPage = useCallback(
        (s: string, page: number, currentFilters: Record<string, string>) => {
            setLoading(true);
            const params = new URLSearchParams();
            if (s) params.set('search', s);
            for (const [k, v] of Object.entries(currentFilters)) {
                if (v && k !== 'search') params.set(k, v);
            }
            params.set('page', String(page));
            params.set('per_page', String(perPage));
            if (extraParams) {
                for (const [k, v] of Object.entries(extraParams())) params.set(k, v);
            }

            fetch(`${endpoint}?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                credentials: 'same-origin',
            })
                .then((r) => r.json())
                .then((res: FetchResult<T>) => {
                    setData(res.data ?? []);
                    setTotal(res.total ?? 0);
                    setCurrentPage(res.current_page ?? res.currentPage ?? page);
                    setLastPage(res.last_page ?? res.lastPage ?? 1);
                })
                .catch(() => {})
                .finally(() => setLoading(false));
        },
        [endpoint, perPage, extraParams],
    );

    useEffect(() => {
        if (isFirst.current) {
            isFirst.current = false;
            return;
        }
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            fetchPage(search, 1, filters);
        }, debounceMs);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [search, filters, fetchPage, debounceMs]);

    function goPage(page: number) {
        fetchPage(search, page, filters);
    }

    function setFilter(key: string, value: string) {
        setFilters((prev) => {
            const next = { ...prev };
            if (value) next[key] = value;
            else delete next[key];
            return next;
        });
    }

    function refresh() {
        fetchPage(search, currentPage, filters);
    }

    return {
        data,
        total,
        currentPage,
        lastPage,
        loading,
        search,
        setSearch,
        filters,
        setFilter,
        goPage,
        refresh,
    };
}
