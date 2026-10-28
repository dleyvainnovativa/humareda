<!DOCTYPE html>
<html lang="es" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · Humareda Prime</title>

    {{-- Apply saved theme before paint to avoid a flash --}}
    <script>
        (function() {
            var m = document.cookie.match(/(?:^|; )hp-theme=([^;]+)/);
            var t = m ? m[1] : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>

    {{-- Favicons --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('img/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    {{-- Bootstrap 5 + theme.css + app.js via Vite --}}
    @vite(['resources/css/theme.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body>
    <div class="hp-app">

        <div class="hp-main">
            <main class="hp-content">
                

# Política de Privacidad y Aviso de Privacidad Integral
## Restaurante Humareda

**Última actualización:** 26 de octubre de 2026

### 1. Identidad y domicilio del responsable

Restaurante Humareda, operado por **[NOMBRE O RAZÓN SOCIAL DEL RESPONSABLE]**, con domicilio en **[DOMICILIO COMPLETO, CIUDAD, ESTADO, MÉXICO]**, es responsable del tratamiento y protección de los datos personales proporcionados por sus clientes mediante sus canales digitales, incluyendo WhatsApp Business, su asistente virtual de reservaciones y los demás medios de contacto habilitados por el establecimiento.

Para cualquier asunto relacionado con la privacidad de sus datos personales, puede contactarnos a través de:

- **Correo electrónico:** [CORREO DE PRIVACIDAD]
- **Teléfono o WhatsApp:** [NÚMERO DE CONTACTO]

### 2. Datos personales que recopilamos

Para brindar nuestros servicios de atención al cliente y gestión de reservaciones, podemos recopilar los siguientes datos personales:

- Nombre del cliente.
- Número telefónico asociado a WhatsApp.
- Fecha y hora solicitadas para la reservación.
- Número de personas que asistirán.
- Preferencias relacionadas con la reservación.
- Mensajes e información proporcionados voluntariamente durante la conversación.
- Identificadores y datos técnicos necesarios para el funcionamiento de la integración con WhatsApp Business.

No solicitamos datos bancarios, contraseñas ni información financiera mediante nuestro asistente virtual de reservaciones.

No solicitamos datos personales sensibles como requisito para reservar. En caso de que un cliente proporcione voluntariamente información sensible, se tratará únicamente cuando exista una finalidad legítima y se cumplan los requisitos legales de consentimiento aplicables.

### 3. Finalidades del tratamiento

Los datos personales recopilados serán utilizados para las siguientes finalidades primarias:

1. Registrar y administrar reservaciones en Restaurante Humareda.
2. Confirmar la disponibilidad de mesas y horarios.
3. Enviar confirmaciones, recordatorios y actualizaciones relacionadas con las reservaciones.
4. Atender solicitudes de modificación o cancelación.
5. Responder consultas relacionadas con el restaurante, horarios, servicios y disponibilidad.
6. Brindar atención mediante nuestro asistente virtual y, cuando sea necesario, mediante personal del establecimiento.
7. Mantener registros operativos de las reservaciones y atender incidencias relacionadas con el servicio.
8. Proteger nuestros sistemas y prevenir usos no autorizados.

**Finalidades secundarias:** En caso de implementar comunicaciones promocionales, ofertas, invitaciones o encuestas de satisfacción, se solicitará el consentimiento correspondiente cuando sea necesario y se ofrecerá un mecanismo para rechazar dichos mensajes.

Los clientes podrán solicitar dejar de recibir comunicaciones promocionales sin que esto afecte la prestación de los servicios de reservación.

### 4. Uso del asistente virtual y WhatsApp Business

Restaurante Humareda utiliza un asistente virtual automatizado conectado con la plataforma WhatsApp Business de Meta para facilitar la comunicación con sus clientes.

Al interactuar con el asistente:

- Los mensajes podrán ser procesados automáticamente para interpretar solicitudes y administrar reservaciones.
- Los datos necesarios podrán registrarse en los sistemas de gestión del restaurante.
- Las respuestas podrán ser generadas automáticamente o por personal autorizado.
- Algunas conversaciones podrán revisarse para resolver incidencias y mejorar la calidad del servicio.

El tratamiento de la información mediante WhatsApp también está sujeto a las condiciones y políticas de privacidad aplicables de Meta y WhatsApp, disponibles en https://www.whatsapp.com/legal/privacy-policy.

El asistente virtual no constituye un canal para compartir información financiera confidencial, contraseñas ni documentos de identificación que no sean necesarios para la prestación del servicio.

### 5. Servicios de terceros y transferencias de información

Para operar el servicio de reservaciones, Restaurante Humareda podrá utilizar proveedores tecnológicos que intervengan en el procesamiento, almacenamiento, transmisión o administración de información.

Estos proveedores pueden incluir:

- **Meta Platforms y WhatsApp Business Platform:** Para recibir y enviar mensajes.
- **Proveedores de alojamiento e infraestructura tecnológica:** Para operar los sistemas de reservaciones.
- **Proveedores de automatización e inteligencia artificial:** Cuando sean utilizados para interpretar mensajes y generar respuestas.
- **Prestadores de soporte tecnológico:** Cuando sea necesario para mantener y dar soporte a los sistemas.

El acceso y tratamiento de información por parte de dichos proveedores estará limitado a las finalidades del servicio y sujeto a las obligaciones contractuales y legales aplicables.

Cuando se realicen transferencias de datos personales que legalmente requieran consentimiento, este será solicitado conforme a la legislación mexicana.

### 6. Conservación de los datos personales

Los datos personales se conservarán únicamente durante el tiempo necesario para administrar las reservaciones, prestar el servicio, resolver incidencias y cumplir con las obligaciones legales aplicables.

Una vez que la información deje de ser necesaria, será eliminada, anonimizada o bloqueada, según corresponda y de conformidad con los periodos de conservación legalmente exigibles.

Los periodos aplicables podrán variar dependiendo del tipo de dato, la finalidad para la que fue obtenido y las obligaciones legales de conservación.

### 7. Seguridad de la información

Restaurante Humareda procurará implementar medidas administrativas, técnicas y físicas razonables para proteger los datos personales contra pérdida, alteración, destrucción, acceso, tratamiento o uso no autorizado.

El acceso a los datos personales estará limitado al personal autorizado y a los proveedores que requieran dicha información para prestar los servicios correspondientes.

Sin embargo, ningún sistema de transmisión o almacenamiento electrónico puede garantizar seguridad absoluta.

### 8. Derechos ARCO

De conformidad con la legislación mexicana aplicable, los titulares de datos personales podrán ejercer sus derechos de:

- **Acceso:** Conocer qué datos personales tenemos y cómo los utilizamos.
- **Rectificación:** Solicitar la corrección de información incorrecta o desactualizada.
- **Cancelación:** Solicitar la cancelación de sus datos personales cuando legalmente proceda.
- **Oposición:** Oponerse al tratamiento de sus datos personales en los casos previstos por la ley.

Para ejercer cualquiera de estos derechos, el titular deberá enviar una solicitud al correo electrónico **[CORREO DE PRIVACIDAD]** con la siguiente información:

1. Nombre del titular y medio para recibir notificaciones.
2. Documentación necesaria para acreditar su identidad o representación legal.
3. Descripción clara de los datos personales relacionados con su solicitud.
4. Derecho que desea ejercer.
5. Información adicional que facilite localizar sus datos, cuando corresponda.

Las solicitudes serán atendidas dentro de los plazos establecidos por la legislación aplicable.

### 9. Eliminación de datos personales

Los clientes podrán solicitar la eliminación de su información personal asociada a las reservaciones y conversaciones con nuestro asistente virtual.

Para hacerlo, deberán enviar un correo electrónico a **[CORREO DE PRIVACIDAD]** con el asunto:

**Solicitud de eliminación de datos personales — Humareda**

También podrán solicitar orientación a través del WhatsApp oficial del restaurante.

Una vez verificada la identidad del solicitante, se analizará la procedencia de la solicitud y se eliminarán o cancelarán los datos correspondientes, salvo aquellos que deban conservarse por obligaciones legales o motivos legítimos permitidos por la ley.

La eliminación de datos de los sistemas del restaurante no implica necesariamente la eliminación de información conservada independientemente por WhatsApp o Meta conforme a sus propias políticas.

### 10. Limitación y revocación del consentimiento

Los titulares podrán solicitar la limitación del uso o divulgación de sus datos personales, así como revocar su consentimiento cuando legalmente corresponda.

Para ello podrán escribir al correo **[CORREO DE PRIVACIDAD]**, indicando el tratamiento que desean limitar o el consentimiento que desean revocar.

La revocación podría impedir la continuidad de determinados servicios cuando el tratamiento de la información sea indispensable para proporcionarlos.

### 11. Uso de inteligencia artificial

El asistente virtual de Humareda podrá utilizar tecnologías de inteligencia artificial para comprender mensajes, identificar solicitudes de reservación y generar respuestas automatizadas.

Cuando se empleen proveedores externos de inteligencia artificial, se procurará limitar el envío de información a los datos necesarios para procesar la solicitud y aplicar las medidas de protección correspondientes.

Los clientes podrán solicitar atención por parte del personal del restaurante cuando dicho canal se encuentre disponible.

### 12. Modificaciones a la política de privacidad

Restaurante Humareda se reserva el derecho de actualizar esta Política de Privacidad para reflejar modificaciones en sus servicios, procesos tecnológicos, obligaciones legales o prácticas de tratamiento de información.

Cualquier modificación será publicada en la misma dirección electrónica donde se encuentre disponible este documento.

### 13. Legislación aplicable

La presente Política de Privacidad se rige por la legislación aplicable de los Estados Unidos Mexicanos, incluyendo la Ley Federal de Protección de Datos Personales en Posesión de los Particulares y demás disposiciones que resulten aplicables.

### 14. Contacto

Para consultas, aclaraciones, solicitudes de acceso, rectificación, cancelación, oposición o eliminación de datos personales, comuníquese con:

**Restaurante Humareda**

Responsable legal: [NOMBRE O RAZÓN SOCIAL]

Domicilio: [DOMICILIO COMPLETO]

Correo electrónico: [CORREO DE PRIVACIDAD]

WhatsApp: [NÚMERO OFICIAL]

---

**Fecha de última actualización:** 26 de octubre de 2026.
            </main>
        </div>
    </div>

    <div class="hp-toast-wrap"></div>
    @stack('scripts')
</body>

</html>
