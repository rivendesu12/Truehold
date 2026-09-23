<?php

namespace App\Console\Commands;

use App\Support\ZooplaLeadsSettings;
use Illuminate\Console\Command;

/**
 * The same as the password box on /admin/zoopla-leads, for when nobody with
 * an admin login is at hand. Asks without echoing, stores it encrypted.
 */
class SetZooplaLeadsPassword extends Command
{
    protected $signature = 'zoopla:password';

    protected $description = 'Save the Zoho mailbox password used by zoopla:leads';

    public function handle(): int
    {
        $password = trim((string) $this->secret('Zoho password for ' . config('services.zoopla_leads.username') . ' (hidden as you type)'));

        if ($password === '') {
            $this->warn('Nothing entered; nothing saved.');

            return self::FAILURE;
        }

        ZooplaLeadsSettings::savePassword($password);
        // Written as root here; the web page (www-data) must still be able to read it.
        @chown(ZooplaLeadsSettings::path(), 'www-data');
        @chgrp(ZooplaLeadsSettings::path(), 'www-data');

        $this->info('Saved. Checking the mailbox now...');

        return $this->call('zoopla:leads');
    }
}
