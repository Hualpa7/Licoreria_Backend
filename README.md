#  Licorería — Backend API REST

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-316192?style=for-the-badge&logo=postgresql&logoColor=white)
![JWT](https://img.shields.io/badge/JWT-black?style=for-the-badge&logo=JSON%20web%20tokens)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![React](https://img.shields.io/badge/React-20232A?style=for-the-badge&logo=react&logoColor=61DAFB)

**API REST desarrollada en Laravel para la gestión integral de una licorería.**  
Control de stock, ventas y transferencias entre sucursales — Trabajo Final de Seminario Universitario.

[Frontend →](https://github.com/Hualpa7/Licoreria_Frontend) · [Reportar un bug](https://github.com/Hualpa7/Licoreria_Backend/issues) · [Ver repositorio](https://github.com/Hualpa7/Licoreria_Backend)

</div>

---

## 📋 Descripción

Sistema backend completo para la administración de una tienda dedicada al expendio y venta de bebidas. Permite controlar el inventario en tiempo real, registrar ventas con relación a productos, gestionar proveedores y realizar transferencias de stock entre sucursales, todo bajo un sistema de autenticación con roles.

Desarrollado como trabajo final del seminario universitario, el proyecto aplica buenas prácticas de arquitectura MVC, control transaccional y validaciones robustas del lado del servidor.

---

## 🖼️ Vista previa

> **Nota:** Reemplazá estas imágenes con capturas de pantalla reales de tu app o de herramientas como Postman/Thunder Client.

### Dashboard / Resumen de ventas
![Dashboard](./screenshots/dashboard.png)

### Gestión de stock y productos
![Productos](./screenshots/productos.png)

### Registro de ventas
![Ventas](./screenshots/ventas.png)

### Transferencias entre sucursales
![Transferencias](./screenshots/transferencias.png)

---

## ✨ Funcionalidades principales

| Módulo | Descripción |
|---|---|
| 🛍️ **Productos** | CRUD completo de productos con control de stock automático |
| 💰 **Ventas** | Registro de ventas con relación Venta–Producto (tabla intermedia) |
| 🏪 **Sucursales** | Transferencias de stock entre sucursales |
| 📦 **Proveedores** | Gestión de proveedores y órdenes de compra |
| 📊 **Reportes** | Resúmenes de ventas y compras |
| 👥 **Roles** | Sistema de autenticación con roles diferenciados vía JWT |

---

## 🛠️ Tecnologías utilizadas

| Tecnología | Rol |
|---|---|
| **Laravel** | Framework PHP principal (API REST) |
| **PostgreSQL** | Base de datos relacional |
| **Eloquent ORM** | Mapeo objeto-relacional y relaciones entre modelos |
| **JWT** | Autenticación stateless con tokens |
| **Middlewares** | Seguridad, autorización por roles y validación |
| **React** | Interfaz frontend (repositorio separado) |

---

## 📁 Estructura destacada

```
Licoreria_Backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # Lógica de cada recurso (Ventas, Productos, etc.)
│   │   └── Middleware/      # Auth, roles y validaciones
│   └── Models/              # Modelos Eloquent y relaciones
├── database/
│   ├── migrations/          # Estructura de la base de datos
│   └── seeders/             # Datos iniciales
├── routes/
│   └── api.php              # Definición de endpoints REST
└── .env.example             # Variables de entorno de ejemplo
```

**Arquitectura:** MVC · Validaciones en backend · Relaciones Eloquent · Control transaccional en registro de ventas

---

## 🚀 Instalación y desarrollo local

### Prerrequisitos

- PHP >= 8.1
- Composer
- PostgreSQL
- Node.js (para el frontend)

### Pasos

```bash
# 1. Clonar el repositorio
git clone https://github.com/Hualpa7/Licoreria_Backend.git
cd Licoreria_Backend

# 2. Instalar dependencias
composer install

# 3. Configurar variables de entorno
cp .env.example .env
php artisan key:generate

# 4. Configurar la base de datos en .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=licoreria
# DB_USERNAME=tu_usuario
# DB_PASSWORD=tu_contraseña

# 5. Ejecutar migraciones (y seeders opcionales)
php artisan migrate
# php artisan db:seed

# 6. Iniciar el servidor
php artisan serve
```

La API estará disponible en `http://localhost:8000/api`.

---

## 🔐 Autenticación

El sistema utiliza **JWT (JSON Web Tokens)**. Para acceder a los endpoints protegidos:

1. Realizá un `POST /api/login` con tus credenciales.
2. Incluí el token recibido en el header de cada petición:
   ```
   Authorization: Bearer <tu_token>
   ```

---

## 🌐 Frontend

La interfaz de usuario está desarrollada en **React** y se conecta a esta API:

👉 [Licoreria — Frontend (React)](https://github.com/Hualpa7/Licoreria_Frontend)

---

## 📄 Licencia

Este proyecto fue desarrollado como trabajo final de seminario universitario. El contenido y el código son de autoría propia.

---
