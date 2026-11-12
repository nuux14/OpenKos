<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('site_name', 'MariaCloud Property Management');
        Setting::set('country_code', 'ET');
        Setting::set('locale', 'en');
        Setting::set('currency', 'ETB');
        Setting::set('supported_currencies', ['ETB', 'USD']);
        Setting::set('timezone', 'Africa/Addis_Ababa');
        Setting::set('lease_id_prefix', 'MCL');
        Setting::set('invoice_id_prefix', 'MCI');
    }
}
