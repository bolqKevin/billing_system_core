#!/bin/bash

# Script de inicio para Railway
# Ejecuta migraciones y luego inicia el servidor

echo "🚀 Iniciando aplicación Laravel en Railway..."

# Esperar un momento para que las variables de entorno estén disponibles
sleep 5

# Verificar si las variables de base de datos están configuradas
if [ -z "$DB_HOST" ] || [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
    echo "⚠️  Variables de base de datos no configuradas"
    echo "DB_HOST: $DB_HOST"
    echo "DB_DATABASE: $DB_DATABASE"
    echo "DB_USERNAME: $DB_USERNAME"
    echo "DB_PASSWORD: [HIDDEN]"
    echo "⏭️  Saltando migraciones..."
else
    echo "✅ Variables de base de datos configuradas"
    echo "🗄️  Ejecutando migraciones..."
    
    # Intentar ejecutar migraciones con reintentos
    max_attempts=5
    attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        echo "🔄 Intento $attempt de $max_attempts..."
        
        if php artisan migrate --force; then
            echo "✅ Migraciones ejecutadas exitosamente"
            break
        else
            echo "❌ Error en migración (intento $attempt)"
            if [ $attempt -eq $max_attempts ]; then
                echo "⚠️  No se pudieron ejecutar las migraciones después de $max_attempts intentos"
                echo "⏭️  Continuando sin migraciones..."
            else
                echo "⏳ Esperando 10 segundos antes del siguiente intento..."
                sleep 10
            fi
        fi
        
        attempt=$((attempt + 1))
    done
fi

echo "🌐 Iniciando servidor Laravel..."
echo "📍 Puerto: $PORT"
echo "🌍 Host: 0.0.0.0"

# Iniciar el servidor Laravel
exec php artisan serve --host=0.0.0.0 --port=$PORT
