# Credit System

## User Story

Credits allow users to prepay for usage-based features (such as API calls, SMS, or other metered services). Users can purchase credit packs or receive monthly credits as part of their subscription. As they use features, credits are deducted from their balance. This system gives users flexibility and transparency, letting them see their remaining balance and top up as needed.

## Technical Documentation

### Credit Types
- Multiple credit types are supported (e.g., `credits`, `sms`).
- Defined in `config/spike.php` under `credit_types`.
- Each type has an ID, translation key, and optional icon.

### Storage
- Credits are tracked per billable model (e.g., User).
- Main model: `CreditTransaction` (table: `spike_credit_transactions`).
- Each transaction records:
  - `billable_id`, `billable_type`
  - `type` (subscription, product, adjustment, usage)
  - `credit_type` (e.g., 'credits')
  - `credits` (positive or negative)
  - `expires_at` (optional)
  - `notes`

### How Credits Are Provided
- **Via Subscriptions:**
  - Each subscription plan can provide monthly credits (see `provides_monthly` in config).
  - On renewal, new credits are added and previous ones are expired/prorated.
- **Via Products:**
  - One-off products can provide credits (see `provides` in product config).
  - Credits from products can have expiry.

### How Credits Are Used
- Credits are deducted when users consume metered features.
- Usage is grouped daily by default (configurable via `group_credit_spend_daily`).
- Negative balances can be allowed via configuration or code.

### Credit Balance Calculation
- The current balance is the sum of all non-expired credit transactions for each type.
- Expiry and proration are handled automatically.
- Balances are cached for performance.

### Relationship to Subscriptions/Products
- Subscriptions provide recurring credits (renewed monthly/yearly).
- Products provide one-off credits.
- Both use the same transaction and balance system.

### API/Code Usage
- Use the `credits()` method on billable models to check, add, or spend credits:
  ```php
  $user->credits('credits')->balance();
  $user->credits('credits')->add(100);
  $user->credits('credits')->spend(10);
  ```
- Credits can be managed via the billing portal UI as well. 