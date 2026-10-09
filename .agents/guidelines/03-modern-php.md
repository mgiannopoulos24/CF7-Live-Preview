# Strict Typing & Modern PHP

> PHP 8.3+ features, type declarations, and modern syntax.

---

## File Header (Every PHP File)

```php
<?php
declare(strict_types=1);

namespace {PluginName};

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
```

---

## PHP 8.3+ Features (Use Everywhere)

### Constructor Promotion

```php
final class Stock_Service {

	public function __construct(
		private readonly int $low_threshold = 5,
		private readonly int $cache_ttl = HOUR_IN_SECONDS,
	) {}
}
```

### Named Arguments

For readability with WooCommerce queries:

```php
$orders = wc_get_orders( [
	'status'  => 'processing',
	'limit'   => 50,
	'return'  => 'ids',
	'orderby' => 'date',
	'order'   => 'DESC',
] );
```

### Match Expressions

Replace `switch` statements:

```php
$label = match ( $status ) {
	'low'      => __( 'Low Stock', '{plugin-name}' ),
	'out'      => __( 'Out of Stock', '{plugin-name}' ),
	'backorder'=> __( 'On Backorder', '{plugin-name}' ),
	default    => __( 'In Stock', '{plugin-name}' ),
};
```

### Union Types

```php
public function find_product( int $id ): \WC_Product|null {
	$product = wc_get_product( $id );
	return $product instanceof \WC_Product ? $product : null;
}
```

### Nullsafe Operator

```php
// Instead of: $name = $product ? $product->get_name() : null;
$name = wc_get_product( $id )?->get_name();
```

---

## PHP 8.4+ Features (When Minimum Is 8.4)

### Property Hooks

### Backed Enums

For fixed status sets:

```php
<?php
declare(strict_types=1);

namespace {PluginName}\Enums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stock status enum.
 *
 * @since 1.0.0
 * @requires PHP 8.1
 */
enum Stock_Status: string {

	case IN_STOCK    = 'instock';
	case OUT_OF_STOCK = 'outofstock';
	case ON_BACKORDER = 'onbackorder';
	case LOW_STOCK    = 'lowstock';

	/**
	 * Get human-readable label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::IN_STOCK     => __( 'In Stock', '{plugin-name}' ),
			self::OUT_OF_STOCK => __( 'Out of Stock', '{plugin-name}' ),
			self::ON_BACKORDER => __( 'On Backorder', '{plugin-name}' ),
			self::LOW_STOCK    => __( 'Low Stock', '{plugin-name}' ),
		};
	}

	/**
	 * Check if purchasable.
	 */
	public function is_purchasable(): bool {
		return match ( $this ) {
			self::IN_STOCK, self::LOW_STOCK, self::ON_BACKORDER => true,
			self::OUT_OF_STOCK => false,
		};
	}
}
```

### Readonly Properties

For immutable value objects:

```php
final class Stock_Summary {

	public function __construct(
		public readonly int $product_id,
		public readonly int $quantity,
		public readonly Stock_Status $status,
		public readonly \DateTimeImmutable $checked_at,
	) {}
}
```

### Asymmetric Visibility (8.4+)

```php
class Config {
	public function __construct(
		public private(set) string $api_key,
		public readonly string $endpoint,
	) {}
}
```

---

## Type Documentation

**Always type**: parameters, return types, class properties.

**Document complex types** in PHPDoc where PHP's type system can't express them:

```php
/**
 * Get stock data for multiple products.
 *
 * @param int[] $product_ids Product IDs.
 * @return array<int, array{quantity: int, status: string, sku: string}>
 */
public function get_bulk_stock( array $product_ids ): array {
	// ...
}
```
