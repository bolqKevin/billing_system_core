<?php

namespace App\Console\Commands\Essential;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\Company;
use App\Models\Setting;
use App\Models\SystemInfo;
use App\Models\Customer;
use App\Models\ProductService;

class InsertInitialData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:insert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert initial data into the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Inserting initial data...');

        // Insert roles
        $this->info('Creating roles...');
        $adminRole = Role::create([
            'name' => 'Administrator',
            'description' => 'Full system access',
            'status' => 'Active',
        ]);

        $invoiceUserRole = Role::create([
            'name' => 'Invoice User',
            'description' => 'Access to invoicing functions',
            'status' => 'Active',
        ]);

        // Insert permissions
        $this->info('Creating permissions...');
        $permissions = [
            // Customer permissions
            ['name' => 'create_customer', 'description' => 'Create new customers', 'module' => 'Customers'],
            ['name' => 'view_customer', 'description' => 'View customer information', 'module' => 'Customers'],
            ['name' => 'update_customer', 'description' => 'Update customer information', 'module' => 'Customers'],
            ['name' => 'delete_customer', 'description' => 'Delete/disable customers', 'module' => 'Customers'],
            
            // Product permissions
            ['name' => 'create_product', 'description' => 'Create new products/services', 'module' => 'Products'],
            ['name' => 'view_product', 'description' => 'View product/service information', 'module' => 'Products'],
            ['name' => 'update_product', 'description' => 'Update product/service information', 'module' => 'Products'],
            ['name' => 'delete_product', 'description' => 'Delete/disable products/services', 'module' => 'Products'],
            
            // Invoice permissions
            ['name' => 'create_invoice', 'description' => 'Create new invoices', 'module' => 'Invoices'],
            ['name' => 'view_invoice', 'description' => 'View invoice information', 'module' => 'Invoices'],
            ['name' => 'update_invoice', 'description' => 'Update invoices', 'module' => 'Invoices'],
            ['name' => 'cancel_invoice', 'description' => 'Cancel invoices', 'module' => 'Invoices'],
            ['name' => 'generate_pdf', 'description' => 'Generate invoice PDFs', 'module' => 'Invoices'],
            ['name' => 'generate_xml', 'description' => 'Generate invoice XMLs', 'module' => 'Invoices'],
            ['name' => 'send_email', 'description' => 'Send invoices by email', 'module' => 'Invoices'],
            
            // Report permissions
            ['name' => 'view_reports', 'description' => 'View sales reports', 'module' => 'Reports'],
            ['name' => 'export_reports', 'description' => 'Export reports', 'module' => 'Reports'],
            
            // User management permissions
            ['name' => 'manage_users', 'description' => 'Manage system users', 'module' => 'Users'],
            ['name' => 'manage_roles', 'description' => 'Manage roles and permissions', 'module' => 'Roles'],
            
            // System permissions
            ['name' => 'configure_system', 'description' => 'Configure system parameters', 'module' => 'Configuration'],
            ['name' => 'view_logs', 'description' => 'View system logs', 'module' => 'Logs'],
            ['name' => 'view_help', 'description' => 'Access system help', 'module' => 'Help'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::create($permissionData);
        }

        // Assign all permissions to Administrator role
        $this->info('Assigning permissions to Administrator role...');
        $allPermissions = Permission::all();
        foreach ($allPermissions as $permission) {
            DB::table('role_permissions')->insert([
                'role_id' => $adminRole->id,
                'permission_id' => $permission->id,
            ]);
        }

        // Assign basic permissions to Invoice User role
        $this->info('Assigning permissions to Invoice User role...');
        $invoicePermissions = Permission::whereIn('name', [
            'view_customer', 'view_product', 'view_invoice', 'create_invoice',
            'view_reports', 'generate_pdf', 'send_email'
        ])->get();

        foreach ($invoicePermissions as $permission) {
            DB::table('role_permissions')->insert([
                'role_id' => $invoiceUserRole->id,
                'permission_id' => $permission->id,
            ]);
        }

        // Insert default company
        $this->info('Creating default company...');
        $company = Company::create([
            'name' => 'Construcciones Griegas S.A.',
            'identification_type' => 'Business',
            'identification_number' => '3-101-123456',
            'commercial_name' => 'Construcciones Griegas',
            'phone1' => '2222-3333',
            'email' => 'info@construccionesgriegas.com',
            'province' => 'San José',
            'canton' => 'San José',
            'exact_address' => 'Dirección de la empresa',
            'status' => 'Active',
        ]);

        // Insert admin user
        $this->info('Creating admin user...');
        $adminUser = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@construccionesgriegas.com',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'company_id' => $company->id,
            'status' => 'Active',
        ]);

        // Insert system settings
        $this->info('Creating system settings...');
        $settings = [
            ['company_id' => $company->id, 'code' => 'system_name', 'value' => 'Sistema de Facturación FactuGriego', 'description' => 'Nombre del sistema'],
            ['company_id' => $company->id, 'code' => 'system_version', 'value' => '1.0.0', 'description' => 'Versión del sistema'],
            ['company_id' => $company->id, 'code' => 'currency', 'value' => 'CRC', 'description' => 'Moneda del sistema'],
            ['company_id' => $company->id, 'code' => 'tax_rate', 'value' => '13', 'description' => 'Tasa de impuesto'],
            ['company_id' => $company->id, 'code' => 'invoice_prefix', 'value' => 'INV-', 'description' => 'Prefijo para facturas'],
            ['company_id' => $company->id, 'code' => 'invoice_start_number', 'value' => '000001', 'description' => 'Número inicial de facturas'],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }

        // Insert system info
        $this->info('Creating system info...');
        SystemInfo::create([
            'version' => '1.0.0',
            'last_update' => now(),
            'database_version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ]);

        $this->info('✅ Initial data inserted successfully!');
        $this->info('Admin user created: admin / password');
        $this->info('Company created: Construcciones Griegas S.A.');
        
        return 0;
    }
}
