# Módulo de IA — Consultas en Lenguaje Natural (CaliFit Sistema POS)

> **El módulo IA es opcional y está desacoplado del núcleo.** Puede desactivarse por completo (`AI_MODULE_ENABLED=false` en `.env`) sin afectar ningún otro módulo del sistema.

## Objetivo
Permitir que el personal de SaborCaliFit formule preguntas en lenguaje natural (ej. *"¿Qué insumos de cocina están por agotarse?"*, *"¿Cuánto vendimos hoy en domicilios?"*) y reciba una respuesta útil, sin necesidad de conocer SQL, manteniendo la seguridad del sistema.

## Arquitectura desacoplada
```
AiQueryService
       ↓
AiProviderInterface   (app/AI/Contracts)
       ↓
GeminiProvider          (app/AI/Providers)
```
El resto del sistema depende únicamente de `AiProviderInterface`. Permite sustituir Gemini por otro proveedor sin reescribir el núcleo.

## Flujo obligatorio
```
Usuario → pregunta en lenguaje natural
   ↓
AiQueryService → GeminiProvider (function calling / structured output)
   ↓
Intención estructurada (JSON), ej: {"intent": "low_stock_ingredients", "filters": {...}}
   ↓
Laravel: validación contra whitelist de intents
   ↓
Laravel: autorización (usuario, rol)
   ↓
Servicio del intent específico (solo lectura) → Eloquent/Query Builder
   ↓
MySQL
   ↓
Resultado → respuesta formateada y amigable
```

## Intenciones permitidas (whitelist — adaptada a SaborCaliFit)

### Inventario e Insumos
- `low_stock_ingredients` — Insumos de cocina con stock bajo (gramos/ml).
- `out_of_stock_ingredients` — Insumos agotados.
- `low_stock_supplements` — Suplementos con stock bajo (unidades).
- `out_of_stock_supplements` — Suplementos agotados.
- `inventory_summary` — Resumen de valoración de inventario (insumos + suplementos).

### Ventas y Facturación
- `sales_summary` — Resumen de ventas (total, cantidad, ticket promedio).
- `sales_by_period` — Ventas filtradas por rango de fechas.
- `sales_by_channel` — Ventas segmentadas por canal (Salón, Domicilios, Para Llevar, Retail).
- `top_selling_products` — Ranking de platos y suplementos más vendidos.

### Clientes
- `list_customers` — Listado de clientes registrados.
- `count_customers` — Conteo de clientes activos.

### Operación de Restaurante
- `active_orders` — Comandas abiertas en salón y despacho.
- `daily_cash_summary` — Resumen de cuadre de caja del día.

Cualquier intención fuera de esta lista se rechaza en código, sin excepción.

## Restricciones no negociables
- Solo lectura: prohibido INSERT/UPDATE/DELETE/DROP/ALTER/TRUNCATE desde cualquier servicio invocado por el módulo IA.
- Sin acceso directo a MySQL, credenciales, ni SQL arbitrario generado por el modelo.
- Minimización de datos enviados al proveedor: solo la pregunta, el esquema de intents permitidos y metadatos mínimos — nunca contraseñas, hashes, tokens ni datos reales de clientes en bruto.

## Fallback
Si el proveedor no responde, hay timeout (8–10s) o error de cuota:
```
Usuario → pregunta → Gemini no disponible → mensaje controlado
```
Mensaje de referencia: *"El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de inventario, ventas y reportes."* Nunca se muestra un error técnico o stack trace.

## Historial (si se implementa)
Guardar únicamente: pregunta, intención detectada, fecha, usuario, estado, tiempo de respuesta. Nunca la respuesta cruda del proveedor con datos sensibles.

## Costos, límites y privacidad
- Se usa el nivel gratuito de Gemini durante desarrollo; no se asume que sea permanente.
- Rate limiting propio en el endpoint del asistente (30 peticiones/min).
- Revisar la política de privacidad del proveedor antes de usar datos de clientes reales.

## Sustitución futura del proveedor
La arquitectura permite migrar de `Gemini Free` → `Gemini Paid` → otro proveedor → modelo local, implementando una nueva clase que satisfaga `AiProviderInterface`, sin tocar `AiQueryService` ni los Services de cada intent.
