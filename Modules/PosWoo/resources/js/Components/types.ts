export type WooImage = {
    src: string;
    alt: string;
};

export type WooCategory = {
    id: number;
    name: string;
};

export type Product = {
    id: number;
    name: string;
    slug?: string;
    type: string;
    price?: string;
    regular_price?: string;
    sale_price?: string;
    stock_status: string;
    stock_quantity?: number | null;
    categories?: WooCategory[];
    images?: WooImage[];
    variations?: number[];
};

export type WooVariationAttribute = {
    name: string;
    option: string;
};

export type WooVariation = {
    id: number;
    price: string;
    regular_price: string;
    sale_price?: string;
    stock_status: string;
    stock_quantity?: number | null;
    sku?: string;
    attributes: WooVariationAttribute[];
};

export type CartItem = {
    cartKey: string;
    productId: number;
    variationId: number | null;
    label: string;
    price: number;
    quantity: number;
    subtotal: number;
    image: string | null;
};

export type Customer = {
    id: number;
    name: string;
    email: string;
    phone?: string;
    avatar_url?: string | null;
};

export type PaymentGateway = {
    id: string;
    title: string;
    method_title: string;
    method_description: string;
    enabled: boolean;
};
