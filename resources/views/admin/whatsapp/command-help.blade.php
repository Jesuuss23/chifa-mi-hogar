<section id="whatsapp-command-help" aria-labelledby="whatsapp-command-help-title"
         class="scroll-mt-6 rounded-none border bg-card p-6 text-foreground shadow-storefront">
    <h2 id="whatsapp-command-help-title" class="text-xl font-bold">Cómo usar los comandos</h2>
    <p class="mt-2 text-sm text-foreground/75">
        Desde uno de los celulares activos registrados aquí, escribe al número de WhatsApp
        del negocio conectado a Meta. El identificador técnico mostrado arriba no es un
        número de teléfono.
    </p>

    <ol class="mt-4 list-inside list-decimal space-y-2 text-sm">
        <li>Guarda los permisos del celular que enviará el mensaje.</li>
        <li>Abre el chat con el número del negocio y envía uno de los textos de abajo.</li>
        <li>Comprueba la respuesta y el historial de comandos de esta página.</li>
    </ol>
    <p class="mt-3 text-sm text-foreground/75">
        Envía solo el comando, con un espacio entre palabras, sin comillas ni texto adicional.
        Se aceptan mayúsculas y minúsculas.
    </p>

    <dl class="mt-5 grid gap-4 md:grid-cols-2">
        <div class="min-w-0 border border-border bg-muted p-4">
            <dt><code class="select-all break-words font-bold">ACTIVAR VENTANA</code></dt>
            <dd class="mt-2 space-y-2 text-sm">
                <p>Registra tu interacción para que el sistema pueda enviar avisos de pedidos a tu celular.</p>
                <p><strong>Permiso:</strong> número activo. No necesita «Controlar tienda».</p>
                <p>Para recibir avisos, activa también «Recibir pedidos». Este comando no abre la tienda.</p>
            </dd>
        </div>
        <div class="min-w-0 border border-border bg-muted p-4">
            <dt><code class="select-all break-words font-bold">ESTADO CHIFA</code></dt>
            <dd class="mt-2 space-y-2 text-sm">
                <p>Consulta si está habilitada la recepción de pedidos. El sistema intenta responder por WhatsApp.</p>
                <p><strong>Permisos:</strong> número activo y «Controlar tienda».</p>
                <p>No cambia el estado de atención.</p>
            </dd>
        </div>
        <div class="min-w-0 border border-border bg-muted p-4">
            <dt><code class="select-all break-words font-bold">ABRIR CHIFA</code></dt>
            <dd class="mt-2 space-y-2 text-sm">
                <p>Habilita la recepción de nuevos pedidos.</p>
                <p><strong>Permisos:</strong> número activo y «Controlar tienda».</p>
            </dd>
        </div>
        <div class="min-w-0 border border-border bg-muted p-4">
            <dt><code class="select-all break-words font-bold">CERRAR CHIFA</code></dt>
            <dd class="mt-2 space-y-2 text-sm">
                <p>Deshabilita nuevos pedidos. El catálogo continúa visible y los pedidos existentes se conservan.</p>
                <p><strong>Permisos:</strong> número activo y «Controlar tienda».</p>
            </dd>
        </div>
    </dl>

    <details class="mt-5 border border-border p-4">
        <summary class="cursor-pointer py-2 font-semibold">No recibo respuestas o avisos de pedidos</summary>
        <div class="mt-3 space-y-3 text-sm leading-relaxed">
            <p>
                Confirma que escribes desde el celular registrado, que está activo y que el destino es
                el número del negocio. El enlace «Chatear con este administrador» del listado abre el
                chat de ese administrador; no envía comandos al negocio.
            </p>
            <p>
                Para los avisos de pedidos, este sistema exige una interacción registrada hace menos
                de 23 horas. Envía <code class="font-semibold">ACTIVAR VENTANA</code> al empezar el turno.
                Un mensaje de texto normal también puede actualizar esa interacción, pero no genera
                una entrada en el historial de comandos.
            </p>
            <p>
                Verifica «Recibir pedidos» para los avisos y «Controlar tienda» para abrir, cerrar o
                consultar el estado. Los mensajes de números inactivos o sin el permiso requerido
                no ejecutan esos comandos ni generan una respuesta de comando.
            </p>
            <p>
                El historial muestra el estado de la tienda al procesar cada comando. «Abierto» junto
                a ACTIVAR VENTANA no significa que ese mensaje abrió la tienda. El historial tampoco
                confirma que la respuesta de WhatsApp haya llegado: el cambio puede haberse aplicado
                aunque falle el envío. Comprueba el estado en el panel antes de repetir una acción.
            </p>
            <p>
                Si los permisos y el chat son correctos, revisa con quien administra el servidor la
                conexión de Meta, la recepción del webhook y el procesamiento de avisos pendientes.
                Activar la ventana no garantiza el reenvío inmediato de todos los avisos anteriores.
            </p>
        </div>
    </details>
</section>
