# Customisation

Spike provides several options for developers to customise the billing portal to match their application's branding and user experience.

## Theme & Branding
- Configure the portal's primary color, logo, and favicon in `config/spike.php` under the `theme` key:
  ```php
  'theme' => [
      'color' => '#047857', // Primary color
      'logo_url' => '/vendor/spike/images/spike-logo-white.png',
      'favicon_url' => '/vendor/spike/images/spike-favicon.png',
      'display_avatar' => true, // Show user avatar
      'display_empty_credit_balances' => true, // Show zero balances
  ],
  ```
- You can hide the logo or avatar by setting their values to `null` or `false`.

## Portal Path & Middleware
- Change the billing portal URL path via the `path` key (e.g., `/billing`).
- Restrict access using the `middleware` array (default: `['web', 'auth']`).

## Return URL
- Set the `return_url` to control where users go after leaving the billing portal.

## Customising Avatar
- Override how the avatar is resolved by calling `Spike::resolveAvatarUsing($callback)` in your `AppServiceProvider`:
  ```php
  use Opcodes\Spike\Facades\Spike;

  Spike::resolveAvatarUsing(function ($user) {
      return $user->profile_photo_url;
  });
  ```

## Blade Views & Components
- The billing portal uses Blade views and Livewire components in `resources/views`.
- You can publish and override these views to further customise the UI:
  ```shell
  php artisan vendor:publish --tag=spike-views
  ```
- Customise components in `resources/views/components` for advanced branding.

## Credit Types & Features
- Add or modify credit types, products, and subscription plans in `config/spike.php` to match your business model.

## Example: Minimal Customisation
```php
// config/spike.php
'theme' => [
    'color' => '#1D4ED8',
    'logo_url' => '/images/my-logo.png',
    'favicon_url' => '/images/my-favicon.png',
    'display_avatar' => false,
],
'path' => 'account/billing',
'return_url' => '/dashboard',
``` 