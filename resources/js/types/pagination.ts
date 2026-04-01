export type PaginationMeta = {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
};

export type PaginatedResponse<T> = {
    items: T[];
    meta: PaginationMeta;
};
