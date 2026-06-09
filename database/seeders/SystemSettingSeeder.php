<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'billing.default_commission_pct', 'value' => '10.00',  'description' => 'Default platform commission percentage applied to new invoices.'],
            ['key' => 'billing.currency',               'value' => 'PHP',    'description' => 'Platform default currency for all transactions.'],
            ['key' => 'app.maintenance_mode',           'value' => 'false',  'description' => 'Set to true to put the platform in maintenance mode.'],
            ['key' => 'app.max_upload_size_mb',         'value' => '10',     'description' => 'Maximum file upload size in megabytes for resources and attachments.'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        $this->command->info('System settings seeded.');
    }
}
