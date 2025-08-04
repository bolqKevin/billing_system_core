# FactuGriego Billing System - API Documentation

## Descripción General

Esta es la API REST del sistema de facturación FactuGriego desarrollada en Laravel 11. La API proporciona endpoints para gestionar clientes, productos/servicios, facturas y otros aspectos del sistema de facturación.

## Configuración Inicial

### Requisitos
- PHP 8.2+
- MySQL 8.0+
- Composer
- Laravel 11

### Instalación

1. Clonar el repositorio
2. Instalar dependencias:
   ```bash
   composer install
   ```

3. Configurar la base de datos en el archivo `.env`:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=factugriego_db
   DB_USERNAME=root
   DB_PASSWORD=tu_password
   ```

4. Generar clave de aplicación:
   ```bash
   php artisan key:generate
   ```

5. Ejecutar las migraciones de Sanctum:
   ```bash
   php artisan migrate
   ```

6. Importar la base de datos:
   ```bash
   mysql -u root -p factugriego_db < billingSystemDb.sql
   ```

7. Iniciar el servidor:
   ```bash
   php artisan serve
   ```

## Autenticación

La API utiliza Laravel Sanctum para la autenticación mediante tokens.

### Login
```http
POST /api/login
Content-Type: application/json

{
    "username": "admin",
    "password": "password"
}
```

**Respuesta:**
```json
{
    "success": true,
    "message": "Inicio de sesión exitoso",
    "data": {
        "user": {
            "id": 1,
            "name": "Administrator",
            "username": "admin",
            "email": "admin@construccionesgriegas.com",
            "role": "Administrator",
            "permissions": ["create_customer", "view_customer", ...]
        },
        "token": "1|abc123..."
    }
}
```

### Logout
```http
POST /api/logout
Authorization: Bearer {token}
```

### Obtener Usuario Actual
```http
GET /api/user
Authorization: Bearer {token}
```

## Endpoints Principales

### Dashboard
```http
GET /api/dashboard
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
    "success": true,
    "data": {
        "sales": {
            "current_month": 150000.00,
            "previous_month": 120000.00,
            "growth_percentage": 25.00
        },
        "invoices": {
            "total_issued": 45,
            "draft": 3,
            "cancelled": 1
        },
        "customers": {
            "total": 25,
            "new_this_month": 5
        },
        "products_services": {
            "total_products": 15,
            "total_services": 8
        },
        "recent_invoices": [...],
        "top_selling_products": [...],
        "monthly_sales_chart": [...]
    }
}
```

### Clientes

#### Listar Clientes
```http
GET /api/customers?search=nombre&status=Active&page=1
Authorization: Bearer {token}
```

#### Crear Cliente
```http
POST /api/customers
Authorization: Bearer {token}
Content-Type: application/json

{
    "name_business_name": "Empresa ABC S.A.",
    "identification_type": "Business",
    "identification_number": "3-101-123456",
    "commercial_name": "ABC",
    "phone1": "2222-3333",
    "email": "info@abc.com",
    "province": "San José",
    "canton": "San José",
    "exact_address": "Dirección exacta",
    "status": "Active"
}
```

#### Obtener Cliente
```http
GET /api/customers/{id}
Authorization: Bearer {token}
```

#### Actualizar Cliente
```http
PUT /api/customers/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name_business_name": "Empresa ABC S.A. Actualizada",
    "identification_type": "Business",
    "identification_number": "3-101-123456",
    "commercial_name": "ABC",
    "phone1": "2222-3333",
    "email": "info@abc.com",
    "province": "San José",
    "canton": "San José",
    "exact_address": "Nueva dirección",
    "status": "Active"
}
```

#### Eliminar Cliente (Deshabilitar)
```http
DELETE /api/customers/{id}
Authorization: Bearer {token}
```

### Productos/Servicios

#### Listar Productos/Servicios
```http
GET /api/products-services?search=código&type=Product&status=Active&page=1
Authorization: Bearer {token}
```

#### Crear Producto/Servicio
```http
POST /api/products-services
Authorization: Bearer {token}
Content-Type: application/json

{
    "code": "PROD-001",
    "name_description": "Producto de ejemplo",
    "type": "Product",
    "unit_measure": "Unidad",
    "unit_price": 100.00,
    "status": "Active"
}
```

#### Obtener Producto/Servicio
```http
GET /api/products-services/{id}
Authorization: Bearer {token}
```

#### Actualizar Producto/Servicio
```http
PUT /api/products-services/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "code": "PROD-001",
    "name_description": "Producto actualizado",
    "type": "Product",
    "unit_measure": "Unidad",
    "unit_price": 120.00,
    "status": "Active"
}
```

#### Eliminar Producto/Servicio (Deshabilitar)
```http
DELETE /api/products-services/{id}
Authorization: Bearer {token}
```

### Facturas

#### Listar Facturas
```http
GET /api/invoices?search=número&status=Issued&customer_id=1&start_date=2024-01-01&end_date=2024-12-31&page=1
Authorization: Bearer {token}
```

#### Crear Factura
```http
POST /api/invoices
Authorization: Bearer {token}
Content-Type: application/json

{
    "customer_id": 1,
    "issue_date": "2024-01-15",
    "payment_method": "Transfer",
    "sale_condition": "Credit",
    "credit_days": 30,
    "observations": "Observaciones de la factura",
    "details": [
        {
            "product_service_id": 1,
            "quantity": 2,
            "unit_price": 100.00,
            "item_discount": 10.00
        },
        {
            "product_service_id": 2,
            "quantity": 1,
            "unit_price": 50.00,
            "item_discount": 0.00
        }
    ]
}
```

#### Obtener Factura
```http
GET /api/invoices/{id}
Authorization: Bearer {token}
```

#### Actualizar Factura (Solo borradores)
```http
PUT /api/invoices/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "customer_id": 1,
    "issue_date": "2024-01-15",
    "payment_method": "Transfer",
    "sale_condition": "Credit",
    "credit_days": 30,
    "observations": "Observaciones actualizadas",
    "details": [
        {
            "product_service_id": 1,
            "quantity": 3,
            "unit_price": 100.00,
            "item_discount": 15.00
        }
    ]
}
```

#### Emitir Factura
```http
POST /api/invoices/{id}/issue
Authorization: Bearer {token}
```

#### Cancelar Factura
```http
POST /api/invoices/{id}/cancel
Authorization: Bearer {token}
Content-Type: application/json

{
    "cancellation_reason": "Error en los datos del cliente"
}
```

### Sistema

#### Información del Sistema
```http
GET /api/system/info
Authorization: Bearer {token}
```

#### Estado del Sistema
```http
GET /api/system/health
Authorization: Bearer {token}
```

## Detalles Completos de Endpoints

### 🔐 Autenticación

#### POST /api/login
**Descripción:** Autentica un usuario y devuelve un token de acceso.

**Request:**
```json
{
    "username": "admin",
    "password": "password123"
}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "message": "Inicio de sesión exitoso",
    "data": {
        "user": {
            "id": 1,
            "name": "Administrator",
            "username": "admin",
            "email": "admin@construccionesgriegas.com",
            "role": {
                "id": 1,
                "name": "Administrator",
                "description": "Administrador del sistema"
            },
            "permissions": [
                "create_customer",
                "view_customer",
                "update_customer",
                "delete_customer",
                "create_invoice",
                "view_invoice",
                "issue_invoice",
                "cancel_invoice"
            ]
        },
        "token": "1|abc123def456ghi789..."
    }
}
```

**Respuesta de Error (401):**
```json
{
    "success": false,
    "message": "Credenciales inválidas",
    "errors": {
        "username": ["El nombre de usuario o contraseña son incorrectos"]
    }
}
```

#### POST /api/logout
**Descripción:** Cierra la sesión del usuario actual invalidando el token.

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "message": "Sesión cerrada exitosamente"
}
```

#### GET /api/user
**Descripción:** Obtiene la información del usuario autenticado actual.

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Administrator",
        "username": "admin",
        "email": "admin@construccionesgriegas.com",
        "role": {
            "id": 1,
            "name": "Administrator",
            "description": "Administrador del sistema"
        },
        "permissions": [
            "create_customer",
            "view_customer",
            "update_customer",
            "delete_customer"
        ]
    }
}
```

### 📊 Dashboard

#### GET /api/dashboard
**Descripción:** Obtiene estadísticas y datos para el dashboard principal.

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "sales": {
            "current_month": 150000.00,
            "previous_month": 120000.00,
            "growth_percentage": 25.00,
            "currency": "CRC"
        },
        "invoices": {
            "total_issued": 45,
            "draft": 3,
            "cancelled": 1,
            "pending_payment": 12
        },
        "customers": {
            "total": 25,
            "new_this_month": 5,
            "active": 23,
            "inactive": 2
        },
        "products_services": {
            "total_products": 15,
            "total_services": 8,
            "active": 20,
            "inactive": 3
        },
        "recent_invoices": [
            {
                "id": 1,
                "invoice_number": "F-001-001",
                "customer_name": "Empresa ABC S.A.",
                "total_amount": 15000.00,
                "status": "Issued",
                "issue_date": "2024-01-15"
            }
        ],
        "top_selling_products": [
            {
                "id": 1,
                "name": "Producto A",
                "total_sold": 50,
                "total_revenue": 50000.00
            }
        ],
        "monthly_sales_chart": [
            {
                "month": "2024-01",
                "sales": 150000.00,
                "invoices": 15
            }
        ]
    }
}
```

### 👥 Clientes

#### GET /api/customers
**Descripción:** Lista todos los clientes con filtros opcionales.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` (string): Búsqueda por nombre, identificación o email
- `status` (string): Filtrar por estado (Active, Inactive)
- `page` (integer): Número de página
- `per_page` (integer): Elementos por página (máximo 100)

**Ejemplo de Request:**
```
GET /api/customers?search=ABC&status=Active&page=1&per_page=15
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name_business_name": "Empresa ABC S.A.",
                "identification_type": "Business",
                "identification_number": "3-101-123456",
                "commercial_name": "ABC",
                "phone1": "2222-3333",
                "phone2": "2222-4444",
                "email": "info@abc.com",
                "province": "San José",
                "canton": "San José",
                "exact_address": "Dirección exacta",
                "status": "Active",
                "created_at": "2024-01-15T10:30:00.000000Z",
                "updated_at": "2024-01-15T10:30:00.000000Z"
            }
        ],
        "first_page_url": "http://localhost:8000/api/customers?page=1",
        "from": 1,
        "last_page": 1,
        "last_page_url": "http://localhost:8000/api/customers?page=1",
        "links": [...],
        "next_page_url": null,
        "path": "http://localhost:8000/api/customers",
        "per_page": 15,
        "prev_page_url": null,
        "to": 1,
        "total": 1
    }
}
```

#### POST /api/customers
**Descripción:** Crea un nuevo cliente.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "name_business_name": "Empresa ABC S.A.",
    "identification_type": "Business",
    "identification_number": "3-101-123456",
    "commercial_name": "ABC",
    "phone1": "2222-3333",
    "phone2": "2222-4444",
    "email": "info@abc.com",
    "province": "San José",
    "canton": "San José",
    "exact_address": "Dirección exacta",
    "status": "Active"
}
```

**Respuesta Exitosa (201):**
```json
{
    "success": true,
    "message": "Cliente creado exitosamente",
    "data": {
        "id": 1,
        "name_business_name": "Empresa ABC S.A.",
        "identification_type": "Business",
        "identification_number": "3-101-123456",
        "commercial_name": "ABC",
        "phone1": "2222-3333",
        "phone2": "2222-4444",
        "email": "info@abc.com",
        "province": "San José",
        "canton": "San José",
        "exact_address": "Dirección exacta",
        "status": "Active",
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Respuesta de Error (400):**
```json
{
    "success": false,
    "message": "Error de validación",
    "errors": {
        "name_business_name": ["El campo nombre/razón social es obligatorio"],
        "identification_number": ["El número de identificación ya existe"]
    }
}
```

#### GET /api/customers/{id}
**Descripción:** Obtiene un cliente específico por ID.

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name_business_name": "Empresa ABC S.A.",
        "identification_type": "Business",
        "identification_number": "3-101-123456",
        "commercial_name": "ABC",
        "phone1": "2222-3333",
        "phone2": "2222-4444",
        "email": "info@abc.com",
        "province": "San José",
        "canton": "San José",
        "exact_address": "Dirección exacta",
        "status": "Active",
        "invoices": [
            {
                "id": 1,
                "invoice_number": "F-001-001",
                "total_amount": 15000.00,
                "status": "Issued",
                "issue_date": "2024-01-15"
            }
        ],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Respuesta de Error (404):**
```json
{
    "success": false,
    "message": "Cliente no encontrado"
}
```

#### PUT /api/customers/{id}
**Descripción:** Actualiza un cliente existente.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "name_business_name": "Empresa ABC S.A. Actualizada",
    "identification_type": "Business",
    "identification_number": "3-101-123456",
    "commercial_name": "ABC",
    "phone1": "2222-3333",
    "phone2": "2222-4444",
    "email": "info@abc.com",
    "province": "San José",
    "canton": "San José",
    "exact_address": "Nueva dirección",
    "status": "Active"
}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "message": "Cliente actualizado exitosamente",
    "data": {
        "id": 1,
        "name_business_name": "Empresa ABC S.A. Actualizada",
        "identification_type": "Business",
        "identification_number": "3-101-123456",
        "commercial_name": "ABC",
        "phone1": "2222-3333",
        "phone2": "2222-4444",
        "email": "info@abc.com",
        "province": "San José",
        "canton": "San José",
        "exact_address": "Nueva dirección",
        "status": "Active",
        "updated_at": "2024-01-15T11:00:00.000000Z"
    }
}
```

#### DELETE /api/customers/{id}
**Descripción:** Deshabilita un cliente (soft delete).

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "message": "Cliente deshabilitado exitosamente"
}
```

### 🛍️ Productos/Servicios

#### GET /api/products-services
**Descripción:** Lista todos los productos y servicios con filtros opcionales.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` (string): Búsqueda por código o nombre
- `type` (string): Filtrar por tipo (Product, Service)
- `status` (string): Filtrar por estado (Active, Inactive)
- `page` (integer): Número de página
- `per_page` (integer): Elementos por página (máximo 100)

**Ejemplo de Request:**
```
GET /api/products-services?search=PROD&type=Product&status=Active&page=1
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "code": "PROD-001",
                "name_description": "Producto de ejemplo",
                "type": "Product",
                "unit_measure": "Unidad",
                "unit_price": 100.00,
                "status": "Active",
                "created_at": "2024-01-15T10:30:00.000000Z",
                "updated_at": "2024-01-15T10:30:00.000000Z"
            }
        ],
        "total": 1
    }
}
```

#### POST /api/products-services
**Descripción:** Crea un nuevo producto o servicio.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "code": "PROD-001",
    "name_description": "Producto de ejemplo",
    "type": "Product",
    "unit_measure": "Unidad",
    "unit_price": 100.00,
    "status": "Active"
}
```

**Respuesta Exitosa (201):**
```json
{
    "success": true,
    "message": "Producto/Servicio creado exitosamente",
    "data": {
        "id": 1,
        "code": "PROD-001",
        "name_description": "Producto de ejemplo",
        "type": "Product",
        "unit_measure": "Unidad",
        "unit_price": 100.00,
        "status": "Active",
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

### 📄 Facturas

#### GET /api/invoices
**Descripción:** Lista todas las facturas con filtros opcionales.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `search` (string): Búsqueda por número de factura
- `status` (string): Filtrar por estado (Draft, Issued, Cancelled)
- `customer_id` (integer): Filtrar por cliente
- `start_date` (date): Fecha de inicio (YYYY-MM-DD)
- `end_date` (date): Fecha de fin (YYYY-MM-DD)
- `page` (integer): Número de página
- `per_page` (integer): Elementos por página (máximo 100)

**Ejemplo de Request:**
```
GET /api/invoices?status=Issued&start_date=2024-01-01&end_date=2024-12-31&page=1
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "invoice_number": "F-001-001",
                "customer": {
                    "id": 1,
                    "name_business_name": "Empresa ABC S.A."
                },
                "issue_date": "2024-01-15",
                "payment_method": "Transfer",
                "sale_condition": "Credit",
                "credit_days": 30,
                "subtotal": 13000.00,
                "tax_amount": 1690.00,
                "total_amount": 14690.00,
                "status": "Issued",
                "observations": "Observaciones de la factura",
                "created_at": "2024-01-15T10:30:00.000000Z"
            }
        ],
        "total": 1
    }
}
```

#### POST /api/invoices
**Descripción:** Crea una nueva factura con sus detalles.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "customer_id": 1,
    "issue_date": "2024-01-15",
    "payment_method": "Transfer",
    "sale_condition": "Credit",
    "credit_days": 30,
    "observations": "Observaciones de la factura",
    "details": [
        {
            "product_service_id": 1,
            "quantity": 2,
            "unit_price": 100.00,
            "item_discount": 10.00
        },
        {
            "product_service_id": 2,
            "quantity": 1,
            "unit_price": 50.00,
            "item_discount": 0.00
        }
    ]
}
```

**Respuesta Exitosa (201):**
```json
{
    "success": true,
    "message": "Factura creada exitosamente",
    "data": {
        "id": 1,
        "invoice_number": "F-001-001",
        "customer": {
            "id": 1,
            "name_business_name": "Empresa ABC S.A."
        },
        "issue_date": "2024-01-15",
        "payment_method": "Transfer",
        "sale_condition": "Credit",
        "credit_days": 30,
        "subtotal": 240.00,
        "tax_amount": 31.20,
        "total_amount": 271.20,
        "status": "Draft",
        "observations": "Observaciones de la factura",
        "details": [
            {
                "id": 1,
                "product_service": {
                    "id": 1,
                    "name_description": "Producto A"
                },
                "quantity": 2,
                "unit_price": 100.00,
                "item_discount": 10.00,
                "subtotal": 190.00,
                "tax_amount": 24.70,
                "total": 214.70
            }
        ],
        "created_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

#### GET /api/invoices/{id}
**Descripción:** Obtiene una factura específica con todos sus detalles.

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "invoice_number": "F-001-001",
        "customer": {
            "id": 1,
            "name_business_name": "Empresa ABC S.A.",
            "identification_number": "3-101-123456",
            "exact_address": "Dirección del cliente"
        },
        "issue_date": "2024-01-15",
        "payment_method": "Transfer",
        "sale_condition": "Credit",
        "credit_days": 30,
        "subtotal": 240.00,
        "tax_amount": 31.20,
        "total_amount": 271.20,
        "status": "Issued",
        "observations": "Observaciones de la factura",
        "details": [
            {
                "id": 1,
                "product_service": {
                    "id": 1,
                    "code": "PROD-001",
                    "name_description": "Producto A",
                    "unit_measure": "Unidad"
                },
                "quantity": 2,
                "unit_price": 100.00,
                "item_discount": 10.00,
                "subtotal": 190.00,
                "tax_amount": 24.70,
                "total": 214.70
            }
        ],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

#### PUT /api/invoices/{id}
**Descripción:** Actualiza una factura (solo si está en estado Draft).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "customer_id": 1,
    "issue_date": "2024-01-15",
    "payment_method": "Transfer",
    "sale_condition": "Credit",
    "credit_days": 30,
    "observations": "Observaciones actualizadas",
    "details": [
        {
            "product_service_id": 1,
            "quantity": 3,
            "unit_price": 100.00,
            "item_discount": 15.00
        }
    ]
}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "message": "Factura actualizada exitosamente",
    "data": {
        "id": 1,
        "invoice_number": "F-001-001",
        "subtotal": 285.00,
        "tax_amount": 37.05,
        "total_amount": 322.05,
        "status": "Draft",
        "updated_at": "2024-01-15T11:00:00.000000Z"
    }
}
```

#### POST /api/invoices/{id}/issue
**Descripción:** Emite una factura (cambia estado de Draft a Issued).

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "message": "Factura emitida exitosamente",
    "data": {
        "id": 1,
        "invoice_number": "F-001-001",
        "status": "Issued",
        "issue_date": "2024-01-15",
        "updated_at": "2024-01-15T11:00:00.000000Z"
    }
}
```

#### POST /api/invoices/{id}/cancel
**Descripción:** Cancela una factura emitida.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "cancellation_reason": "Error en los datos del cliente"
}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "message": "Factura cancelada exitosamente",
    "data": {
        "id": 1,
        "invoice_number": "F-001-001",
        "status": "Cancelled",
        "cancellation_reason": "Error en los datos del cliente",
        "updated_at": "2024-01-15T11:00:00.000000Z"
    }
}
```

### 🏢 Sistema

#### GET /api/system/info
**Descripción:** Obtiene información general del sistema.

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "system_name": "FactuGriego",
        "version": "1.0.0",
        "owner": "Construcciones Griegas S.A.",
        "description": "Sistema de facturación electrónica",
        "database": {
            "connection": "mysql",
            "database": "factugriego_db"
        },
        "api_version": "v1",
        "environment": "production"
    }
}
```

#### GET /api/system/health
**Descripción:** Verifica el estado de salud del sistema.

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta Exitosa (200):**
```json
{
    "success": true,
    "data": {
        "status": "healthy",
        "database": "connected",
        "timestamp": "2024-01-15T10:30:00.000000Z",
        "uptime": "2 days, 5 hours, 30 minutes"
    }
}
```

## Códigos de Respuesta

- `200` - Éxito
- `201` - Creado exitosamente
- `400` - Error de validación
- `401` - No autenticado
- `403` - No autorizado
- `404` - No encontrado
- `500` - Error interno del servidor

## Estructura de Respuestas

### Respuesta Exitosa
```json
{
    "success": true,
    "message": "Mensaje de éxito",
    "data": {
        // Datos de la respuesta
    }
}
```

### Respuesta de Error
```json
{
    "success": false,
    "message": "Mensaje de error",
    "errors": {
        "field": ["Error específico del campo"]
    }
}
```

## Paginación

Los endpoints que devuelven listas incluyen paginación:

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [...],
        "first_page_url": "...",
        "from": 1,
        "last_page": 5,
        "last_page_url": "...",
        "links": [...],
        "next_page_url": "...",
        "path": "...",
        "per_page": 15,
        "prev_page_url": null,
        "to": 15,
        "total": 75
    }
}
```

## Filtros Comunes

### Búsqueda
- `search` - Búsqueda por texto en campos relevantes

### Estado
- `status` - Filtrar por estado (Active, Inactive, Draft, Issued, Cancelled)

### Fechas
- `start_date` - Fecha de inicio (YYYY-MM-DD)
- `end_date` - Fecha de fin (YYYY-MM-DD)

### Paginación
- `page` - Número de página
- `per_page` - Elementos por página (máximo 100)

## Logs y Auditoría

El sistema registra automáticamente:
- Intentos de login (exitosos y fallidos)
- Logouts
- Creación, actualización y eliminación de registros
- Acciones específicas como emisión y cancelación de facturas

## Seguridad

- Autenticación mediante tokens Bearer
- Validación de permisos por rol
- Sanitización de datos de entrada
- Logs de auditoría
- Protección CSRF (no aplicable para API)
- Validación de datos en todos los endpoints

## Notas de Desarrollo

- La API está diseñada para ser consumida por aplicaciones frontend
- Todos los endpoints devuelven JSON
- Los errores de validación incluyen detalles específicos
- El sistema está preparado para integración futura con la API de Hacienda
- Los cálculos de impuestos se realizan automáticamente (13% IVA)
- Las facturas tienen estados: Draft, Issued, Cancelled
- Los clientes y productos pueden ser deshabilitados (soft delete) 