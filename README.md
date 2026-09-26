# Plataforma de avatares

Laravel para avatares publicados en `slug.ia.tinq.pe`. El primer contenido es
una demostración ficticia: las respuestas y sus MP3 son contenido estático
aprobado, no una conversación generativa en vivo.

## Desarrollo local

El proyecto usa MySQL tanto en desarrollo como en pruebas. Crea las bases
locales una vez (la contraseña se solicita de forma interactiva):

```sh
mysql -u root -p -e 'CREATE DATABASE IF NOT EXISTS avatar_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS avatar_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
```

Copia `.env.example` a `.env` si todavía no existe y completa únicamente
`DB_PASSWORD` con tu credencial local. No subas ese archivo al repositorio.
Después prepara los datos de desarrollo:

```sh
php artisan config:clear
php artisan migrate --seed
php artisan storage:link
```

```sh
php artisan serve --port=8088
```

Abre `http://127.0.0.1:8088/llamada`. Pulsa **Iniciar conversación**: ese gesto
habilita el audio del navegador, reproduce el saludo estático y sincroniza los
visemas del archivo Rive. Después queda disponible el micrófono durante un
máximo de 15 segundos.

La transcripción se realiza con Web Speech del navegador durante la llamada y
solo el texto final se envía a Laravel. Laravel consulta el
árbol publicado y los MP3 aprobados en MySQL; Qwen solo selecciona de uno a
tres temas permitidos y nunca redacta respuestas ni consulta la base de datos.
Antes y después de Qwen existe un fallback local determinista para palabras
claras como `seguridad` o `presupuesto`. La aplicación conserva en la sesión
únicamente tema, etapa y variante; no guarda preguntas ni audio del visitante.

El reconocimiento de voz depende de `SpeechRecognition` o
`webkitSpeechRecognition` del navegador. Si el navegador no lo expone, la
llamada sigue disponible, pero muestra un aviso y no intenta subir audio al VPS.

Mientras Anita prepara o reproduce toda una playlist, el micrófono se detiene,
queda deshabilitado y se ve al 10 %. Se reactiva únicamente al terminar.

Las pruebas usan automáticamente la base MySQL aislada `avatar_testing`:

```sh
php artisan test
```

## Servicios externos

- Salad voz se usa únicamente al publicar o regenerar MP3. Nunca se invoca
  durante una llamada pública.
- Salad router ejecuta Qwen3-4B y devuelve solo la selección de tema y etapa.
  Si no está habilitado, el router local por palabras clave mantiene el demo.
- Los archivos de voz se mantienen en `storage/app/public/avatars` y están
  excluidos de Git. En producción viven en el almacenamiento compartido del
  VPS.

## Avatares y voces

Cada avatar tiene su propio `slug`, archivo Rive, árbol/versiones, MP3 y perfil
de voz. Los temas del árbol incluyen `description`, `examples` y `keywords`,
que ayudan al clasificador a reconocer frases naturales; las respuestas siguen
siendo las variantes preaprobadas del árbol.

El modo **Sintética** conserva el perfil `anita`. El modo **Clonada aprobada**
solicita una muestra privada, que Laravel guarda exclusivamente en
`storage/app/private/avatars/...`; no queda disponible en `/storage` ni se
comparte con otro avatar. La validación de consentimiento y derechos ocurre
antes de la carga.

Para publicar una voz clonada, el servicio Voicebox desplegado en Salad debe
ofrecer `POST /v1/cloned/speech` autenticado. Laravel envía un multipart con
`input`, `voice_mode=cloned`, `response_format=mp3` y `voice_sample`, junto con
un `Idempotency-Key` estable por audio. Voicebox debe devolver
`audio_base64`, `duration_ms` y `words`; Laravel deriva los visemas, publica el
MP3 y marca la versión publicada solo cuando todos sus audios están listos.
El código del motor Voicebox no está en este repositorio, por lo que su imagen
de Salad debe implementar ese contrato antes de usar el modo clonado.

La guía de infraestructura y secretos de despliegue está en
[`infrastructure/vps/README.md`](infrastructure/vps/README.md).
