# Módulo de IA — Consultas en Lenguaje Natural

> **El módulo IA es opcional y está desacoplado del núcleo.** Puede desactivarse por completo (`AI_MODULE_ENABLED=false` en `.env`) sin afectar ningún otro módulo del sistema.

## Objetivo
Permitir que el usuario formule preguntas en lenguaje natural (ej. *"¿Qué productos tienen stock bajo?"*) y reciba una respuesta útil, sin necesidad de conocer SQL, manteniendo el mismo nivel de seguridad y aislamiento que el resto del sistema.

## Arquitectura desacoplada
```
AiQueryService
       ↓
AiProviderInterface   (app/AI/Contracts)
       ↓
GeminiProvider          (app/AI/Providers)
```
El resto del sistema depende únicamente de `AiProviderInterface`. Esto permite sustituir Gemini por otro proveedor comercial, un modelo local u Ollama sin reescribir el núcleo de negocio.

## Flujo obligatorio
```
Usuario → pregunta en lenguaje natural
   ↓
AiQueryService → GeminiProvider (function calling / structured output)
   ↓
Intención estructurada (JSON), ej: {"intent": "low_stock_products", "filters": {...}}
   ↓
Laravel: validación contra whitelist de intents
   ↓
Laravel: autorización (usuario, rol, business_id inyectado por el sistema)
   ↓
Servicio del intent específico (solo lectura) → Eloquent/Query Builder
   ↓
MySQL
   ↓
Resultado → respuesta formateada y amigable
```

## Intenciones permitidas (whitelist — únicas soportadas en esta versión)
`list_customers`, `count_customers`, `list_products`, `low_stock_products`, `out_of_stock_products`, `top_selling_products`, `sales_summary`, `sales_by_period`, `inventory_summary`, `list_suppliers`, `expenses_summary`, `restaurant_tables_status`, `restaurant_orders_open`, `restaurant_deliveries_today`, `restaurant_tips_collected`, `sales_comparison`, `product_margins`.

Cualquier intención fuera de esta lista se rechaza en código, sin excepción, aunque el modelo la devuelva.

### Detalle de Nuevas Intenciones Analíticas (Fase 15)
- **`sales_comparison` (Comparativas Temporales y Franjas Horarias/Turnos):**
  - Soporte nativo de turnos:
    - *Mañana:* `06:00:00` a `11:59:59`
    - *Tarde:* `12:00:00` a `17:59:59`
    - *Noche:* `18:00:00` a `23:59:59`
  - Resolución de fechas relativas (`America/Bogota`): *"hoy vs ayer"*, *"turno tarde comparado con el martes pasado"*, *"esta semana vs semana pasada"*, *"este mes vs mes pasado"*.
  - Salida estructurada: Total actual ($ y N° ventas), Total comparado ($ y N° ventas), Diferencia ($) y Variación porcentual (+X% / -X%).
- **`product_margins` (Análisis de Margen y Rentabilidad con Límite Estricto):**
  - Cálculo unitario: `Margen ($) = sale_price - cost_price`, `Margen (%) = ((sale_price - cost_price) / sale_price) * 100`.
  - Ordenamiento: Menor margen (`order: 'asc'`) o mayor margen (`order: 'desc'`).
  - Límite estricto: Si el usuario solicita un número específico (ej. *"los 3 platos con menor margen"*), el sistema acota el resultado exactamente a 3 registros (no descarga el catálogo completo).
  - Nota de costo $0: Advierte si algún ítem tiene costo registrado en $0 para evitar distorsiones de margen aparente al 100%.

## Disponibilidad 100% y Sinergia con Motor Analítico Determinista Local
Si Gemini está configurado y responde dentro de la ventana de espera (8s), se aprovecha su capacidad de procesamiento semántico. No obstante, si Gemini no está configurado (clave por defecto o vacía), agota el tiempo de espera (timeout) o arroja cualquier excepción de red o cuota:
1. `AiQueryService` activa de inmediato y sin interrupciones el **`DeterministicAiResolver`** (`app/AI/Services/DeterministicAiResolver.php`).
2. El motor determinista resuelve consultas de turnos, comparativas relativas, márgenes, top ventas, stock bajo, agotados y resúmenes con 100% de disponibilidad.
3. El usuario nunca queda bloqueado por caídas de servicios externos de terceros.

## Resumen Nocturno Automatizado por Correo Electrónico (Brevo SMTP)
Sistema desatendido para entrega de reportes de cierre diario a los administradores:
- **Comando Artisan:** `pos:send-daily-summary {--business_id=} {--email=}`
- **Frecuencia y Horario:** Programado en `routes/console.php` diariamente a las `23:30` (11:30 PM) hora de Colombia (`America/Bogota`).
- **Servicio Analítico:** `App\Services\DailySummaryService` agrega métricas aisladas por tenant:
  - Facturación total del día y variación % vs día anterior.
  - Cantidad de transacciones y ticket promedio.
  - Desglose porcentual y montos por método de pago (`sale_payments`).
  - Top 5 productos más vendidos del día.
  - Alerta de inventario crítico con déficit y recomendación de abastecimiento al proveedor.
  - Conclusión ejecutiva redactada por el motor analítico.
- **Mailable y Plantilla:** `App\Mail\DailyBusinessSummaryMail` y `resources/views/emails/daily-summary.blade.php` (diseño HTML responsivo, branding corporativo PuntoStock con banner oficial y logo `apple-touch-icon.png`, footer legal de privacidad/términos y formato monetario colombiano COP).
- **Control Opt-In / Opt-Out (`nightly_report_enabled`):**
  - Columna booleana en la tabla `businesses` (por defecto `true`).
  - Switch interactivo en SuperAdmin (`superadmin.businesses.create` / `edit`) y en la configuración del comercio (`/my-business`).
  - El comando programado `pos:send-daily-summary` filtra estrictamente por `is_active = true` y `nightly_report_enabled = true`.
- **Correo de Bienvenida con Credenciales:**
  - Al dar de alta un comercio desde SuperAdmin, se despacha `App\Mail\WelcomeTenantMail` con las credenciales temporales del administrador y enlace de acceso (`/login`).
  - Flujo protegido: forzado de cambio de clave en primer ingreso (`must_change_password = true`) y manejo defensivo de excepciones SMTP sin interrumpir la transacción de BD.

## Restricciones no negociables
- Solo lectura: prohibido INSERT/UPDATE/DELETE/DROP/ALTER/TRUNCATE desde cualquier servicio invocado por el módulo IA.
- Sin acceso directo a MySQL, credenciales, ni SQL arbitrario generado por el modelo.
- El `business_id` de cada consulta lo determina Laravel a partir del usuario autenticado; nunca lo aporta el modelo ni el texto del usuario.
- Minimización de datos enviados al proveedor: solo la pregunta, el esquema de intents permitidos y metadatos mínimos — nunca contraseñas, hashes, tokens ni datos reales de clientes en bruto.

## Fallback
Si el proveedor no responde, hay timeout (8–10s) o error de cuota:
```
Usuario → pregunta → Gemini no disponible → mensaje controlado
```
Mensaje de referencia: *"El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de clientes, ventas e inventario."* Nunca se muestra un error técnico o stack trace al usuario final.

## Historial (si se implementa)
Guardar únicamente: pregunta, intención detectada, fecha, usuario, `business_id`, estado, tiempo de respuesta. Nunca la respuesta cruda del proveedor con datos sensibles. Debe respetar aislamiento por `business_id` igual que cualquier otra tabla.

## Costos, límites y privacidad
- Se usa el nivel gratuito de Gemini durante desarrollo; no se asume que sea permanente.
- Deben manejarse explícitamente: límites de cuota, timeout, rate limiting propio en el endpoint del asistente.
- Antes de usar datos de clientes reales en producción, revisar la política de privacidad vigente del proveedor de IA elegido y decidir la configuración contractual adecuada.

## Sustitución futura del proveedor
La arquitectura permite migrar de `Gemini Free` → `Gemini Paid` → otro proveedor → modelo local, implementando una nueva clase que satisfaga `AiProviderInterface`, sin tocar `AiQueryService` ni los Services de cada intent.
