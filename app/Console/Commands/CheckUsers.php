<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CheckUsers extends Command
{
    protected $signature = 'users:check';
    protected $description = 'Check users in database';

    public function handle()
    {
        $users = User::all(['name', 'username', 'email']);
        
        $this->info('Usuarios en la base de datos:');
        foreach ($users as $user) {
            $this->line("{$user->name} - {$user->username} - {$user->email}");
        }
        
        return 0;
    }
}
