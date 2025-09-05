<?php

namespace Opcodes\Spike\Tests;

use Asantibanez\LivewireCharts\LivewireChartsServiceProvider;
use LivewireUI\Modal\LivewireModalServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\CashierServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Opcodes\Spike\SpikeServiceProvider;

#[\AllowDynamicProperties]
class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Opcodes\\Spike\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        config(['spike.credit_types' => [
            [
                'id' => 'credits',
                'translation_key' => 'spike::translations.credits',
            ],
            [
                'id' => 'sms',
                'translation_key' => 'sms',
            ],
        ]]);
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            LivewireModalServiceProvider::class,
            LivewireChartsServiceProvider::class,
            CashierServiceProvider::class,
            SpikeServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $testingMigrationFileNames = [
            '01_create_users_table.php',
            '02_create_password_resets_table.php',
            // '03_create_customer_columns.php',
            // '04_create_subscriptions_table.php',
            // '05_create_subscription_items_table.php',
        ];

        foreach ($testingMigrationFileNames as $migrationFileName) {
            $migration = include __DIR__."/migrations/$migrationFileName";
            $migration->up();
        }

        config()->set('app.key', 'base64:z1qfUazFM1lzfPy5sFcm8oykb2pQeS0/wuX79GdL3zI=');
        $userClass = testBillableClass();
        config(['spike.billable_models' => [$userClass]]);
        Cashier::useCustomerModel($userClass);
        SpikeServiceProvider::shouldRegisterVendorMigrations();
    }
}
