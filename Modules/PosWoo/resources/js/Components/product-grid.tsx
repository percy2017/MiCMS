import { ProductCard } from './product-card';
import type { Currency } from '@/lib/currency';
import type { Product } from './types';
type Props = {
    products: Product[];
    currency: Currency;
    onAddToCart: (product: Product) => void;
};

export function ProductGrid({ products, currency, onAddToCart }: Props) {
    if (products.length === 0) {
        return (
            <div className="flex h-full items-center justify-center">
                <p className="text-sm text-muted-foreground">
                    No se encontraron productos con ese término.
                </p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            {products.map((product) => (
                <ProductCard
                    key={product.id}
                    product={product}
                    currency={currency}
                    onAddToCart={onAddToCart}
                />
            ))}
        </div>
    );
}
