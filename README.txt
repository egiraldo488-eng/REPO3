GESTOR DE ENVÍOS ÉBANO
======================

Aplicación web PHP + MySQL con diseño elegante negro y dorado.

FUNCIONES
- Registrar envíos (destinatario, dirección y descripción).
- Listar y buscar envíos.
- Editar y eliminar registros.
- Crear automáticamente la tabla `envios` si no existe.
- Consultas preparadas, escape de salida y token CSRF en formularios.

ARCHIVOS
- index.php: interfaz y operaciones CRUD.
- config.php: conexión a MySQL y creación automática de la tabla.

INSTALACIÓN EN ALWAYSData
1. Descomprime el ZIP en tu computador.
2. Sube index.php y config.php a la carpeta web de tu sitio usando FileZilla.
3. Abre la dirección pública de tu sitio en el navegador.
4. Al abrir la aplicación, intentará conectarse a MySQL y creará la tabla `envios` si no existe.

IMPORTANTE
- La base de datos indicada debe existir y el usuario debe tener permisos para crear tablas.
- Si aparece un error de conexión, confirma en el panel de Alwaysdata el host, nombre de base de datos, usuario y contraseña.
- No publiques config.php ni subas estas credenciales a un repositorio público.
- Como la contraseña se compartió en un chat, es recomendable cambiarla desde el panel del proveedor y actualizar DB_PASS en config.php.
- Esta versión no incluye autenticación de administrador; úsala detrás de una zona protegida si va a estar disponible públicamente.
