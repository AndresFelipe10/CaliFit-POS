# Módulo de Cola de Impresión Automática Centralizada (ESC/POS Pull-Queue)
## CaliFit Sistema POS — SABORCALIFIT | BOCHALEMA

Este documento describe la arquitectura, configuración y puesta en marcha del módulo de impresión automática centralizada para la impresora térmica de 80 mm (**DigitalPOS DIG-E200I**, 48 columnas en Fuente A) instalada en el local físico.

---

## 1. Problema que Resuelve y Arquitectura Pull-Queue

### Contexto Operativo
En el restaurante SaborCaliFit Bochalema, los meseros y cajeros utilizan celulares o tablets conectadas a la red Wi-Fi o datos para abrir mesas, agregar tandas a cocina, tomar pedidos de despacho o liquidar cuentas.
Anteriormente, imprimir dependía del cuadro de diálogo del navegador móvil (`window.print()`), el cual:
- Exige intervención manual en la pantalla del celular.
- No se conecta directamente a una impresora térmica USB conectada a la PC de caja.
- Puede bloquear ventanas emergentes en navegadores móviles.

### Solución Implementada: Cola de Impresión Centralizada (Pull-Queue)

```
┌────────────────────────────────────────────────────────┐
│     Dispositivos Móviles (Celulares / Tablets)        │
│    - Abrir Mesa        - Nueva Tanda     - Domicilio   │
│    - Liquidar POS      - Pre-cuenta      - Despacho    │
└───────────────────────────┬────────────────────────────┘
                            │
                            ▼ (Operaciones Comerciales)
┌────────────────────────────────────────────────────────┐
│             CaliFit Sistema POS (Servidor)             │
│  1. Ejecuta transacción ACID bajo bloqueo pesimista   │
│  2. EscPosTicketBuilder genera bytes ESC/POS en Base64 │
│  3. PrintQueueService encola el trabajo en print_jobs  │
│     (tipo, referencia_id, contenido, estado='pendiente')│
└───────────────────────────┬────────────────────────────┘
                            │
                            ▲ Polling cada 1 segundo
                            │ (Header: X-Print-Token)
┌───────────────────────────┴────────────────────────────┐
│      PC Windows del Local (Caja / Administración)      │
│  Script PowerShell: scripts/agente-impresion.ps1       │
│  1. POST /api/print/siguiente (toma trabajo atómico)   │
│  2. Decodifica Base64 a Bytes crudos ESC/POS           │
│  3. Envía directamente al Spooler RAW (winspool.drv)   │
│  4. POST /api/print/listo/{id} (confirma impresión)    │
└───────────────────────────┬────────────────────────────┘
                            │ Cable USB o Red TCP 9100
                            ▼
┌────────────────────────────────────────────────────────┐
│   Impresora Térmica 80 mm (DigitalPOS DIG-E200I)       │
│  Corte automático de papel, 48 columnas, Fuente A      │
└────────────────────────────────────────────────────────┘
```

---

## 2. Variables de Entorno y Configuración

En el servidor (`.env`):

```ini
# Habilitar o pausar la cola de impresión centralizada
PRINT_QUEUE_ENABLED=true

# Token secreto de autenticación para el agente del PC local
PRINT_AGENT_TOKEN=genera_un_token_seguro_aqui_con_openssl
```

### Generación de Token Seguro
En la consola del servidor:
```bash
php -r "echo bin2hex(random_bytes(32));"
```
Copia el valor generado y configúralo en `PRINT_AGENT_TOKEN` tanto en el `.env` del servidor como en el script del agente de Windows.

---

## 3. Especificación de Endpoints API (Seguridad X-Print-Token)

Todos los endpoints bajo `/api/print/*` están protegidos por el middleware `PrintAgentToken` que exige el encabezado HTTP:
`X-Print-Token: <PRINT_AGENT_TOKEN>`. Ante un token inválido o ausente, retorna HTTP `401 Unauthorized`.

| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/api/print/siguiente` | Recupera trabajos colgados (>60s) y entrega el trabajo más antiguo en estado `pendiente` bajo `lockForUpdate`. Retorna `id`, `tipo`, `contenido` (Base64) o `null` si no hay trabajos. |
| `POST` | `/api/print/listo/{job}` | Marca el trabajo como `impreso` con timestamp `impreso_at = now()`. |
| `POST` | `/api/print/error/{job}` | Incrementa reintentos. Si `intentos < 5`, regresa a `pendiente`; si alcanza 5 intentos, marca `error`. |

---

## 4. Instalación del Agente en el PC Windows del Local

### Prerrequisitos en el PC
- Windows 10 u 11 con PowerShell 5.1 o superior.
- Impresora **DigitalPOS DIG-E200I** instalada con su driver oficial o genérico "Generic / Text Only" (compartida o con nombre visible en *Panel de Control > Impresoras*).

### Pasos de Puesta en Marcha:

1. **Verificar el nombre de la impresora en Windows:**
   Abre PowerShell y ejecuta:
   ```powershell
   Get-Printer | Select-Object Name, PortName
   ```
   Anota el nombre exacto (por ejemplo: `DigitalPOS DIG-E200I` o `POS-80`).

2. **Ejecutar el script del Agente:**
   Navega a la carpeta del proyecto en el PC:
   ```powershell
   cd "C:\Users\andre\Desktop\Proyectos\CaliFit Sistema POS\scripts"
   .\agente-impresion.ps1 -ServerUrl "http://localhost:8081" -ApiToken "TU_TOKEN_AQUI" -PrinterName "DigitalPOS DIG-E200I"
   ```

   Si el servidor está alojado en un VPS o dominio público:
   ```powershell
   .\agente-impresion.ps1 -ServerUrl "https://pos.saborcalifit.com" -ApiToken "TU_TOKEN_AQUI" -PrinterName "DigitalPOS DIG-E200I"
   ```

3. **Ejecución Automática al Iniciar Windows (Opcional - Servicio en Segundo Plano):**
   Puedes crear una tarea programada en el *Programador de Tareas de Windows (Task Scheduler)*:
   - **Desencadenador:** Al iniciar la sesión del usuario.
   - **Acción:** Iniciar un programa.
   - **Programa:** `powershell.exe`
   - **Argumentos:** `-ExecutionPolicy Bypass -WindowStyle Hidden -File "C:\ruta\scripts\agente-impresion.ps1" -ServerUrl "https://..." -ApiToken "..." -PrinterName "..."`

---

## 5. Mantenimiento y Soporte

### Reencolar un Trabajo Fallido
Si hubo un atasco de papel o la impresora estuvo apagada y un trabajo cayó en error, se puede reactivar inmediatamente desde la consola:
```bash
php artisan print:requeue {id}
```

### Limpieza Automática de Historial
Laravel tiene programada una tarea diaria (`routes/console.php`) que elimina automáticamente los trabajos impresos con más de 3 días de antigüedad, manteniendo la tabla `print_jobs` optimizada y ligera.

