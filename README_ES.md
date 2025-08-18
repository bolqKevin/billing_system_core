# 🧾 Sistema de Facturación - FactuGriego

Sistema de facturación completo desarrollado en Laravel con API REST para gestión de clientes, productos, facturas y reportes.

> **🇺🇸 [View in English](README.md)** | **📖 Ver en Español**

Sistema de facturación completo desarrollado en Laravel con API REST para gestión de clientes, productos, facturas y reportes.

## 📋 Tabla de Contenidos

- [Requisitos del Sistema](#requisitos-del-sistema)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Uso del Sistema](#uso-del-sistema)
- [API Endpoints](#api-endpoints)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Comandos Útiles](#comandos-útiles)
- [Solución de Problemas](#solución-de-problemas)

## 🖥️ Requisitos del Sistema

### Software Requerido
- **PHP**: 8.1 o superior
- **Composer**: 2.0 o superior
- **MySQL**: 8.0 o superior
- **Node.js**: 16.0 o superior (opcional, para desarrollo)
- **Git**: Para clonar el repositorio

### Extensiones PHP Requeridas
```bash
php -m | grep -E "(bcmath|ctype|fileinfo|json|mbstring|openssl|pdo|tokenizer|xml)"
```

## 🚀 Instalación

### Paso 1: Clonar el Repositorio
```bash
git clone <url-del-repositorio>
cd billing_system_core
```

### Paso 2: Instalar Dependencias PHP
```bash
composer install
```

### Paso 3: Configurar Variables de Entorno
```bash
cp .env.example .env
```

Editar el archivo `.env` con tu configuración:
```env
APP_NAME="Sistema de Facturación"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billing_system_db
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_email@gmail.com
MAIL_PASSWORD=tu_password_app
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu_email@gmail.com
MAIL_FROM_NAME="Sistema de Facturación"
```

### Paso 4: Generar Clave de Aplicación
```bash
php artisan key:generate
```

### Paso 5: Crear Base de Datos
```sql
CREATE DATABASE billing_system_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Paso 6: Ejecutar Migraciones
```bash
php artisan migrate
```

### Paso 7: Insertar Datos Iniciales
```bash
php artisan data:insert
```

### Paso 8: Limpiar Tablas No Utilizadas (Opcional)
```bash
php artisan system:cleanup-tables
```

### Paso 9: Configurar Permisos de Almacenamiento
```bash
chmod -R 775 storage bootstrap/cache
```

### Paso 10: Iniciar el Servidor
```bash
php artisan serve
```

El sistema estará disponible en: `http://localhost:8000`

## ⚙️ Configuración

### Configuración de Email (SMTP)
Para que el sistema pueda enviar facturas por email:

1. **Gmail (Recomendado para pruebas):**
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=tu_email@gmail.com
   MAIL_PASSWORD=tu_password_de_aplicacion
   MAIL_ENCRYPTION=tls
   ```

2. **Probar Configuración:**
   ```bash
   php artisan test-email
   ```

### Configuración de Empresa
Después de la instalación, configurar la información de la empresa:

1. Acceder al sistema con: `admin` / `password`
2. Ir a Configuración → Información de Empresa
3. Actualizar datos de la empresa

## 👥 Uso del Sistema

### Usuarios por Defecto
- **Administrador**: `admin` / `password`
- **Usuario Facturador**: `facturador` / `password`

### Roles del Sistema
- **Administrador**: Acceso completo al sistema
- **Facturador**: Solo funciones de facturación

### Funcionalidades Principales
- ✅ Gestión de Clientes
- ✅ Gestión de Productos/Servicios
- ✅ Creación y Emisión de Facturas
- ✅ Generación de PDFs
- ✅ Envío de Facturas por Email
- ✅ Reportes de Ventas
- ✅ Auditoría de Usuarios
- ✅ Configuración del Sistema

## 🔌 API Endpoints

### Autenticación
```http
POST /api/login
Content-Type: application/json

{
    "username": "admin",
    "password": "password"
}
```

### Clientes
```http
GET    /api/customers              # Listar clientes
GET    /api/customers/{id}         # Obtener cliente
POST   /api/customers              # Crear cliente
PUT    /api/customers/{id}         # Actualizar cliente
DELETE /api/customers/{id}         # Eliminar cliente
```

### Productos/Servicios
```http
GET    /api/products-services              # Listar productos
GET    /api/products-services/{id}         # Obtener producto
POST   /api/products-services              # Crear producto
PUT    /api/products-services/{id}         # Actualizar producto
DELETE /api/products-services/{id}         # Eliminar producto
```

### Facturas
```http
GET    /api/invoices               # Listar facturas
GET    /api/invoices/{id}          # Obtener factura
POST   /api/invoices               # Crear factura
PUT    /api/invoices/{id}          # Actualizar factura
DELETE /api/invoices/{id}          # Eliminar factura
POST   /api/invoices/{id}/issue    # Emitir factura
POST   /api/invoices/{id}/cancel   # Cancelar factura
GET    /api/invoices/{id}/pdf      # Generar PDF
POST   /api/invoices/{id}/send-email # Enviar por email
```

### Reportes
```http
GET /api/reports/sales             # Reporte de ventas
GET /api/reports/customers         # Reporte de clientes
GET /api/reports/products          # Reporte de productos
GET /api/reports/monthly-sales     # Ventas mensuales
```

### Usuarios (Solo Administradores)
```http
GET    /api/users                  # Listar usuarios
GET    /api/users/{id}             # Obtener usuario
POST   /api/users                  # Crear usuario
PUT    /api/users/{id}             # Actualizar usuario
DELETE /api/users/{id}             # Eliminar usuario
```

### Sistema
```http
GET  /api/system/info              # Información del sistema
GET  /api/system/settings          # Configuraciones
PUT  /api/system/settings          # Actualizar configuraciones
GET  /api/system/health            # Estado del sistema
```

### Auditoría (Solo Administradores)
```http
GET /api/audit/movements           # Logs de movimientos
GET /api/audit/logins              # Logs de login
GET /api/audit/users               # Información de usuarios
GET /api/audit/statistics          # Estadísticas
```

## 📁 Estructura del Proyecto

```
billing_system_core/
├── app/
│   ├── Http/Controllers/Api/     # Controladores de la API
│   ├── Models/                   # Modelos Eloquent
│   ├── Helpers/                  # Helpers personalizados
│   └── Traits/                   # Traits reutilizables
├── database/
│   ├── migrations/               # Migraciones de base de datos
│   └── seeders/                  # Seeders (si los hay)
├── routes/
│   └── api.php                   # Rutas de la API
├── Console/Commands/             # Comandos personalizados
└── storage/
    └── logs/                     # Logs del sistema
```

## 🛠️ Comandos Útiles

### Comandos de Instalación
```bash
php artisan data:insert              # Insertar datos iniciales
php artisan system:cleanup-tables    # Limpiar tablas no utilizadas
php artisan migrate:fresh            # Recrear base de datos
```

### Comandos de Diagnóstico
```bash
php artisan test-email               # Probar configuración de email
php artisan system:health            # Verificar estado del sistema
php artisan route:list               # Listar todas las rutas
```

### Comandos de Mantenimiento
```bash
php artisan cache:clear              # Limpiar cache
php artisan config:clear             # Limpiar configuración
php artisan view:clear               # Limpiar vistas
```

## 🔧 Solución de Problemas

### Error de Conexión a Base de Datos
```bash
# Verificar configuración
php artisan tinker
>>> DB::connection()->getPdo();
```

### Error de Permisos
```bash
# En Linux/Mac
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Error de Email
```bash
# Probar configuración SMTP
php artisan test-email
```

### Error de Migraciones
```bash
# Recrear base de datos
php artisan migrate:fresh --seed
```

### Error de Autenticación
```bash
# Regenerar clave de aplicación
php artisan key:generate
```

## 📊 Características del Sistema

### ✅ Funcionalidades Implementadas
- Sistema de autenticación con Sanctum
- Gestión de roles y permisos
- Multitenancy (múltiples empresas)
- Generación de PDFs con TCPDF
- Envío de emails con plantillas
- Auditoría completa de acciones
- API REST documentada
- Sistema de logs detallado

### 🔒 Seguridad
- Autenticación JWT con Sanctum
- Validación de datos en todos los endpoints
- Filtrado por empresa (multitenancy)
- Logs de auditoría
- Protección CSRF
- Sanitización de inputs

### 📈 Escalabilidad
- Arquitectura modular
- Separación de responsabilidades
- Código reutilizable
- Base de datos optimizada
- API REST stateless

## 🤝 Contribución

1. Fork el proyecto
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 📞 Soporte

Para soporte técnico o consultas:
- Email: soporte@construccionesgriegas.com
- Documentación: [Wiki del Proyecto]
- Issues: [GitHub Issues]

---

**Desarrollado por:** Construcciones Griegas B&B S.A.  
**Versión:** 1.0.0  
**Última actualización:** Agosto 2025
