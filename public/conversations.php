<?php

// Agregar al principio
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {

    session_start();
    require_once __DIR__ . '/../app/config/db.php';
    require_once __DIR__ . '/../app/core/Auth.php';
    require_once __DIR__ . '/../app/core/Layout.php';
    Auth::init($pdo);
    Auth::requireLogin();
    $user = Auth::user();

    // Obtener nombre de la empresa
    $stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
    $stmt->execute([$user['company_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    $user['company_name'] = $company['name'] ?? 'Empresa';

    // Obtener lista de contactos + estado desde prospects
    $stmt = $pdo->prepare("
        SELECT 
            c.contact_phone, 
            c.contact_name, 
            c.contact_lastname,
            c.unread_count,
            MAX(c.updated_at) as last_message_at,
            p.name as prospect_name,
            p.lastname as prospect_lastname,
            p.status
        FROM conversations c
        LEFT JOIN prospects p ON c.company_id = p.company_id AND c.contact_phone = p.phone
        WHERE c.company_id = ? AND c.user_id = ?
        GROUP BY c.id, c.contact_phone, c.contact_name, c.contact_lastname, c.unread_count, p.name, p.lastname, p.status
        ORDER BY last_message_at DESC");
    $stmt->execute([$user['company_id'], $user['id']]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // Obtener conversación seleccionada
    $selected_phone = $_GET['phone'] ?? ($contacts ? $contacts[0]['contact_phone'] : null);
    $messages = [];
    $contact_name = $selected_phone ? null : null;
    $contact_lastname = $selected_phone ? null : null;

    if ($selected_phone) {
        // Obtener datos del contacto
        $stmt = $pdo->prepare("
            SELECT contact_name, contact_lastname
            FROM conversations
            WHERE company_id = ? AND user_id = ? AND contact_phone = ?
            LIMIT 1
        ");
        $stmt->execute([$user['company_id'], $user['id'], $selected_phone]);
        $contact_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $contact_name = $contact_data['contact_name'] ?? null;
        $contact_lastname = $contact_data['contact_lastname'] ?? null;

    //  Busca en prospects si existe name and lastname
        $prospect_name = null;
        $prospect_lastname = null;

        $stmt = $pdo->prepare("
        SELECT name, lastname, status
        FROM prospects 
        WHERE company_id = ? AND phone = ?
        LIMIT 1
    ");
        $stmt->execute([$user['company_id'], $selected_phone]);
        $prospect = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prospect) {
            $prospect_name = $prospect['name'];
            $prospect_lastname = $prospect['lastname'];
        }

    // Prioridad:
    // 1. Nombre agendado en conversations
    // 2. Nombre de prospectos
    // 3. Número de teléfono
        $display_name =
            $contact_name ??
            $contact_lastname ??
            $prospect_name ??
            $prospect_lastname ??
            $selected_phone;

        // Obtener mensajes
        $stmt = $pdo->prepare("
            SELECT content, direction, sent_at
            FROM messages
            WHERE conversation_id = (
                SELECT id FROM conversations 
                WHERE company_id = ? AND user_id = ? AND contact_phone = ?
            )
            ORDER BY sent_at ASC
        ");
        $stmt->execute([$user['company_id'], $user['id'], $selected_phone]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Marcar como leídos
            $stmt = $pdo->prepare("
            UPDATE conversations 
            SET unread_count = 0 
            WHERE company_id = ? AND user_id = ? AND contact_phone = ?
        ");
        $stmt->execute([$user['company_id'], $user['id'], $selected_phone]);
    }

    // Generar el contenido principal (solo lo que va dentro del layout)
    $contenido = '';
    if ($selected_phone):
        $contenido .= '
        <div class="flex h-full max-w-6xl mx-auto bg-white rounded-xl shadow-sm overflow-hidden">
            <!-- Sidebar: contactos -->
            <div class="w-80 border-r border-gray-200 flex flex-col">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold">Contactos</h2>
                </div>
                <div class="flex-1 overflow-y-auto">
                    ' . (empty($contacts) ? '
                    <div class="p-4 text-center text-gray-500">No hay contactos</div>
                    ' : '');
                    foreach ($contacts as $contact) {
                        $active = $contact['contact_phone'] === $selected_phone ? 'bg-blue-50 border-blue-200' : 'border-transparent';

                        // Prioridad: agendado > prospecto > teléfono
                        $name = $contact['contact_name'] ?? $contact['prospect_name'] ?? $contact['contact_phone'];
                        $lastname = $contact['contact_lastname'] ?? $contact['prospect_lastname'] ?? '';

                        // Mostrar nombre completo
                        $full_name = trim("$name $lastname");
                        if (!$full_name) {
                            $full_name = $contact['contact_phone'];
                        }

                        // Determinar color del icono según estado
                        $status = $contact['status'] ?? 'pendiente';
                        $color_class = match($status) {
                            'interesado' => 'bg-green-500',
                            'no_interesa' => 'bg-red-500',
                            'vendido' => 'bg-green-700',
                            'contactar_despues' => 'bg-orange-500',
                            default => 'bg-gray-400'
                        };

                        // Inicial del nombre completo
                        $initial = strtoupper(substr($full_name, 0, 1));

                        $contenido .= "
                    <a href='?phone=" . urlencode($contact['contact_phone']) . "' class='flex items-center p-4 border-l-4 $active hover:bg-gray-50'>
                        <div class='w-10 h-10 rounded-full $color_class flex items-center justify-center text-white font-bold'>
                            $initial
                        </div>                        
                        <div class='ml-3 flex-1 min-w-0'>
                            <div class='flex justify-between'>
                                <p class='font-medium truncate'>" . htmlspecialchars($full_name) . "</p>
                                " . ($contact['unread_count'] > 0 ? "
                                <span class='inline-flex items-center justify-center bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5'>
                                    " . min(99, $contact['unread_count']) . "
                                </span>" : '') . "
                            </div>
                            <p class='text-sm text-gray-500 truncate'>" . htmlspecialchars($contact['contact_phone']) . "</p>
                            " . ($status !== 'pendiente' ? "
                            <p class='text-xs font-medium " . match($status) {
                                    'interesado' => 'text-green-600',
                                    'no_interesa' => 'text-red-600',
                                    'vendido' => 'text-green-800',
                                    'contactar_despues' => 'text-orange-600',
                                    default => 'text-gray-400'
                                } . "'>" . match($status) {
                                    'interesado' => 'INTERESADO',
                                    'no_interesa' => 'NO LE INTERESA',
                                    'vendido' => 'LE VENDÍ',
                                    'contactar_despues' => 'CONTACTAR MÁS ADELANTE',
                                    default => ''
                                } . "</p>" : '') . "
                        </div>
                    </a>";
                    }
                    $contenido .= '
                </div>
            </div>
    
            <!-- Chat principal -->
            <div class="flex-1 flex flex-col">
                <div class="p-4 border-b border-gray-200 bg-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                                ' . strtoupper(substr($display_name, 0, 1)) . '
                            </div>
                            <div class="ml-3">
                                <h3 class="font-semibold">' . htmlspecialchars($display_name) . '</h3>
                                <p class="text-sm text-gray-500">' . htmlspecialchars($selected_phone) . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos de nombre y apellido -->
                    <div class="mt-3 flex gap-2">
                        <input type="text" id="contactName" placeholder="Nombre" value="' . htmlspecialchars($contact_name ?? '') . '" class="text-sm px-2 py-1.5 border border-gray-300 rounded-md">
                        <input type="text" id="contactLastname" placeholder="Apellido" value="' . htmlspecialchars($contact_lastname ?? '') . '" class="text-sm px-2 py-1.5 border border-gray-300 rounded-md">
                        <button onclick="saveContact()" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded-md hover:bg-blue-700">Guardar</button>
                    </div>
                    
                    <!-- Selector de estado en una nueva línea -->
                    <div class="mt-2">
                        <select id="contactStatus" onchange="updateStatus()" class="text-sm px-2 py-1.5 border border-gray-300 rounded-md">
                            <option value="pendiente" ' . (($prospect['status'] ?? 'pendiente') === 'pendiente' ? 'selected' : '') . '>Sin clasificar</option>
                            <option value="interesado" ' . (($prospect['status'] ?? 'pendiente') === 'interesado' ? 'selected' : '') . '>INTERESADO</option>
                            <option value="no_interesa" ' . (($prospect['status'] ?? 'pendiente') === 'no_interesa' ? 'selected' : '') . '>NO LE INTERESA</option>
                            <option value="vendido" ' . (($prospect['status'] ?? 'pendiente') === 'vendido' ? 'selected' : '') . '>LE VENDÍ</option>
                            <option value="contactar_despues" ' . (($prospect['status'] ?? 'pendiente') === 'contactar_despues' ? 'selected' : '') . '>CONTACTAR MÁS ADELANTE</option>
                        </select>
                    </div>                
                </div>
    
    
                <div id="chatMessages" class="flex-1 overflow-y-auto p-4 bg-gray-50">
                    ' . (empty($messages) ? '<p class="text-gray-500 text-center py-4">No hay mensajes</p>' : '');
                    foreach ($messages as $msg) {
                        $direction = $msg['direction'] === 'out' ? 'justify-end' : 'justify-start';
                        $bubble = $msg['direction'] === 'out' ? 'bg-blue-600 text-white rounded-tr-none' : 'bg-white text-gray-800 border border-gray-200 rounded-tl-none';
                        $contenido .= "
                        <div class='flex $direction mb-2'>
                            <div class='max-w-xs sm:max-w-md px-4 py-2 rounded-2xl $bubble'>
                                <p>" . htmlspecialchars($msg['content']) . "</p>
                                <p class='text-xs mt-1 opacity-70 text-right'>" . date('H:i', strtotime($msg['sent_at'])) . "</p>
                            </div>
                        </div>";
                    }
                    $contenido .= '
                </div>
    
                ' . ($selected_phone ? '
                <div class="px-3 py-2 border-t border-gray-200 bg-white">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Plantillas</label>
                    <select id="templateSelect" class="w-full text-sm px-3 py-1.5 border border-gray-300 rounded-md">
                        <option value="">Selecciona una plantilla...</option>
                        ' . (function() use ($pdo, $user) {
                                $stmt = $pdo->prepare("SELECT id, name, content FROM templates WHERE company_id = ? ORDER BY name");
                                $stmt->execute([$user['company_id']]);
                                $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                $html = '';
                                foreach ($templates as $tpl) {
                                    $html .= '<option value="' . htmlspecialchars($tpl['content'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($tpl['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                                }
                                return $html;
                            })() . '
                    </select>
                </div>
    
                <script>
                document.getElementById("templateSelect").addEventListener("change", function() {
                    const content = this.value;
                    if (content) {
                        let nombre = "Cliente";
                        let apellido = "";
                        ' . (!empty($contact_name) ? 'nombre = ' . json_encode($contact_name) . ';' : '') . '
                        ' . (!empty($contact_lastname) ? 'apellido = ' . json_encode($contact_lastname) . ';' : '') . '
                        ' . (empty($contact_name) && !empty($prospect_name) ? 'nombre = ' . json_encode($prospect_name) . ';' : '') . '
                        ' . (empty($contact_lastname) && !empty($prospect_lastname) ? 'apellido = ' . json_encode($prospect_lastname) . ';' : '') . '
    
                        let finalText = content
                            .replace(/{nombre}/gi, nombre)
                            .replace(/{apellido}/gi, apellido);
                        document.getElementById("messageText").value = finalText;
                    }
                });
                </script>
                ' : '') . '
    
    
                <div class="p-3 border-t border-gray-200 bg-white">
                    <div class="flex gap-2">
                        <input type="text" id="messageText" placeholder="Escribe un mensaje..." class="flex-1 px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-1 focus:ring-blue-500" maxlength="500">
                        <button onclick="sendMessage()" class="bg-blue-600 text-white p-2.5 rounded-full hover:bg-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    
        <script>
        function sendMessage() {
            const text = document.getElementById("messageText").value.trim();
            if (!text) return;
            fetch("/api/send-message.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ phone: "' . $selected_phone . '", text: text })
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    document.getElementById("messageText").value = "";
                    location.reload();
                } else alert("Error: " + (d.message || "No se pudo enviar"));
            });
        }
    
        function saveContact() {
            const name = document.getElementById("contactName").value.trim();
            const lastname = document.getElementById("contactLastname").value.trim();
            fetch("/api/save-contact.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ phone: "' . $selected_phone . '", name, lastname })
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    alert("Contacto guardado");
                    location.reload();
                }
            });
        }
    
        function saveStatus() {
            const status = document.getElementById("contactStatus").value;
            fetch("/api/save-contact-status.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ phone: "' . $selected_phone . '", status })
            }).then(r => r.json()).then(d => {
                if (d.success) {
                alert("Estado actualizado");
                }
            });
        }    
        
        function updateStatus() {
            const status = document.getElementById("contactStatus").value;
            fetch("/api/save-contact-status.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ phone: "' . $selected_phone . '", status })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    // Opcional: mostrar mensaje temporal
                    // alert("Estado actualizado");
                }
            });
        }
        document.getElementById("chatMessages").scrollTop = document.getElementById("chatMessages").scrollHeight;
        
        
        </script>
        ';
    else:
        $contenido = '
        <div class="max-w-4xl mx-auto h-full flex items-center justify-center">
            <div class="text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-2 2v-2z" />
                </svg>
                <h3 class="text-lg font-medium text-gray-900">Selecciona una conversación</h3>
                <p class="text-gray-500 mt-1">Elige un contacto de la lista para ver el historial.</p>
            </div>
        </div>
        ';
    endif;

    // Renderizar con el layout
    Layout::render('Conversaciones', $contenido, $user);

} catch (Exception $e) {
    echo "<h2>Error en conversations.php:</h2>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit;
}
?>