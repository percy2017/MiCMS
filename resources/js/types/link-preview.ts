export type LinkPreviewItem = {
    url: string;
    final_url?: string | null;
    title?: string | null;
    description?: string | null;
    image?: string | null;
    image_width?: number | null;
    image_height?: number | null;
    site_name?: string | null;
    favicon?: string | null;
    error?: string | null;
};

export type LinkPreviews = {
    version?: number;
    fetched_at?: string | null;
    items: LinkPreviewItem[];
};
