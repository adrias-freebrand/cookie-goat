=== Cookie GOAT 🐐 – CMP + GCM v2 Advanced ===
Contributors: cookiegoat
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Cookie GOAT 🐐 es una plataforma de gestión de consentimiento (CMP) completa diseñada para cumplir la legislación europea (RGPD, LSSI y Ley de Cookies 2024/2025) e implementar Google Consent Mode v2 en modo avanzado. El plugin bloquea preventivamente las cookies no esenciales, ofrece banner accesible con decisiones equivalentes, configura granularmente las categorías y genera evidencias verificables del consentimiento.

* Bloqueo previo de etiquetas de analítica y marketing hasta recibir consentimiento.
* Integración avanzada de Google Consent Mode v2 (ad_storage, analytics_storage, ad_user_data y ad_personalization) con pings anónimos en rechazos.
* Escáner automático de cookies y almacenamiento local que alimenta la segunda capa y la plantilla de Política de Cookies.
* Registro cifrado del consentimiento (fecha, hash IP, usuario, versión legal, detalle por categoría) con exportación desde Herramientas.
* Reapertura permanente mediante botón flotante y shortcode `[cookiegoat_preferences]`.
* Banner accesible con texto configurable, tres botones equiparables y segunda capa granular.
* Renovación automática configurable (≤ 24 meses) y reprompt cuando cambian finalidades o versión legal.
* Traducciones listas (fichero POT) e interfaz disponible para traducciones adicionales.

== Installation ==
1. Sube la carpeta `cookie-goat` al directorio `/wp-content/plugins/` o instálala desde el administrador de WordPress.
2. Activa **Cookie GOAT 🐐 – CMP + GCM v2 Advanced** desde la pantalla de Plugins.
3. Accede a **Ajustes → Cookie GOAT 🐐** para personalizar textos, caducidad, categorías y la integración opcional con Google Tag Manager.
4. Opcional: introduce el identificador de contenedor GTM (`GTM-XXXXXXX`) para mostrarlo en la documentación interna del banner (el contenedor se gestiona directamente en GTM).

== Configuration ==
1. Personaliza los textos del banner (título, descripción y etiqueta del botón flotante) y enlaza tu Política de Cookies.
2. Define la duración de renovación del consentimiento (máximo legal 24 meses) y la frecuencia de escaneo automático.
3. Ajusta las descripciones de las categorías (Esenciales, Preferencias, Analíticas, Marketing) para la segunda capa.
4. Guarda los cambios y ejecuta el escaneo inicial desde la sección **Herramientas** para poblar la tabla de cookies.
5. Inserta el shortcode `[cookiegoat_policy_table]` en tu página de Política de Cookies para mostrar la tabla actualizada automáticamente.
6. Coloca el shortcode `[cookiegoat_preferences]` en pie de página si deseas un acceso adicional al modal; el botón flotante siempre permanece disponible.

== Google Tag Manager y Consent Mode ==
1. En GTM crea una etiqueta de **Consent Initialization** que se dispare en todas las páginas.
2. Inserta en esa etiqueta únicamente el contenedor de GTM habitual o los scripts de Google que requieran consentimiento temprano.
3. Cookie GOAT imprime antes de cualquier script de terceros el stub `gtag('consent','default', {...})` con todos los estados en `denied` y habilita `ads_data_redaction` y `url_passthrough`.
4. Tras la decisión del usuario el script frontend ejecuta `gtag('consent','update', {...})` con los estados correctos (`granted` o `denied` por categoría).
5. Cuando se rechaza, el plugin mantiene los pings anónimos y se valida en la red la presencia del parámetro `gcs=G100`.

== Consent Log ==
* Accede a **Herramientas → Consentimientos CMP** para revisar los registros.
* Cada entrada incluye fecha/hora, usuario (o visitante), hash de IP, resumen (granted/partial/denied) y decisión por categoría.
* Los datos se pueden auditar exportando la tabla desde el listado o copiando los valores.

== Shortcodes ==
* `[cookiegoat_preferences]` — Botón accesible que abre el modal de preferencias.
* `[cookiegoat_policy_table]` — Tabla actualizada con nombre, proveedor, finalidad, duración, categoría y mecanismo de revocación.

== Cron & Renewal ==
* Un evento diario (`cookiegoat_daily_event`) verifica la expiración del consentimiento según la caducidad configurada y ejecuta el escaneo automático cuando corresponda.
* Cambiar la versión legal en los ajustes forzará el reprompt y limpieza del consentimiento.

== Validation Checklist ==
1. **Google Tag Assistant:** ejecuta el modo Vista previa y verifica que `ad_storage`, `analytics_storage`, `ad_user_data` y `ad_personalization` aparecen como `denied` antes de interactuar. Tras aceptar parcial o totalmente, comprueba la actualización a `granted` en las categorías habilitadas.
2. **Pestaña Network → collect / consent:** inspecciona las solicitudes de Google (`collect`, `ads`, `gtm`) y valida el parámetro `gcs=G100` cuando se rechaza. Revisa `gcd` para confirmar el estado de cada señal.
3. **Escaneo interno:** tras guardar ajustes, pulsa *Ejecutar escaneo ahora* y verifica que la tabla de la política refleja los resultados.
4. **Bloqueo de scripts:** usa las herramientas de desarrollador para confirmar que las etiquetas registradas con handles `google-analytics`, `google-ads`, etc. permanecen bloqueadas hasta otorgar consentimiento.

== Frequently Asked Questions ==
= ¿Cómo registro scripts adicionales por categoría? =
Usa el filtro `cookiegoat_script_categories` en tu tema o plugin:

```
add_filter( 'cookiegoat_script_categories', function( $registry ) {
    $registry['mi-handle-analytics'] = 'analytics';
    $registry['mi-handle-ads']       = 'marketing';
    return $registry;
} );
```

= ¿Puedo limpiar todos los datos del plugin? =
Sí. Al desinstalar el plugin se eliminan las opciones y la tabla de consentimientos (`wp_cookiegoat_consent_log`).

== Changelog ==
= 1.0.0 =
* Publicación inicial con CMP completo, escáner, registro de consentimientos y compatibilidad con Google Consent Mode v2 avanzado.
