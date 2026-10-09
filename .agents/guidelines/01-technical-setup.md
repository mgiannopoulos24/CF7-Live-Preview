# Technical Setup

> PHP requirements, naming conventions, and plugin registry.

---

## PHP Requirements

**Minimum**: PHP 8.3

**Leverage modern syntax**: constructor promotion, named arguments, `match`, union types, nullsafe operator, `enum` (8.1+), `readonly` (8.1+), intersection types (8.1+), `readonly` classes (8.2+), DNF types (8.2+), property hooks (8.4+), asymmetric visibility (8.4+).

---

## Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Plugin Slug | Short, unique identifier | `{SLUG}` |
| Global Constants | `{PLUGIN_NAME_}` prefix (only FILE & PATH) | `{PLUGIN_NAME}_FILE`, `{PLUGIN_NAME}_PATH` |
| Class Constants | Inside Plugin class | `Plugin::VERSION`, `Plugin::SLUG` |
| Functions | `{plugin_name_}` prefix, snake_case | `{plugin_name}_get_stock()` |
| Classes | `{PluginName}\` namespace, PascalCase | `{PluginName}\Stock_Manager` |
| Methods/Props | snake_case | `$this->product_id` |
| Hooks | `{plugin-name}/` prefix | `do_action( '{plugin-name}/stock_updated' )` |
| REST Routes | `{plugin-name}/v1` namespace | `register_rest_route( '{plugin-name}/v1', ... )` |
| DB Tables | `{$wpdb->prefix}{plugin_name}_` | `wp_{plugin_name}_sync_log` |
| Script Handles | `{plugin-name}-{purpose}` | `{plugin-name}-admin` |
| Nonce Actions | `Plugin::PREFIX . '{action}'` | `{plugin_name}_save_settings` |

---

## Composer PSR-4

```json
"autoload": {
	"psr-4": {
		"{PluginName}\\": "includes/"
	}
}
```

---

## Standards

- WordPress Coding Standards
- Tabs for indentation
- Braces on same line
