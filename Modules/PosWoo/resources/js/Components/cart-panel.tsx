import type { CartItem, Customer } from './types';

type Props = {
    cart: CartItem[];
    total: number;
    customer: Customer | null;
    onUpdateQuantity: (cartKey: string, quantity: number) => void;
    onRemoveFromCart: (cartKey: string) => void;
    onCheckout: () => void;
    onCustomerSearch: () => void;
};

export function CartPanel({
    cart,
    total,
    customer,
    onUpdateQuantity,
    onRemoveFromCart,
    onCheckout,
    onCustomerSearch,
}: Props) {
    return (
        <div className="flex h-full flex-col">
            <div className="border-b px-4 py-3">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                    Carrito
                </h2>
                <p className="text-xs text-muted-foreground">
                    {cart.length === 0
                        ? 'Vacío'
                        : `${cart.reduce((s, i) => s + i.quantity, 0)} artículo${cart.length !== 1 ? 's' : ''}`}
                </p>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-2">
                {cart.length === 0 ? (
                    <div className="flex h-full items-center justify-center">
                        <p className="text-center text-sm text-muted-foreground">
                            Selecciona productos
                            <br />
                            para comenzar
                        </p>
                    </div>
                ) : (
                    <ul className="space-y-2">
                        {cart.map((item) => (
                            <li
                                key={item.cartKey}
                                className="flex items-start gap-2 rounded-lg bg-muted/50 p-2.5"
                            >
                                {item.image && (
                                    <img
                                        src={item.image}
                                        alt=""
                                        className="mt-0.5 size-8 shrink-0 rounded object-cover"
                                    />
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {item.label}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        ${item.price.toFixed(2)} c/u
                                    </p>
                                </div>
                                <div className="flex items-center gap-1">
                                    <button
                                        onClick={() =>
                                            onUpdateQuantity(item.cartKey, item.quantity - 1)
                                        }
                                        className="flex size-6 items-center justify-center rounded-md border text-xs transition-colors hover:bg-muted"
                                    >
                                        −
                                    </button>
                                    <span className="flex h-6 min-w-[24px] items-center justify-center text-sm font-medium tabular-nums">
                                        {item.quantity}
                                    </span>
                                    <button
                                        onClick={() =>
                                            onUpdateQuantity(item.cartKey, item.quantity + 1)
                                        }
                                        className="flex size-6 items-center justify-center rounded-md border text-xs transition-colors hover:bg-muted"
                                    >
                                        +
                                    </button>
                                </div>
                                <div className="flex flex-col items-end gap-1">
                                    <span className="text-sm font-semibold tabular-nums">
                                        ${item.subtotal.toFixed(2)}
                                    </span>
                                    <button
                                        onClick={() => onRemoveFromCart(item.cartKey)}
                                        className="text-[10px] text-destructive/70 hover:text-destructive"
                                    >
                                        Quitar
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <div className="border-t p-4">
                <button
                    onClick={onCustomerSearch}
                    className="mb-3 flex w-full items-center gap-2 rounded-lg border border-dashed px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-primary/50 hover:text-foreground"
                >
                    {customer ? (
                        <>
                            <div className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                {customer.name.charAt(0)}
                            </div>
                            <span className="flex-1 truncate text-left">{customer.name}</span>
                            <span className="text-xs text-muted-foreground">Cambiar</span>
                        </>
                    ) : (
                        <>
                            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Asignar cliente (opcional)</span>
                        </>
                    )}
                </button>

                <div className="mb-4 flex items-center justify-between">
                    <span className="text-sm font-medium text-muted-foreground">Total</span>
                    <span className="text-2xl font-bold tabular-nums">
                        ${total.toFixed(2)}
                    </span>
                </div>

                <button
                    onClick={onCheckout}
                    disabled={cart.length === 0}
                    className="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Cobrar ${total.toFixed(2)}
                </button>
            </div>
        </div>
    );
}
