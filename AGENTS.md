# AGENTS.md — Punto de entrada para agentes de IA

Este archivo es la puerta de entrada. Léelo primero, siempre, antes de tocar cualquier código.

## Qué es este proyecto
**CaliFit Sistema POS** — Software a la medida para **SABORCALIFIT | BOCHALEMA**, un negocio de suplementación deportiva y restaurante de alimentación saludable ubicado en Arbolatta Mall, Cali, Colombia. El sistema gestiona punto de venta, inventario con motor de recetas por porciones en gramos, adiciones reutilizables con precio, salón de mesas, comandas de cocina, despachos a domicilio/para llevar, cuadre de caja adaptativo e impresión térmica de 58 mm. Detalle completo en `docs/PLAN_PROYECTO.md`.

## Fuente de verdad
- **Alcance y objetivo del proyecto** → `docs/PLAN_PROYECTO.md`
- **Arquitectura y estructura de carpetas** → `docs/ARQUITECTURA.md`
- **Modelo de datos (mono-negocio)** → `docs/BASE_DATOS.md`
- **Seguridad** → `docs/SEGURIDAD.md`
- **Módulo de IA** → `docs/MODULO_IA.md`
- **Por qué se tomó cada decisión técnica** → `docs/DECISIONES_TECNICAS.md`
- **Plan de fases y qué falta** → `docs/ROADMAP.md`

## Cómo debe comportarse el agente
- Reglas permanentes obligatorias → `.agents/rules/` (léelas todas antes de escribir código; son cortas y accionables por diseño).
- Procesos repetibles → `.agents/workflows/` (usar `implementar-feature.md` para cualquier funcionalidad nueva, `ejecutar-tests.md` tras cualquier cambio, `revisar-codigo.md` antes de cerrar una fase, `preparar-produccion.md` para despliegue).
- Procedimiento específico para extender el asistente de IA → `.agents/skills/ai-query-module/SKILL.md`.

## Qué debes leer antes de modificar código
1. Este archivo.
2. Todas las reglas en `.agents/rules/`.
3. `docs/ROADMAP.md` para saber en qué fase está el proyecto y cuál sigue.
4. El documento de `docs/` específico del módulo que vayas a tocar.

## Reglas críticas (resumen — la fuente completa está en `.agents/rules/`)
- Este es un software **mono-negocio exclusivo** para SaborCaliFit. No existe multi-tenancy ni SaaS.
- El núcleo funciona sin el módulo IA. Nunca al revés.
- El motor de recetas opera en **gramos (`g`) y mililitros (`ml`)** como unidades base internas. Factor de conversión: 1 libra = 500 g.
- Las adiciones son extras reutilizables con precio y receta propia de insumos; nunca platos independientes.
- **Cero Stock Negativo**: toda venta se valida bajo `lockForUpdate` con rechazo atómico si falta un solo gramo de insumo.
- La anulación de ventas revierte simétricamente el consumo del plato base y sus adiciones.
- Los precios cobrados quedan inmutables en `sale_details` y `venta_adicion` (snapshot histórico).
- El módulo IA es solo lectura y nunca ejecuta SQL arbitrario.
- No se avanza de fase con tests fallando.
- No se agregan tecnologías ni se cambian decisiones fundamentales sin documentarlo en `docs/DECISIONES_TECNICAS.md`.

## Identidad visual
- **Primario (Verde Fitness / Esmeralda):** `#10B981` (Hover: `#059669`, Acentos: `#34D399`).
- **Neutro Oscuro (Carbón / Dark Slate):** `#111827` / `#1F2937`.
- **Fondos y Superficies:** `#F9FAFB` y `#FFFFFF`.
- **Estados:** Verde `#10B981` (Libre/Pagado), Amarillo `#F59E0B` (En Cobro/Bajo Stock), Rojo `#EF4444` (Ocupado/Agotado/Cancelado).
