# NOTAS DE DECISIONES TÉCNICAS

## Arquitectura: por qué BookingService separado del Controller

Toda la lógica de negocio vive en `BookingService`. El controller solo valida el request HTTP y delega. Esto permite:
- Tests unitarios que no dependen de HTTP ni de `actingAs()`
- Defender cada decisión en entrevista técnica señalando una sola clase
- Reutilizar la lógica desde comandos Artisan o eventos sin duplicar código

## Timezone: America/Bogota en todas partes

Las fechas entran y se validan siempre en `America/Bogota`. Se almacenan en UTC en la BD (comportamiento default de Laravel con `->timestamp()`). Al leer, Carbon reconvierte automáticamente. Nunca se mezclan zonas horarias dentro de la lógica de negocio.

## Festivos Colombia 2026: lista hardcodeada

Se optó por una constante `HOLIDAYS_2026` en `BookingService` en lugar de una tabla en BD porque:
1. La prueba técnica no requiere administración dinámica de festivos
2. Evita una join extra en cada validación
3. Los festivos de un año son un conjunto cerrado y conocido

## Solapamiento: la query crítica

```sql
starts_at < nueva_ends_at AND ends_at > nueva_starts_at AND service_id = X AND status = active
```

Esta condición detecta cualquier traslape parcial o total. El filtro es por `service_id` porque cada `Service` tiene un `professional_id` — si el servicio está ocupado, el profesional está ocupado.

## Matriz de reembolso: orden de evaluación

1. **Primero** se evalúa `non_refundable` → siempre 0%, sin importar plan
2. **Luego** se evalúa el plan del usuario
3. Premium: umbrales en 4h y 1h
4. Standard: umbrales en 24h y 4h

El orden importa porque un usuario premium con servicio non_refundable NO tiene reembolso.

## seed.json: inconsistencias intencionales

El archivo tiene:
- `duration_minutes` como string en un caso (`"45"`) → el seeder castea con `(int)`
- `price` como string en varios casos → el seeder castea con `(float)`
- Usuario sin campo `plan` → el seeder usa `'standard'` como default
- `non_refundable` ausente en un servicio → el seeder usa `false` como default
- Fechas en formatos distintos (`"2026-06-15 09:00:00"` vs `"2026-06-16T10:30:00"`)
  → Carbon::parse() maneja ambos formatos sin problema

Estas inconsistencias son intencionales para evaluar robustez del seeder.

## Tests: sin HTTP

Los 8 tests en `BookingServiceTest` instancian `BookingService` directamente y usan `RefreshDatabase`. Esto es más rápido y más limpio que feature tests HTTP. En entrevista: "los tests prueban la lógica, no el framework".

## Límite activo: solo reservas futuras

El límite de 3 reservas activas cuenta únicamente `starts_at > now()`. Una reserva activa del pasado no bloquea nuevas reservas (el servicio ya se prestó, aunque no se canceló formalmente).
