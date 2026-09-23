# Plataforma de avatares

Laravel para avatares publicados en `slug.ia.tinq.pe`. El primer contenido es
una demostración ficticia: las respuestas y sus MP3 son contenido estático
aprobado, no una conversación generativa en vivo.

## Desarrollo local

```sh
php artisan serve --port=8088
```

Abre `http://127.0.0.1:8088/llamada`. Pulsa **Iniciar conversación**: ese gesto
habilita el audio del navegador, reproduce el saludo estático y sincroniza los
visemas del archivo Rive. Después queda disponible el micrófono durante un
máximo de 15 segundos.

Las transcripciones se procesan solo durante la llamada. La aplicación conserva
en la sesión únicamente tema, etapa y variante; no guarda preguntas ni audio
del visitante.

## Servicios externos

- Salad voz se usa únicamente al publicar o regenerar MP3. Nunca se invoca
  durante una llamada pública.
- Salad router ejecuta Qwen3-4B y devuelve solo la selección de tema y etapa.
  Si no está habilitado, el router local por palabras clave mantiene el demo.
- Los archivos de voz se mantienen en `storage/app/public/avatars` y están
  excluidos de Git. En producción viven en el almacenamiento compartido del
  VPS.

La guía de infraestructura y secretos de despliegue está en
[`infrastructure/vps/README.md`](infrastructure/vps/README.md).
