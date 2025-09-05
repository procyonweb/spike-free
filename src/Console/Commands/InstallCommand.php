<?php

namespace Opcodes\Spike\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Opcodes\Spike\Exceptions\MissingPaymentProviderException;
use Opcodes\Spike\Facades\Spike;
use Opcodes\Spike\PaymentProvider;

class InstallCommand extends Command
{
    protected $signature = 'spike:install {--teams : Teams support (for Jetstream applications)}';

    protected $description = 'Set up all resources necessary for the Spike package.';

    public function handle()
    {
        if (file_exists(base_path('bootstrap/cache/config.php'))) {
            $this->error('We have detected that your Laravel configuration is cached. Please clear the config cache (`php artisan config:clear`) and try again.');
            return Command::FAILURE;
        }

        $paymentProvider = Spike::paymentProvider();

        if ($paymentProvider->isValid()) {
            $this->info("We have detected <options=bold,underscore>{$paymentProvider->name()}</> as your payment provider.");
            $this->line("We will now set up Spike to be used with {$paymentProvider->name()}.");
            $this->newLine();
        } else {
            $this->error("We could not detect a payment provider.");
            $this->line("\nPlease install one of these Composer packages, depending on your preferred payment provider and then try again.");
            $this->newLine();
            $stripeProviderPackage = PaymentProvider::Stripe->requiredComposerPackage();
            $this->line("- Stripe:");
            $this->line("  <options=bold>composer require $stripeProviderPackage</>");
            $this->newLine();
            $paddleProviderPackage = PaymentProvider::Paddle->requiredComposerPackage();
            $this->line("- Paddle:");
            $this->line("  <options=bold>composer require $paddleProviderPackage</>");
            $this->newLine();
            return Command::FAILURE;
        }

        if (file_exists(config_path('cashier.php'))) {
            $configContents = file_get_contents(config_path('cashier.php'));
            $printError = function (string $currentPaymentProvider, string $configurationPaymentProvider) {
                $this->error('Incompatible Cashier configuration file exists already.');
                $this->line("We have detected that you have previously published the Cashier ({$configurationPaymentProvider}) configuration file.");
                $this->line("If you would like to use {$currentPaymentProvider} as your payment provider with Spike, please run this command to force-publish the Cashier configuration file:");
                $this->newLine();
                $this->line('php artisan vendor:publish --tag=cashier-config --force');
                $this->newLine();
                $this->line('Afterwards, please run this command again.');
            };

            if ($paymentProvider === PaymentProvider::Stripe && str_contains($configContents, 'Paddle')) {
                $printError(PaymentProvider::Stripe->name(), PaymentProvider::Paddle->name());
                return Command::FAILURE;
            } elseif ($paymentProvider === PaymentProvider::Paddle && str_contains($configContents, 'Stripe')) {
                $printError(PaymentProvider::Paddle->name(), PaymentProvider::Stripe->name());
                return Command::FAILURE;
            }
        }

        $supportTeams = (bool) $this->option('teams');

        $this->call('vendor:publish', ['--tag' => 'cashier-config']);
        $this->call('vendor:publish', ['--tag' => 'spike-config']);
        $this->call('vendor:publish', ['--tag' => 'spike-migrations']);
        $this->call('vendor:publish', ['--tag' => 'livewire-charts:public']);

        if ($supportTeams) {
            $this->line('You have selected the <options=bold,underscore>Teams</> option. We will now adjust migrations and configuration to support teams.');
            $this->migrateConfigurationToTeams();
        }

        // Now let's set up the Stripe's environment variables.
        match ($paymentProvider) {
            PaymentProvider::Stripe => $this->setupStripeEnvironmentVariables(),
            PaymentProvider::Paddle => $this->setupPaddleEnvironmentVariables(),
            default => throw new MissingPaymentProviderException(),
        };

        $this->printNextSteps();

        return Command::SUCCESS;
    }

    protected function addToEnv($name, $value)
    {
        $newValue = "$name=$value";
        $envFile = file_get_contents(base_path('.env'));

        if (Str::contains($envFile, "$name=")) {
            $envFile = preg_replace("/^$name=.*$/m", $newValue, $envFile);
        } else {
            $envFile .= $newValue . PHP_EOL;
        }

        file_put_contents(base_path('.env'), $envFile);
    }

    private function searchAndReplace(string $search, string $replace, string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $fileContents = file_get_contents($filePath);
        $fileContents = str_replace($search, $replace, $fileContents);
        file_put_contents($filePath, $fileContents);
    }

    private function migrateConfigurationToTeams(): void
    {
        // Migrations
        $this->searchAndReplace(
            "Schema::table('users'",
            "Schema::table('teams'",
            database_path('migrations/2022_05_01_000001_create_customer_columns.php')
        );

        $this->searchAndReplace(
            "unsignedBigInteger('user_id')",
            "unsignedBigInteger('team_id')",
            database_path('migrations/2022_05_01_000002_create_subscriptions_table.php')
        );

        $this->searchAndReplace(
            "index(['user_id', 'stripe_status']",
            "index(['team_id', 'stripe_status']",
            database_path('migrations/2022_05_01_000002_create_subscriptions_table.php')
        );

        // Config
        $this->searchAndReplace(
            "'App\Models\User',",
            "'App\Models\Team',",
            config_path('spike.php')
        );
    }

    /** @noinspection LaravelFunctionsInspection */
    private function setupStripeEnvironmentVariables()
    {
        if (empty(env('STRIPE_KEY'))) {
            $this->line('We could not find the Stripe API <options=bold,underscore>Publishable key</> in your project\'s configuration. Let\'s add it now.');
            $this->line('You can find the API <options=bold,underscore>Publishable key</> by visiting <href=https://dashboard.stripe.com/apikeys>https://dashboard.stripe.com/apikeys</>');

            $stripeKey = $this->ask('Enter Stripe <options=bold,underscore>Publishable key</> <fg=gray>(leave empty to skip)</>');
            $this->addToEnv('STRIPE_KEY', $stripeKey);
        }

        if (empty(env('STRIPE_SECRET'))) {
            $this->line('We could not find the Stripe API <options=bold,underscore>Secret key</> in your project\'s configuration. Let\'s add it now.');
            $this->line('You can find the API <options=bold,underscore>Secret key</> by visiting <href=https://dashboard.stripe.com/apikeys>https://dashboard.stripe.com/apikeys</>');

            $stripeSecret = $this->ask('Enter Stripe <options=bold,underscore>Secret key</> <fg=gray>(leave empty to skip)</>');
            $this->addToEnv('STRIPE_SECRET', $stripeSecret);
        }

        if (empty(env('STRIPE_WEBHOOK_SECRET'))) {
            $this->line('We could not find the Stripe <options=bold,underscore>Webhook secret</> in your project\'s configuration. Let\'s add it now.');
            $this->line('You can learn more about <options=bold,underscore>Webhook secret</> by visiting <href=https://laravel.com/docs/9.x/billing#verifying-webhook-signatures>https://laravel.com/docs/9.x/billing#verifying-webhook-signatures</>');

            $webhookSecret = $this->ask('Enter Stripe <options=bold,underscore>Webhook secret</> <fg=gray>(leave empty to skip)</>');
            $this->addToEnv('STRIPE_WEBHOOK_SECRET', $webhookSecret);
        }
    }

    /** @noinspection LaravelFunctionsInspection */
    private function setupPaddleEnvironmentVariables(): void
    {
        if (empty(env('PADDLE_SELLER_ID'))) {
            $this->line('We could not find the Paddle <options=bold,underscore>Seller ID</> in your project\'s configuration. Let\'s add it now.');
            $this->line('You can find the <options=bold,underscore>Seller ID</> by visiting <href=https://vendors.paddle.com/authentication-v2>https://vendors.paddle.com/authentication-v2</> (top-left corner)');

            $paddleSellerId = $this->ask('Enter Paddle <options=bold,underscore>Seller ID</> <fg=gray>(leave empty to skip)</>');
            $this->addToEnv('PADDLE_SELLER_ID', $paddleSellerId);
        }

        if (empty(env('PADDLE_API_KEY'))) {
            $this->line('We could not find the Paddle <options=bold,underscore>API key</> in your project\'s configuration. Let\'s add it now.');
            $this->line('You can find or create the <options=bold,underscore>API key</> by visiting <href=https://vendors.paddle.com/authentication-v2>https://vendors.paddle.com/authentication-v2</>');

            $paddleApiKey = $this->ask('Enter Paddle <options=bold,underscore>API key</> <fg=gray>(leave empty to skip)</>');
            $this->addToEnv('PADDLE_API_KEY', $paddleApiKey);
        }

        if (empty(env('PADDLE_WEBHOOK_SECRET'))) {
            $this->line('We could not find the Paddle <options=bold,underscore>Webhook secret</> in your project\'s configuration. Let\'s add it now.');
            $this->line('You can learn more about <options=bold,underscore>Webhook secret</> by visiting <href=https://developer.paddle.com/webhooks/signature-verification#get-secret-key>https://developer.paddle.com/webhooks/signature-verification#get-secret-key</>');

            $webhookSecret = $this->ask('Enter Paddle <options=bold,underscore>Webhook secret</> <fg=gray>(leave empty to skip)</>');
            $this->addToEnv('PADDLE_WEBHOOK_SECRET', $webhookSecret);
        }

        if (is_null(env('PADDLE_SANDBOX'))) {
            $this->line('Would you like to start using Paddle in <options=bold,underscore>Sandbox</> mode?');

            $sandbox = $this->confirm('Use Paddle in Sandbox mode?', true);
            $this->addToEnv('PADDLE_SANDBOX', $sandbox ? 'true' : 'false');
        }
    }

    private function printNextSteps()
    {
        $paymentProvider = Spike::paymentProvider();

        $this->info('### All done! ###');
        $this->line('');
        $this->line('Next steps:');
        $this->line('- 1. Run <options=bold>`php artisan migrate`</> to set up the database for Spike.');
        $this->line('- 2. Configure Spike purchases and subscriptions by editing the <options=bold>config/spike.php</> file.');
        $this->line("- 3. Run <options=bold>`php artisan spike:verify`</> to verify your integration with {$paymentProvider->name()}.");
        $this->line('- 4. Visit your new billing page via the <href='.route('spike.usage').'>/billing</> route in the browser.');
        $this->line('');
        $this->line('You can find the full documentation at <href=https://spike.opcodes.io/docs>https://spike.opcodes.io/docs</>');
        $this->line('');
    }
}
