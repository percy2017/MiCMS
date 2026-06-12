# Plan: Tipo de venta "Directa" vs "Suscripción" en POS

## Objetivo
Agregar selector de tipo de venta (Directa / Suscripción) en el POS. En suscripción se muestran 2 inputs (título + fecha de vencimiento) y se envían como `meta_data` a WooCommerce.

## Referencia funcional
Las órdenes existentes #1389 y #1390 ya tienen este patrón en `meta_data`:
```json
"_is_pos_subscription": "true",
"_subscription_title": "1 mes de mastv",
"_subscription_end_date": "2026-07-08",
"_subscription_start_date": "2026-06-08",
"_sale_date": "2026-06-08"
```

## Cambios a aplicar

### 1. Backend — `PosWooController::checkout()` (validar)
**Archivo**: `Modules/PosWoo/Http/Controllers/PosWooController.php:105-115`

Agregar al `validate()`:
```php
'type' => 'sometimes|in:direct,subscription',
'subscription_title' => 'required_if:type,subscription|nullable|string|max:255',
'subscription_start_date' => 'required_if:type,subscription|nullable|date',
'subscription_end_date' => 'required_if:type,subscription|nullable|date|after:subscription_start_date',
```

Y pasar al `createOrder()`:
```php
'type' => $validated['type'] ?? 'direct',
'subscription_title' => $validated['subscription_title'] ?? null,
'subscription_start_date' => $validated['subscription_start_date'] ?? null,
'subscription_end_date' => $validated['subscription_end_date'] ?? null,
```

### 2. Backend — `WooCommerceService::createOrder()` (meta_data condicional)
**Archivo**: `Modules/PosWoo/Services/WooCommerceService.php:198-260`

Cambiar firma PHPDoc:
```php
/**
 * @param  array{items: array, customer?: array, payment_method: string, payment_method_title: string, note?: string, type?: string, subscription_title?: string|null, subscription_start_date?: string|null, subscription_end_date?: string|null}  $data
 */
public function createOrder(array $data): array
```

Después del bloque `meta_data` existente (líneas 219-225), agregar:
```php
if (($data['type'] ?? 'direct') === 'subscription') {
    $orderData['meta_data'][] = ['key' => '_is_pos_subscription', 'value' => 'true'];
    $orderData['meta_data'][] = ['key' => '_subscription_title', 'value' => (string) $data['subscription_title']];
    $orderData['meta_data'][] = ['key' => '_subscription_start_date', 'value' => (string) $data['subscription_start_date']];
    $orderData['meta_data'][] = ['key' => '_subscription_end_date', 'value' => (string) $data['subscription_end_date']];
    $orderData['meta_data'][] = ['key' => '_sale_date', 'value' => (string) $data['subscription_start_date']];
}
```

### 3. Frontend — `Modules/PosWoo/resources/js/Pages/Dashboard.tsx`

**3.1. Nuevos states** (después de los existentes, línea ~48):
```tsx
const [saleType, setSaleType] = useState<'direct' | 'subscription'>('direct');
const [subscriptionTitle, setSubscriptionTitle] = useState('');
const [subscriptionStartDate, setSubscriptionStartDate] = useState(() => new Date().toISOString().slice(0, 10));
const [subscriptionEndDate, setSubscriptionEndDate] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() + 30);
    return d.toISOString().slice(0, 10);
});
```

**3.2. Resetear al cobrar exitosamente** (en `submitCheckout` después de `setCart([])`):
```tsx
setSaleType('direct');
setSubscriptionTitle('');
setSubscriptionStartDate(new Date().toISOString().slice(0, 10));
const d = new Date(); d.setDate(d.getDate() + 30);
setSubscriptionEndDate(d.toISOString().slice(0, 10));
```

**3.3. UI en el footer del `CartPanel`** (después del select de método de pago, antes del total):

```tsx
<div className="rounded-md border bg-muted/30 p-2">
    <p className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground mb-1.5">Tipo de venta</p>
    <div className="flex gap-1.5">
        <button
            type="button"
            onClick={() => setSaleType('direct')}
            className={cn(
                "flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition",
                saleType === 'direct' ? "bg-primary text-primary-foreground" : "bg-background text-muted-foreground hover:bg-muted"
            )}
        >
            Directa
        </button>
        <button
            type="button"
            onClick={() => setSaleType('subscription')}
            className={cn(
                "flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition",
                saleType === 'subscription' ? "bg-primary text-primary-foreground" : "bg-background text-muted-foreground hover:bg-muted"
            )}
        >
            Suscripción
        </button>
    </div>
    {saleType === 'subscription' && (
        <div className="mt-2 space-y-1.5">
            <input
                type="text"
                value={subscriptionTitle}
                onChange={(e) => setSubscriptionTitle(e.target.value)}
                placeholder="Título (ej: 1 mes de mastv)"
                className="h-8 w-full rounded border border-input bg-background px-2 text-xs"
            />
            <div className="grid grid-cols-2 gap-1.5">
                <div>
                    <label className="block text-[9px] uppercase tracking-wide text-muted-foreground">Inicio</label>
                    <input
                        type="date"
                        value={subscriptionStartDate}
                        onChange={(e) => setSubscriptionStartDate(e.target.value)}
                        className="h-8 w-full rounded border border-input bg-background px-2 text-xs"
                    />
                </div>
                <div>
                    <label className="block text-[9px] uppercase tracking-wide text-muted-foreground">Vence</label>
                    <input
                        type="date"
                        value={subscriptionEndDate}
                        onChange={(e) => setSubscriptionEndDate(e.target.value)}
                        className="h-8 w-full rounded border border-input bg-background px-2 text-xs"
                    />
                </div>
            </div>
        </div>
    )}
</div>
```

**3.4. Pasar al `openConfirmDialog` y `submitCheckout`**:

En `openConfirmDialog(paymentMethod, paymentMethodTitle)`:
```tsx
function openConfirmDialog(paymentMethod: string, paymentMethodTitle: string) {
    if (!customer) {
        setError('Debes asignar un cliente antes de cobrar.');
        return;
    }
    if (saleType === 'subscription') {
        if (!subscriptionTitle.trim()) {
            setError('Debes asignar un título a la suscripción.');
            return;
        }
        if (subscriptionEndDate <= subscriptionStartDate) {
            setError('La fecha de vencimiento debe ser posterior a la fecha de inicio.');
            return;
        }
    }
    setPendingCheckout({ method: paymentMethod, title: paymentMethodTitle });
    setConfirmOpen(true);
}
```

En `submitCheckout()` body del fetch, agregar al body JSON:
```tsx
type: saleType,
subscription_title: saleType === 'subscription' ? subscriptionTitle : undefined,
subscription_start_date: saleType === 'subscription' ? subscriptionStartDate : undefined,
subscription_end_date: saleType === 'subscription' ? subscriptionEndDate : undefined,
```

**3.5. Preview en el Dialog de confirmación** (después del bloque de método de pago, antes del total):
```tsx
{saleType === 'subscription' && (
    <div className="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-xs">
        <p className="font-semibold text-primary">Suscripción</p>
        <p className="text-muted-foreground">{subscriptionTitle}</p>
        <p className="text-muted-foreground">
            {new Date(subscriptionStartDate).toLocaleDateString('es')} → {new Date(subscriptionEndDate).toLocaleDateString('es')}
        </p>
    </div>
)}
```

### 4. Tests Pest
**Archivo nuevo**: `tests/Feature/Modules/PosWoo/SubscriptionTest.php`

5 tests:
1. `direct sale does not include subscription meta_data` — POST sin `type` → orden sin `_is_pos_subscription`.
2. `subscription sale includes _is_pos_subscription=true` — POST con `type=subscription` → meta_data presente.
3. `subscription sale includes _subscription_title` — POST con título → meta_data con el título.
4. `subscription sale includes _subscription_end_date` — POST con fechas → meta_data con end_date.
5. `subscription with end_date <= start_date returns 422` — POST con fechas inválidas → validation error.

Los tests deben mockear el cliente WC con `Http::fake()` o un wrapper. Patrón similar al de `SearchCustomersTest.php`.

## Verificación
1. `npm run build` ✓
2. `php artisan test --compact` → 244+5 = 249/249 ✓
3. `vendor/bin/pint --dirty` ✓
4. Test manual con Playwright:
   - Venta directa "Mastv 1 mes" → WC sin `_is_pos_subscription`.
   - Venta suscripción "Mastv 3 meses" con título + fechas → WC con los 5 meta_data.
5. Hard reload con `?v=N` para evitar caché.

## Archivos a modificar (4)
- `Modules/PosWoo/Http/Controllers/PosWooController.php` (validación)
- `Modules/PosWoo/Services/WooCommerceService.php` (meta_data condicional)
- `Modules/PosWoo/resources/js/Pages/Dashboard.tsx` (UI + state + envío + preview)
- `tests/Feature/Modules/PosWoo/SubscriptionTest.php` (nuevo, 5 tests)
