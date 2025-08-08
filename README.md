## 🧾 Sistema de Nómina Académica

- Este proyecto es un sistema de nómina desarrollado en PHP con Bootstrap, diseñado para la gestión académica y financiera de docentes en una institución educativa. Permite registrar docentes, asignarles unidades curriculares, gestionar liquidaciones, pagos, sedes y usuarios del sistema.

## 📌 Características principales

- 📚 **Gestión de docentes**: CRUD de docentes con filtros y paginación.
- 🧾 **Liquidaciones**: Registro de pagos según carga horaria, con visualización detallada.
- 🏫 **Sedes**: Administración de sedes académicas.
- 🎓 **Unidades curriculares**: Registro y asignación a docentes.
- 💵 **Pagos recibidos**: Control de pagos realizados a los docentes.
- 👤 **Gestión de usuarios**: Sistema de autenticación y gestión por roles.
- 🔐 **Roles y permisos**: Usuarios con rol de administrador, dirección, contabilidad y docentes.
- 🛡️ **Super administrador**: Usuario protegido contra eliminación o edición.
- 📄 **Generación de facturas** por docente.
- 📦 **Diseño unificado y responsivo** con Bootstrap.

---

## 💻 Tecnologías utilizadas

- PHP 8.2
- MySQL / MariaDB
- Bootstrap 5
- JavaScript (vanilla)
- SweetAlert2 (para notificaciones)
- XAMPP (entorno de desarrollo local)

---

## ⚙️ Instalación y configuración

1. **Clonar el repositorio**

```bash
git clone https://github.com/tu-usuario/sistema-nomina-academica.git
```
---
2. **Ubicación del proyecto (XAMPP)**
- Si estás utilizando XAMPP, debes colocar el proyecto dentro del directorio:
```bash
C:\xampp\htdocs\
```
---
3. **Importar la base de datos**
- Crear una base de datos en phpMyAdmin (por ejemplo: nomina_academica).
- Importar el archivo nomina_academica.sql incluido en la carpeta /database (debes crearlo si aún no lo has exportado).
---
4. **Configurar conexión a la base de datos**
- Editar el archivo config/db.php con tus credenciales locales:
```bash
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'nomina_academica';
```
---
5. **Acceder al sistema**
- Abre tu navegador y accede a:
```bash
http://localhost/Nomina/index.php
```
---
6. **Credenciales por defecto**
- Usuario: admin
- Contraseña: admin123
---
- 🔐 Este usuario tiene privilegios de super administrador y no puede ser eliminado o editado desde el sistema.
---
**🚧 Estado del proyecto**
- ✅ Módulos principales implementados
- 🔄 Gestión de usuarios en desarrollo continuo
---
**👨‍💻 Autor**
Brayan Villegas Corrales
- Desarrollador Full Stack | Enfocado en soluciones educativas y administrativas.
- GitHub: @villegas07
- Email: brayanvillegas0719@gmail.com
---
**📄 Licencia**
- Este proyecto es propiedad de Brayan Villegas Corrales y está protegido por derechos de autor.
- Licencia de uso restringido
Este software ha sido desarrollado con fines académicos y administrativos. 
- No está autorizado su uso, distribución, copia, modificación ni redistribución sin el consentimiento explícito y por escrito del autor.
---
Queda estrictamente prohibido:
- Usar el código fuente en otros proyectos.
- Comercializar total o parcialmente este sistema.
- Publicar el código sin autorización.
- Todos los derechos reservados.
---
