=== AI Code (360group) ===
Contributors: 360group
Tags: ai, openai, code, chat, ocr, pdf, generator
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Plugin que integra el asistente aiCode dentro de WordPress mediante shortcode [aicode], con soporte de archivos y extracción de texto si hay librerías disponibles.

== Instalación ==
1. Comprime la carpeta aicode-plugin y súbela desde Plugins > Añadir nuevo > Subir plugin.
2. Activa el plugin.
3. Define la clave en wp-config.php (recomendado) o en Ajustes > AI Code.
4. Inserta [aicode] en una página.

== Seguridad ==
- Clave de OpenAI en servidor (no expuesta al cliente).
- Nonce y AJAX nativos de WordPress.
