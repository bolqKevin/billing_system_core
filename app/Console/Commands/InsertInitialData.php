<?php

namespace App\Console\Commands;

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
                'created_at' => now(),
            ]);
        }

        // Assign limited permissions to Invoice User role
        $this->info('Assigning permissions to Invoice User role...');
        $limitedPermissions = Permission::whereIn('name', [
            'create_customer', 'view_customer', 'update_customer',
            'view_product', 'create_invoice', 'view_invoice',
            'update_invoice', 'generate_pdf', 'generate_xml',
            'send_email', 'view_reports', 'view_help'
        ])->get();
        
        foreach ($limitedPermissions as $permission) {
            DB::table('role_permissions')->insert([
                'role_id' => $invoiceUserRole->id,
                'permission_id' => $permission->id,
                'created_at' => now(),
            ]);
        }

        // Insert company
        $this->info('Creating company...');
        $company = Company::create([
            'company_name' => 'Construcciones Griegas B&B',
            'business_name' => 'Construcciones Griegas B&B S.A.',
            'legal_id' => '3-101-123456',
            'address' => 'San José, Costa Rica',
            'phone' => '2222-3333',
            'email' => 'info@construccionesgriegas.com',
            'invoice_current_consecutive' => 1,
            'invoice_prefix' => 'INV-',
            'status' => 'Active',
        ]);

        // Insert system settings
        $this->info('Creating system settings...');
        $settings = [
            // SMTP Configuration
            ['code' => 'smtp_host', 'value' => 'smtp.gmail.com'],
            ['code' => 'smtp_port', 'value' => '587'],
            ['code' => 'smtp_username', 'value' => ''],
            ['code' => 'smtp_password', 'value' => ''],
            ['code' => 'smtp_encryption', 'value' => 'tls'],
            ['code' => 'smtp_from_email', 'value' => 'noreply@construccionesgriegas.com'],
            ['code' => 'smtp_from_name', 'value' => 'Construcciones Griegas B&B'],

            // Email Templates
            ['code' => 'email_invoice_template', 'value' => '/templates/invoice_email.html'],
            ['code' => 'email_invoice_subject', 'value' => 'Factura #{invoice_number} - Construcciones Griegas B&B'],
            ['code' => 'email_welcome_template', 'value' => '/templates/welcome_email.html'],
            ['code' => 'email_password_reset_template', 'value' => '/templates/password_reset_email.html'],

            // System Configuration
            ['code' => 'system_timezone', 'value' => 'America/Costa_Rica'],
            ['code' => 'system_currency', 'value' => 'CRC'],
            ['code' => 'system_tax_rate', 'value' => '13.00'],
            ['code' => 'system_logo_path', 'value' => '/uploads/logo.png'],
            ['code' => 'system_theme', 'value' => 'default'],

            // Invoice Configuration
            ['code' => 'invoice_terms_conditions', 'value' => 'Términos y condiciones generales de facturación'],
            ['code' => 'invoice_footer_text', 'value' => 'Gracias por su preferencia'],
            ['code' => 'invoice_due_days_default', 'value' => '30'],

            // Security Settings
            ['code' => 'password_min_length', 'value' => '8'],
            ['code' => 'session_timeout_minutes', 'value' => '120'],
            ['code' => 'max_login_attempts', 'value' => '5'],
            ['code' => 'lockout_duration_minutes', 'value' => '30'],
        ];

        foreach ($settings as $setting) {
            Setting::create([
                'company_id' => $company->id,
                'code' => $setting['code'],
                'value' => $setting['value'],
            ]);
        }

        // Insert system info
        $this->info('Creating system info...');
        SystemInfo::create([
            'system_name' => 'FactuGriego',
            'version' => '1.0.0',
            'release_date' => now()->toDateString(),
            'owner' => 'Construcciones Griegas B&B S.A.',
            'developer' => 'Desarrollo Interno',
            'technologies' => 'PHP, MySQL, HTML5, CSS3, JavaScript, Bootstrap',
        ]);

        // Insert admin user
        $this->info('Creating admin user...');
        User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@construccionesgriegas.com',
            'password' => Hash::make('admin123'),
            'role_id' => $adminRole->id,
            'status' => 'Active',
        ]);

        // Insert sample customers
        $this->info('Creating sample customers...');
        $customers = [
            [
                'name_business_name' => 'Constructora San José S.A.',
                'identification_type' => 'Business',
                'identification_number' => '3-101-123456',
                'commercial_name' => 'ConstrSanJosé',
                'phone1' => '2222-3333',
                'phone2' => '2222-3334',
                'email' => 'info@constrsanjose.com',
                'province' => 'San José',
                'canton' => 'San José',
                'exact_address' => 'Avenida Central, 100 metros norte del Banco Nacional',
                'status' => 'Active',
            ],
            [
                'name_business_name' => 'María Elena Rodríguez Mora',
                'identification_type' => 'Individual',
                'identification_number' => '1-2345-6789',
                'commercial_name' => null,
                'phone1' => '8888-9999',
                'phone2' => null,
                'email' => 'maria.rodriguez@email.com',
                'province' => 'Heredia',
                'canton' => 'Heredia',
                'exact_address' => 'Calle 5, 200 metros este de la Iglesia',
                'status' => 'Active',
            ],
            [
                'name_business_name' => 'Carlos Alberto Brenes Picado',
                'identification_type' => 'Individual',
                'identification_number' => '2-3456-7890',
                'commercial_name' => null,
                'phone1' => '7777-8888',
                'phone2' => null,
                'email' => 'carlos.brenes@email.com',
                'province' => 'Alajuela',
                'canton' => 'Alajuela',
                'exact_address' => 'Avenida 2, 150 metros oeste del Parque Central',
                'status' => 'Active',
            ],
            [
                'name_business_name' => 'Inmobiliaria Costa Rica S.A.',
                'identification_type' => 'Business',
                'identification_number' => '3-101-987654',
                'commercial_name' => 'InmoCR',
                'phone1' => '2444-5555',
                'phone2' => '2444-5556',
                'email' => 'contacto@inmocr.com',
                'province' => 'Cartago',
                'canton' => 'Cartago',
                'exact_address' => 'Avenida 4, 300 metros norte de la Basílica',
                'status' => 'Active',
            ],
            [
                'name_business_name' => 'Roberto Brenes Solano',
                'identification_type' => 'Individual',
                'identification_number' => '3-4567-8901',
                'commercial_name' => null,
                'phone1' => '6666-7777',
                'phone2' => null,
                'email' => 'roberto.brenes@email.com',
                'province' => 'Puntarenas',
                'canton' => 'Puntarenas',
                'exact_address' => 'Calle 8, 100 metros sur del Mercado Central',
                'status' => 'Active',
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::create($customerData);
        }

        // Insert sample products/services
        $this->info('Creating sample products/services...');
        $products = [
            [
                'code' => 'CONST001',
                'name_description' => 'Construcción de casa habitación',
                'type' => 'Service',
                'unit_measure' => 'Proyecto',
                'unit_price' => 50000.00,
                'status' => 'Active',
            ],
            [
                'code' => 'CONST002',
                'name_description' => 'Reparación de techo',
                'type' => 'Service',
                'unit_measure' => 'Hora',
                'unit_price' => 45.00,
                'status' => 'Active',
            ],
            [
                'code' => 'CONST003',
                'name_description' => 'Instalación eléctrica',
                'type' => 'Service',
                'unit_measure' => 'Hora',
                'unit_price' => 35.00,
                'status' => 'Active',
            ],
            [
                'code' => 'MAT001',
                'name_description' => 'Cemento Portland',
                'type' => 'Product',
                'unit_measure' => 'Saco',
                'unit_price' => 12.50,
                'status' => 'Active',
            ],
            [
                'code' => 'MAT002',
                'name_description' => 'Varilla de construcción',
                'type' => 'Product',
                'unit_measure' => 'Unidad',
                'unit_price' => 8.75,
                'status' => 'Active',
            ],
            [
                'code' => 'MAT003',
                'name_description' => 'Ladrillo',
                'type' => 'Product',
                'unit_measure' => 'Unidad',
                'unit_price' => 0.85,
                'status' => 'Active',
            ],
            [
                'code' => 'MAT004',
                'name_description' => 'Arena',
                'type' => 'Product',
                'unit_measure' => 'Metro cúbico',
                'unit_price' => 25.00,
                'status' => 'Active',
            ],
            [
                'code' => 'MAT005',
                'name_description' => 'Piedra',
                'type' => 'Product',
                'unit_measure' => 'Metro cúbico',
                'unit_price' => 30.00,
                'status' => 'Active',
            ],
        ];

        foreach ($products as $productData) {
            ProductService::create($productData);
        }

        $this->info('Initial data inserted successfully!');
        $this->info('Admin user: admin / admin123');
    }
}
