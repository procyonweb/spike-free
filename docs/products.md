# Products

## User Story

Products allow users to make one-off purchases, such as buying additional credits or feature packs, without committing to a subscription. This gives users flexibility to top up their account as needed, and lets businesses offer a variety of purchase options beyond recurring plans.

## Technical Documentation

### Product Definition
- Products are defined in `config/spike.php` under `products`.
- Each product has an ID, name, description, features, price, and provides (e.g., credits).
- Products can be archived to hide them from the UI.

### Storage
- Products are not stored as Eloquent models by default, but are loaded from config.
- Purchases are tracked via `Cart`, `CartItem`, and `ProductPurchase` models.
- Each purchase records the product, quantity, and purchase date.

### Purchase Flow
- Users add products to their cart and checkout via Stripe or Paddle.
- On successful payment, the purchased products' benefits (e.g., credits) are provided to the user.
- Purchases are tracked and can be viewed in the billing portal.

### How Products Provide Benefits
- Each product defines what it provides (usually credits) via the `provides` array.
- On purchase, the benefits are granted to the user and recorded as `CreditTransaction`.
- Expiry can be set for credits provided by products.

### Edge Cases & Intricacies
- Duplicate purchases are allowed; quantities are tracked.
- Product prices and availability are validated against Stripe/Paddle.
- Archived products are hidden from the UI but can be referenced in code.

### API/Code Usage
- Use the `products()` and `findProduct()` methods to access product definitions:
  ```php
  $products = Spike::products();
  $product = Spike::findProduct('product_id');
  ```
- Purchases are managed via the billing portal UI and cart system. 