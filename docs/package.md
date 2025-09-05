# Spike Billing Package

Spike is a Laravel package for managing billing, subscriptions, credits, and one-off product purchases in SaaS applications. It provides a customizable billing portal, integrates with Stripe and Paddle, and supports flexible credit systems and product offerings.

## What is Spike for?

Spike helps SaaS developers quickly implement robust billing, subscription, and credit management features. It is designed for teams who want to:

- Offer subscription plans (monthly/yearly) with recurring billing
- Sell one-off products (e.g., credit packs)
- Track and manage user credits (API calls, SMS, etc.)
- Integrate with Stripe or Paddle for payments
- Provide a self-serve billing portal for customers
- Customize the billing experience to match their brand

## Technical Details

- **Framework:** Laravel 10+
- **Main Packages:**
  - [laravel/cashier](https://laravel.com/docs/10.x/billing) (Stripe integration)
  - [laravel/paddle](https://laravel.com/docs/10.x/cashier-paddle) (Paddle integration)
  - Livewire (for interactive billing portal UI)
- **Database:** Eloquent ORM, migrations for all billing entities
- **API Resources:** Eloquent API Resources for API responses
- **Testing:** PestPHP 3

## Who is it for?

- SaaS product teams using Laravel
- Developers who want to avoid building billing logic from scratch
- Teams needing flexible credit and product systems

## Main Features

- [Credit System](./credits.md): Flexible, multi-type credit management for usage-based billing
- [Subscriptions](./subscriptions.md): Recurring plans, proration, renewal, and provider sync
- [Products](./products.md): One-off purchases, credit packs, and product management
- [Customisation](./customisation.md): Theme, branding, and portal customisation for developers

## Related Docs

- [Credit System](./credits.md)
- [Subscriptions](./subscriptions.md)
- [Products](./products.md)
- [Customisation](./customisation.md) 