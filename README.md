API REST desarrollada en Laravel para gestión de stock, ventas y transferencias entre sucursales.

API REST desarrollada con Laravel para la gestión integral de una tienda dedicada al expendio y venta de bebidas.
Permite controlar stock, registrar ventas y realizar transferencias entre sucursales.
Proyecto desarrollado como trabajo final de seminario universitario.

Tecnologías utilizadas:

Laravel
PostgreSQL
Eloquent ORM
Seguridad mediante middlewares
Autenticación con roles -> JWT
API REST

Funcionalidades principales:

CRUD de productos
Registro de ventas
Relación Venta – Producto (tabla intermedia)
Gestión de proveedores
Transferencias entre sucursales
Control de stock automático
Resúmenes de ventas/compras
Sistema de roles

Estructura destacada:

Arquitectura MVC
Validaciones en backend
Relaciones Eloquent
Control transaccional en registro de ventas

Intalacion:

git clone https://github.com/tuusuario/Licoreria_Backend.git
cd Licoreria_Backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

Frontend

La interfaz está desarrollada en React: https://github.com/Hualpa7/Licoreria_Frontend
