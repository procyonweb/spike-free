# Subscriptions

## User Story

Subscriptions allow users to access premium features and receive recurring benefits (such as monthly credits) by paying a regular fee. Users can choose from different plans (monthly or yearly), upgrade or downgrade as needed, and manage their subscription through the billing portal. This helps SaaS businesses generate predictable revenue and offer value-based pricing.

## Technical Documentation

### Subscription Plans
- Defined in `config/spike.php` under `subscriptions`.
- Each plan has an ID, name, description, features, price, and monthly provides (e.g., credits).
- Supports both monthly and yearly billing periods.

### Storage
- Subscriptions are stored in provider-specific tables:
  - Stripe: `stripe_subscriptions`, `stripe_subscription_items`
  - Paddle: `paddle_subscriptions`, `paddle_subscription_items`
- Each subscription links to the billable model (e.g., User) and tracks status, renewal date, and items.

### Sync with Stripe/Paddle
- Integrates with [Laravel Cashier](https://laravel.com/docs/10.x/billing) for Stripe and [Cashier Paddle](https://laravel.com/docs/10.x/cashier-paddle) for Paddle.
- Subscriptions are created, updated, and cancelled via provider APIs.
- Webhooks are handled to sync status and renewals.
- Proration is handled when switching plans (unused credits are prorated and expired).

### Renewal & Credits
- On renewal, new credits are provided and previous ones are expired/prorated.
- Renewal dates are tracked and exposed via API and UI.
- Free plans are supported and can be switched to at any time.

### Edge Cases & Intricacies
- Switching plans triggers proration and credit adjustment.
- Cancelling a subscription can be immediate or at period end.
- Resuming a cancelled subscription restores access and benefits.
- Handles incomplete payments, past due status, and provider-specific errors.
- Supports promotion codes and discounts.

### API/Code Usage
- Use the `subscriptionManager()` on billable models to manage subscriptions:
  ```php
  $user->subscribeTo($plan);
  $user->cancelSubscription();
  $user->resumeSubscription();
  $user->currentSubscriptionPlan();
  ```
- Subscriptions can be managed via the billing portal UI as well. 