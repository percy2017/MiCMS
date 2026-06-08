import type { Product } from '@/pages/admin/pos-woo/parts/types';

type Props = {
    product: Product;
    onAddToCart: (product: Product) => void;
};

function getPrice(product: Product): string | null {
    if (product.price && product.price !== '') return product.price;
    if (product.regular_price && product.regular_price !== '') return product.regular_price;
    return null;
}

export function ProductCard({ product, onAddToCart }: Props) {
    const price = getPrice(product);
    const isVariable = product.type === 'variable';
    const hasVariations = isVariable && (product.variations?.length ?? 0) > 0;

    return (
        <button
            onClick={() => onAddToCart(product)}
            className="group flex flex-col overflow-hidden rounded-xl border bg-card text-left shadow-sm transition-all hover:border-primary/50 hover:shadow-md active:scale-[0.98]"
        >
            {product.images && product.images.length > 0 && product.images[0].src ? (
                <div className="aspect-[4/3] overflow-hidden bg-muted">
                    <img
                        src={product.images[0].src}
                        alt={product.images[0].alt || product.name}
                        className="h-full w-full object-cover transition-transform group-hover:scale-105"
                        loading="lazy"
                    />
                </div>
            ) : (
                <div className="flex aspect-[4/3] items-center justify-center bg-gradient-to-br from-primary/10 to-primary/5">
                    <span className="text-3xl font-bold text-primary/40 drop-shadow-sm">
                        {product.name.charAt(0)}
                    </span>
                </div>
            )}
            <div className="flex flex-1 flex-col justify-between gap-2 p-3">
                <div>
                    <p className="line-clamp-2 text-sm font-medium leading-tight">
                        {product.name}
                    </p>
                    {product.categories && product.categories.length > 0 && (
                        <span className="mt-1 inline-block rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                            {product.categories[0].name}
                        </span>
                    )}
                </div>
                <div className="flex items-center justify-between">
                    {price ? (
                        <span className="text-lg font-bold tabular-nums text-primary">
                            ${parseFloat(price).toFixed(2)}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            {isVariable ? 'Ver variantes' : 'Sin precio'}
                        </span>
                    )}
                    {isVariable && hasVariations && (
                        <span className="flex size-7 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary opacity-0 transition-opacity group-hover:opacity-100">
                            +
                        </span>
                    )}
                </div>
            </div>
        </button>
    );
}
