<?php
use JBZoo\MermaidPHP\Graph;
use JBZoo\MermaidPHP\Link;
use JBZoo\MermaidPHP\Node;

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/vendor/autoload.php';
require_login();

$title = 'Documentación';
$roleLabels = db()->getListRoles();
$user = auth_user();
$userRoleCode = is_array($user) && isset($user['role']) ? (string) $user['role'] : '';
$roleLabel = isset($roleLabels[$userRoleCode])
    ? $roleLabels[$userRoleCode]
    : 'Usuario';
$roleTemplateMap = array(
    'admin' => 'detail-role-admin',
    'operador' => 'detail-role-operator',
    'tecnico' => 'detail-role-technician',
    'consulta' => 'detail-role-consult',
    'personal' => 'detail-role-personal',
);
$currentRoleTemplate = isset($roleTemplateMap[$userRoleCode]) ? $roleTemplateMap[$userRoleCode] : '';
$printMode = isset($_GET['print']) && $_GET['print'] === '1';
$title = $printMode ? 'Documentación imprimible' : $title;

$makeFlowchart = static function (array $nodeDefinitions, array $linkDefinitions): string {
    $graph = new Graph(array('direction' => Graph::TOP_BOTTOM));
    foreach ($nodeDefinitions as $definition) {
        $graph->addNode(new Node($definition[0], $definition[1], $definition[2]));
    }
    foreach ($linkDefinitions as $definition) {
        $graph->addLink(new Link(
            $graph->getNode($definition[0]),
            $graph->getNode($definition[1]),
            isset($definition[2]) ? $definition[2] : '',
            isset($definition[3]) ? $definition[3] : Link::ARROW
        ));
    }

    return strtr((string) $graph, array(
        '#aacute;' => 'á',
        '#eacute;' => 'é',
        '#iacute;' => 'í',
        '#oacute;' => 'ó',
        '#uacute;' => 'ú',
        '#ntilde;' => 'ñ',
        '#iquest;' => '¿',
        '#iexcl;' => '¡',
        '#middot;' => '·',
    ));
};

$documentationDiagrams = array(
    'overview' => $makeFlowchart(
        array(
            array('asset', 'Identificar el bien', Node::STADIUM),
            array('request', '¿Hay una solicitud o incidencia?', Node::RHOMBUS),
            array('ticket', 'Registrar ticket asociado al bien', Node::SQUARE),
            array('work', '¿Se realiza un trabajo?', Node::RHOMBUS),
            array('maintenance', 'Registrar mantenimiento y vincular ticket si aplica', Node::SQUARE),
            array('history', 'Consultar historial e informe del bien', Node::STADIUM),
        ),
        array(
            array('asset', 'request'),
            array('request', 'ticket', 'Sí'),
            array('request', 'work', 'No'),
            array('ticket', 'work'),
            array('work', 'maintenance', 'Sí'),
            array('work', 'history', 'No'),
            array('maintenance', 'history'),
        )
    ),
    'tickets' => $makeFlowchart(
        array(
            array('register', 'Registrar solicitud del bien', Node::STADIUM),
            array('state', 'Elegir el estado que refleja la atención', Node::RHOMBUS),
            array('initiated', 'Iniciado', Node::ROUND),
            array('progress', 'En progreso', Node::ROUND),
            array('delayed', 'Con retraso', Node::ROUND),
            array('waiting', 'En espera', Node::ROUND),
            array('finished', 'Finalizado', Node::ROUND),
            array('followup', 'Añadir seguimiento si corresponde', Node::SQUARE),
            array('history', 'Guardar cambios en el historial', Node::STADIUM),
        ),
        array(
            array('register', 'state'),
            array('state', 'initiated', 'Alternativa'),
            array('state', 'progress', 'Alternativa'),
            array('state', 'delayed', 'Alternativa'),
            array('state', 'waiting', 'Alternativa'),
            array('state', 'finished', 'Alternativa'),
            array('state', 'followup', 'Opcional'),
            array('initiated', 'history'),
            array('progress', 'history'),
            array('delayed', 'history'),
            array('waiting', 'history'),
            array('finished', 'history'),
            array('followup', 'history'),
        )
    ),
    'maintenances' => $makeFlowchart(
        array(
            array('start', 'Registrar mantenimiento · Iniciado', Node::STADIUM),
            array('review', 'Revisión', Node::ROUND),
            array('complete', 'Finalizado', Node::STADIUM),
        ),
        array(
            array('start', 'review', 'Enviar a revisión'),
            array('start', 'complete', 'Finalizar'),
            array('review', 'complete', 'Finalizar'),
            array('complete', 'review', 'Reabrir para revisión'),
        )
    ),
    'personal' => $makeFlowchart(
        array(
            array('login', 'Personal inicia sesión', Node::STADIUM),
            array('asset', 'Seleccionar el bien y describir la solicitud', Node::SQUARE),
            array('ai', '¿Usar propuesta de IA?', Node::RHOMBUS),
            array('proposal', 'Revisar y corregir la propuesta', Node::SQUARE),
            array('submit', 'Enviar ticket', Node::SQUARE),
            array('followupDecision', '¿Desea añadir un seguimiento?', Node::RHOMBUS),
            array('followup', 'Añadir seguimiento si el ticket no está finalizado', Node::SQUARE),
            array('track', 'Consultar estado y seguimientos propios', Node::STADIUM),
        ),
        array(
            array('login', 'asset'),
            array('asset', 'ai'),
            array('ai', 'proposal', 'Sí'),
            array('ai', 'submit', 'No'),
            array('proposal', 'submit'),
            array('submit', 'followupDecision'),
            array('followupDecision', 'followup', 'Sí'),
            array('followupDecision', 'track', 'No'),
            array('followup', 'track'),
        )
    ),
    'role-admin' => $makeFlowchart(
        array(
            array('login', 'Administrador inicia sesión', Node::STADIUM),
            array('task', '¿Qué tarea realizará?', Node::RHOMBUS),
            array('users', 'Crear o actualizar cuentas, roles y estado', Node::SQUARE),
            array('operations', 'Gestionar inventario, tickets o mantenimientos', Node::SQUARE),
            array('imports', 'Importar datos o sincronizar asignaciones', Node::SQUARE),
            array('finish', 'Verificar resultado y cerrar la tarea', Node::STADIUM),
        ),
        array(
            array('login', 'task'),
            array('task', 'users', 'Usuarios'),
            array('task', 'operations', 'Operación'),
            array('task', 'imports', 'Importación'),
            array('users', 'finish'),
            array('operations', 'finish'),
            array('imports', 'finish'),
        )
    ),
    'role-operator' => $makeFlowchart(
        array(
            array('login', 'Operador inicia sesión', Node::STADIUM),
            array('task', 'Seleccionar gestión', Node::RHOMBUS),
            array('inventory', 'Importar bienes o sincronizar asignaciones', Node::SQUARE),
            array('maintenance', 'Registrar o actualizar mantenimiento', Node::SQUARE),
            array('ticket', 'Registrar ticket, seguimiento o cambio de estado', Node::SQUARE),
            array('finish', 'Verificar el resultado de la operación', Node::STADIUM),
        ),
        array(
            array('login', 'task'),
            array('task', 'inventory', 'Inventario'),
            array('task', 'maintenance', 'Mantenimiento'),
            array('task', 'ticket', 'Tickets'),
            array('inventory', 'finish'),
            array('maintenance', 'finish'),
            array('ticket', 'finish'),
        )
    ),
    'role-technician' => $makeFlowchart(
        array(
            array('login', 'Técnico inicia sesión', Node::STADIUM),
            array('ticket', 'Revisar ticket e historial del bien', Node::SQUARE),
            array('followup', 'Registrar seguimiento y actualizar estado', Node::SQUARE),
            array('work', '¿Se realiza un mantenimiento?', Node::RHOMBUS),
            array('maintenance', 'Registrar mantenimiento y vincular ticket opcional del mismo bien', Node::SQUARE),
            array('status', 'Actualizar estado del mantenimiento', Node::SQUARE),
            array('finish', 'Consultar la trazabilidad registrada', Node::STADIUM),
        ),
        array(
            array('login', 'ticket'),
            array('ticket', 'followup'),
            array('followup', 'work'),
            array('work', 'maintenance', 'Sí'),
            array('work', 'finish', 'No'),
            array('maintenance', 'status'),
            array('status', 'finish'),
        )
    ),
    'role-consult' => $makeFlowchart(
        array(
            array('login', 'Usuario Consulta inicia sesión', Node::STADIUM),
            array('list', 'Abrir listado de tickets', Node::SQUARE),
            array('search', 'Buscar o filtrar tickets', Node::SQUARE),
            array('detail', 'Ver resumen, estado e historial disponible', Node::SQUARE),
            array('readonly', 'Solo lectura: no crear ni modificar', Node::STADIUM),
        ),
        array(
            array('login', 'list'),
            array('list', 'search'),
            array('search', 'detail'),
            array('detail', 'readonly'),
        )
    ),
    'imports' => $makeFlowchart(
        array(
            array('role', 'Administrador u Operador', Node::STADIUM),
            array('source', 'Elegir proceso: importar datos o sincronizar asignaciones', Node::SQUARE),
            array('validate', 'Validar y procesar la fuente', Node::SQUARE),
            array('persist', 'Guardar los datos válidos', Node::SQUARE),
            array('cache', 'Intentar actualizar la caché local', Node::SQUARE),
            array('stale', 'Si no hay caché vigente: usar datos locales o mostrar aviso', Node::ROUND),
            array('views', 'Consultar los datos disponibles en las vistas', Node::STADIUM),
        ),
        array(
            array('role', 'source'),
            array('source', 'validate'),
            array('validate', 'persist', 'Importación'),
            array('validate', 'cache', 'Sincronización'),
            array('persist', 'cache', 'Best-effort', Link::DOTTED),
            array('persist', 'views'),
            array('cache', 'views', 'Si está disponible', Link::DOTTED),
            array('cache', 'stale', 'Fallo o vencida'),
            array('stale', 'views', 'Continuar con último dato'),
        )
    ),
    'ai' => $makeFlowchart(
        array(
            array('input', 'Completar relato o texto narrativo', Node::STADIUM),
            array('send', 'Solicitar asistencia al servicio NVIDIA Build', Node::SQUARE),
            array('available', '¿Respuesta válida?', Node::RHOMBUS),
            array('proposal', 'Recibir propuesta de redacción', Node::SQUARE),
            array('fallback', 'Usar fallback local solo si el flujo lo admite', Node::SQUARE),
            array('manual', 'Sin fallback: mostrar aviso y editar manualmente', Node::SQUARE),
            array('review', 'Revisar y corregir los campos', Node::SQUARE),
            array('decision', '¿Guardar el formulario?', Node::RHOMBUS),
            array('save', 'Guardar manualmente el registro', Node::STADIUM),
            array('discard', 'Descartar o seguir editando', Node::STADIUM),
        ),
        array(
            array('input', 'send'),
            array('send', 'available'),
            array('available', 'proposal', 'Sí'),
            array('available', 'fallback', 'No, con fallback'),
            array('available', 'manual', 'No, sin fallback'),
            array('proposal', 'review'),
            array('fallback', 'review'),
            array('manual', 'review'),
            array('review', 'decision'),
            array('decision', 'save', 'Sí'),
            array('decision', 'discard', 'No'),
        )
    ),
    'renditions' => $makeFlowchart(
        array(
            array('scope', 'Definir período y alcance del informe', Node::STADIUM),
            array('data', 'Reunir mantenimientos y tickets asociados', Node::SQUARE),
            array('ai', '¿IA configurada y respuesta válida?', Node::RHOMBUS),
            array('nvidia', 'Generar narrativa con NVIDIA Build', Node::SQUARE),
            array('fallback', 'Generar narrativa local de respaldo', Node::SQUARE),
            array('markdown', 'Editar y revisar el Markdown', Node::SQUARE),
            array('draft', 'Guardar como borrador', Node::ROUND),
            array('approval', '¿Confirmar aprobación?', Node::RHOMBUS),
            array('approved', 'Guardar como aprobado e inmutable', Node::STADIUM),
            array('output', 'Imprimir o descargar .md', Node::STADIUM),
        ),
        array(
            array('scope', 'data'),
            array('data', 'ai'),
            array('ai', 'nvidia', 'Sí'),
            array('ai', 'fallback', 'No o fallo'),
            array('nvidia', 'markdown'),
            array('fallback', 'markdown'),
            array('markdown', 'draft'),
            array('draft', 'approval'),
            array('approval', 'draft', 'No: seguir editando'),
            array('approval', 'approved', 'Sí'),
            array('draft', 'output', 'Vista previa'),
            array('approved', 'output'),
        )
    ),
    'architecture' => $makeFlowchart(
        array(
            array('user', 'Persona autenticada con un rol', Node::STADIUM),
            array('ui', 'Interfaz web Bootstrap', Node::SQUARE),
            array('pages', 'Páginas PHP y endpoints JSON', Node::SQUARE),
            array('repos', 'Repositorios y reglas de negocio', Node::SQUARE),
            array('sqlite', 'SQLite: storage/db.db', Node::STADIUM),
            array('files', 'CSV, caché y logs', Node::SQUARE),
            array('external', 'Servicios opcionales: NVIDIA, PostgreSQL legacy y avatares', Node::ROUND),
            array('backup', 'Backups verificables', Node::STADIUM),
        ),
        array(
            array('user', 'ui'),
            array('ui', 'pages'),
            array('pages', 'repos'),
            array('repos', 'sqlite'),
            array('pages', 'files'),
            array('pages', 'external', 'Opcional', Link::DOTTED),
            array('sqlite', 'backup'),
        )
    ),
    'lifecycle' => $makeFlowchart(
        array(
            array('property', 'Bien inventariado', Node::STADIUM),
            array('request', 'Solicitud o incidencia reportada', Node::ROUND),
            array('ticket', 'Ticket e historial de atención', Node::SQUARE),
            array('work', 'Trabajo técnico realizado', Node::ROUND),
            array('maintenance', 'Mantenimiento registrado', Node::SQUARE),
            array('history', 'Historial consolidado del bien', Node::SQUARE),
            array('report', 'Reporte o Informe de Rendición', Node::STADIUM),
            array('backup', 'Respaldo y recuperación', Node::STADIUM),
        ),
        array(
            array('property', 'request', 'Si se reporta'),
            array('request', 'ticket'),
            array('property', 'work', 'Si se interviene'),
            array('work', 'maintenance'),
            array('ticket', 'maintenance', 'Vínculo opcional del mismo bien', Link::DOTTED),
            array('ticket', 'history'),
            array('maintenance', 'history'),
            array('history', 'report'),
            array('history', 'backup', 'Copia de seguridad', Link::DOTTED),
        )
    ),
);

$sections = array(
    array('id' => 'overview', 'title' => 'Cómo funciona SysTec', 'icon' => 'bi-diagram-3'),
    array('id' => 'foundations', 'title' => 'Fundamentos y garantías', 'icon' => 'bi-bricks'),
    array('id' => 'lifecycle', 'title' => 'Ciclo de vida del dato', 'icon' => 'bi-arrow-repeat'),
    array('id' => 'roles', 'title' => 'Roles y casos de uso', 'icon' => 'bi-people'),
    array('id' => 'states', 'title' => 'Estados y transiciones', 'icon' => 'bi-signpost-split'),
    array('id' => 'assets', 'title' => 'Bienes e inventario', 'icon' => 'bi-box-seam'),
    array('id' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bi-speedometer2'),
    array('id' => 'maintenances', 'title' => 'Mantenimientos', 'icon' => 'bi-tools'),
    array('id' => 'renditions', 'title' => 'Informes de Rendición', 'icon' => 'bi-file-earmark-richtext'),
    array('id' => 'tickets', 'title' => 'Tickets de soporte', 'icon' => 'bi-ticket-detailed'),
    array('id' => 'personal', 'title' => 'Portal Personal', 'icon' => 'bi-person-workspace'),
    array('id' => 'reports', 'title' => 'Historiales e informes', 'icon' => 'bi-file-earmark-text'),
    array('id' => 'imports', 'title' => 'Importación y sincronización', 'icon' => 'bi-arrow-repeat'),
    array('id' => 'administration', 'title' => 'Usuarios y cuenta', 'icon' => 'bi-shield-lock'),
    array('id' => 'migrations', 'title' => 'Migraciones y datos', 'icon' => 'bi-database'),
    array('id' => 'security', 'title' => 'Seguridad y sesiones', 'icon' => 'bi-shield-check'),
    array('id' => 'operations', 'title' => 'Operación y despliegue', 'icon' => 'bi-server'),
    array('id' => 'ai', 'title' => 'Asistencia con IA', 'icon' => 'bi-magic'),
    array('id' => 'good-practices', 'title' => 'Buenas prácticas', 'icon' => 'bi-check2-circle'),
    array('id' => 'limits', 'title' => 'Alcance y límites', 'icon' => 'bi-exclamation-diamond'),
    array('id' => 'glossary', 'title' => 'Glosario', 'icon' => 'bi-book'),
    array('id' => 'faq', 'title' => 'Preguntas frecuentes', 'icon' => 'bi-patch-question'),
);

ob_start();
?>
<div class="container-fluid text-start documentation-page">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="documentation-title">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0" id="documentation-title">
                    <i class="bi bi-journal-text text-primary me-2" aria-hidden="true"></i>Guía de SysTec
                </h1>
                <span class="badge text-bg-light border text-body-secondary">
                    Tu perfil: <?= e($roleLabel) ?>
                </span>
                <span class="badge text-bg-primary-subtle border border-primary-subtle text-primary-emphasis">
                    Guía v1.6 · 2026-11-26
                </span>
            </div>
            <p class="text-body-secondary mb-1">
                Manual funcional y técnico para usuarios autenticados. Explica qué hace SysTec, por qué funciona así,
                qué garantías ofrece y qué límites siguen abiertos.
            </p>
            <p class="small text-body-secondary mb-0">
                Contrastado con el código, las migraciones 001–011, el snapshot de datos del 2026-11-26 y la suite
                de pruebas. Los conteos son una fotografía y pueden variar con el uso.
            </p>
        </div>
        <?php if (!$printMode) : ?>
            <div class="d-flex flex-wrap gap-2 align-self-md-start flex-shrink-0">
                <?php if ($currentRoleTemplate !== '') : ?>
                    <button type="button" class="btn btn-outline-primary"
                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                            data-doc-template="<?= e($currentRoleTemplate) ?>"
                            data-doc-title="Caso de uso: <?= e($roleLabel) ?>">
                        <i class="bi bi-person-badge me-1" aria-hidden="true"></i>Guía de mi rol
                    </button>
                <?php endif; ?>
                <a href="<?= e(url('documentation.php?print=1')) ?>"
                   class="btn btn-outline-secondary"
                   target="_blank" rel="noopener">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Versión imprimible
                </a>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$printMode) : ?>
        <section class="card shadow-sm border-0 mb-3 d-print-none" aria-label="Buscar en la documentación">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md">
                        <label class="visually-hidden" for="documentationSearch">Buscar en la guía de SysTec</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body-tertiary text-body-secondary" aria-hidden="true">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="search" class="form-control" id="documentationSearch"
                                   placeholder="Buscar tema, rol o palabra clave (ej. CSV, TTL, desincorporación, IA)…"
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-auto">
                        <span class="small text-body-secondary" id="documentationSearchCount" role="status" aria-live="polite">
                            <?= e((string) count($sections)) ?> secciones
                        </span>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetDocumentationSearch">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="row g-3" id="documentationLayout">
        <aside class="col-12 col-lg-3 documentation-index-column">
            <nav class="card shadow-sm border-0 documentation-index" id="documentationIndex"
                 aria-label="Índice de la documentación">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h2 class="h6 mb-0 documentation-index-title">
                        <i class="bi bi-list-nested text-primary me-2" aria-hidden="true"></i>Contenido
                    </h2>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            id="documentationIndexToggle" aria-controls="documentationIndex"
                            aria-expanded="true" aria-label="Colapsar índice" title="Colapsar índice">
                        <i class="bi bi-chevron-bar-left" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($sections as $section) : ?>
                        <a class="list-group-item list-group-item-action d-flex align-items-center gap-2"
                           href="#<?= e($section['id']) ?>"
                           aria-label="<?= e($section['title']) ?>" title="<?= e($section['title']) ?>">
                            <i class="bi <?= e($section['icon']) ?> text-body-secondary" aria-hidden="true"></i>
                            <span><?= e($section['title']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </nav>
        </aside>

        <div class="col-12 col-lg-9 documentation-content-column">
            <div class="alert alert-info d-none d-print-none" id="documentationNoMatches" role="status">
                Ninguna sección coincide con la búsqueda. Prueba con otra palabra clave o limpia el filtro.
            </div>
            <div class="d-flex flex-column gap-3">
                <section class="card shadow-sm border-0 documentation-section" id="overview"
                         aria-labelledby="overview-title">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-between gap-2">
                            <div>
                                <h2 class="h5 mb-2" id="overview-title">Cómo funciona SysTec</h2>
                                <p class="mb-2">
                                    SysTec (Sistema de Gestión de Mantenimientos de Bienes) centraliza el inventario institucional,
                                    las solicitudes de soporte y el registro de los trabajos realizados sobre cada bien.
                                </p>
                                <p class="text-body-secondary mb-0">
                                    El flujo habitual conecta <strong>bien → ticket (si se reporta una solicitud) → mantenimiento (si se realiza un trabajo) → historial e informe</strong>.
                                    Los módulos disponibles y las acciones habilitadas dependen del rol asignado a la cuenta.
                                </p>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0"
                                    data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                    data-doc-template="detail-overview"
                                    data-doc-title="Cómo funciona SysTec">
                                Ampliar <i class="bi bi-arrow-up-right-square ms-1" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="foundations"
                         aria-labelledby="foundations-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="foundations-title">Fundamentos y garantías</h2>
                            <p class="mb-2">
                                SysTec es un monolito PHP renderizado en servidor sobre SQLite. El servidor decide permisos y validaciones;
                                el navegador solo presenta la información y envía formularios autenticados.
                            </p>
                            <p class="text-body-secondary mb-0">
                                Esta sección explica las decisiones que sostienen el resto del manual: fuente de verdad, separación entre
                                solicitud y trabajo, trazabilidad, automatización opcional y operación sin internet.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-foundations"
                                data-doc-title="Fundamentos y garantías">Ver fundamentos</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="lifecycle"
                         aria-labelledby="lifecycle-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="lifecycle-title">Ciclo de vida del dato</h2>
                            <p class="text-body-secondary mb-0">
                                El bien es la raíz. El ticket documenta una solicitud, el mantenimiento documenta el trabajo, el historial
                                conserva la secuencia y los informes consolidan un período. La baja lógica y la inmutabilidad de los
                                informes aprobados explican qué se conserva y qué se oculta.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-lifecycle"
                                data-doc-title="Ciclo de vida del dato">Ver ciclo</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="roles"
                         aria-labelledby="roles-title">
                    <div class="card-header">
                        <h2 class="h5 mb-0" id="roles-title">Roles y casos de uso</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-body-secondary">
                            Cada cuenta tiene un rol que limita las páginas y operaciones disponibles. La autorización se valida también en el servidor.
                        </p>
                        <div class="row g-3">
                            <article class="col-12 col-sm-6">
                                <div class="h-100 rounded border p-3<?= $userRoleCode === 'admin' ? ' border-primary border-2 bg-primary-subtle bg-opacity-10' : '' ?>">
                                    <h3 class="h6">
                                        <i class="bi bi-shield-fill-check text-danger me-1" aria-hidden="true"></i>Administrador
                                        <?php if ($userRoleCode === 'admin') : ?><span class="badge text-bg-primary ms-1">Tu rol actual</span><?php endif; ?>
                                    </h3>
                                    <p class="small mb-2">Administra cuentas, perfiles y roles en <code>users.php</code>; además, puede acceder a la gestión operativa, importaciones y sincronización.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-admin"
                                            data-doc-title="Caso de uso: Administrador">Caso de uso</button>
                                </div>
                            </article>
                            <article class="col-12 col-sm-6">
                                <div class="h-100 rounded border p-3<?= $userRoleCode === 'operador' ? ' border-primary border-2 bg-primary-subtle bg-opacity-10' : '' ?>">
                                    <h3 class="h6">
                                        <i class="bi bi-person-gear text-primary me-1" aria-hidden="true"></i>Operador
                                        <?php if ($userRoleCode === 'operador') : ?><span class="badge text-bg-primary ms-1">Tu rol actual</span><?php endif; ?>
                                    </h3>
                                    <p class="small mb-2">Opera el inventario, las importaciones y los registros de mantenimiento y tickets.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-operator"
                                            data-doc-title="Caso de uso: Operador">Caso de uso</button>
                                </div>
                            </article>
                            <article class="col-12 col-sm-6">
                                <div class="h-100 rounded border p-3<?= $userRoleCode === 'tecnico' ? ' border-primary border-2 bg-primary-subtle bg-opacity-10' : '' ?>">
                                    <h3 class="h6">
                                        <i class="bi bi-tools text-info me-1" aria-hidden="true"></i>Técnico
                                        <?php if ($userRoleCode === 'tecnico') : ?><span class="badge text-bg-primary ms-1">Tu rol actual</span><?php endif; ?>
                                    </h3>
                                    <p class="small mb-2">Atiende tickets y registra o actualiza mantenimientos. No tiene una cola de tickets exclusiva por asignación.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-technician"
                                            data-doc-title="Caso de uso: Técnico">Caso de uso</button>
                                </div>
                            </article>
                            <article class="col-12 col-sm-6">
                                <div class="h-100 rounded border p-3<?= $userRoleCode === 'consulta' ? ' border-primary border-2 bg-primary-subtle bg-opacity-10' : '' ?>">
                                    <h3 class="h6">
                                        <i class="bi bi-eye text-secondary me-1" aria-hidden="true"></i>Consulta
                                        <?php if ($userRoleCode === 'consulta') : ?><span class="badge text-bg-primary ms-1">Tu rol actual</span><?php endif; ?>
                                    </h3>
                                    <p class="small mb-2">Consulta inventario, mantenimientos y reportes. En tickets ve el listado y el drawer, pero no el detalle completo ni las acciones: es una inconsistencia conocida.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-consult"
                                            data-doc-title="Caso de uso: Consulta">Caso de uso</button>
                                </div>
                            </article>
                            <article class="col-12 col-sm-6">
                                <div class="h-100 rounded border p-3<?= $userRoleCode === 'personal' ? ' border-primary border-2 bg-primary-subtle bg-opacity-10' : '' ?>">
                                    <h3 class="h6">
                                        <i class="bi bi-person-badge text-warning me-1" aria-hidden="true"></i>Personal
                                        <?php if ($userRoleCode === 'personal') : ?><span class="badge text-bg-primary ms-1">Tu rol actual</span><?php endif; ?>
                                    </h3>
                                    <p class="small mb-2">Registra solicitudes y consulta sus propios tickets. Puede añadir seguimientos solo mientras el ticket no esté finalizado.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-personal"
                                            data-doc-title="Caso de uso: Personal">Caso de uso</button>
                                </div>
                            </article>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <caption class="visually-hidden">Resumen de permisos por rol</caption>
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Rol</th>
                                        <th scope="col">Tickets</th>
                                        <th scope="col">Mantenimientos</th>
                                        <th scope="col">Inventario / importación</th>
                                        <th scope="col">Usuarios</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th scope="row">Administrador</th>
                                        <td>Alta, seguimiento, estado y baja lógica</td>
                                        <td>Alta, edición, estado y acciones masivas</td>
                                        <td>Consulta, condición, importación y sincronización</td>
                                        <td>Administración completa</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Operador</th>
                                        <td>Alta, seguimiento, estado y baja lógica</td>
                                        <td>Alta, edición, estado y acciones masivas</td>
                                        <td>Consulta, condición, importación y sincronización</td>
                                        <td>Sin acceso</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Técnico</th>
                                        <td>Alta, seguimiento y estado; sin asignación exclusiva ni eliminación</td>
                                        <td>Registra, edita y cambia estado</td>
                                        <td>Consulta; no importa</td>
                                        <td>Sin acceso</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Consulta</th>
                                        <td>Listado y drawer; sin detalle completo ni acciones (inconsistencia conocida)</td>
                                        <td>Solo lectura</td>
                                        <td>Solo lectura; no importa</td>
                                        <td>Sin acceso</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Personal</th>
                                        <td>Solo tickets propios; seguimiento si no está finalizado</td>
                                        <td>Sin acceso</td>
                                        <td>Sin acceso operativo</td>
                                        <td>Sin acceso</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="states"
                         aria-labelledby="states-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="states-title">Estados y transiciones</h2>
                            <p class="mb-2">
                                Bien, mantenimiento, ticket e informe usan catálogos distintos. No comparten estados ni una secuencia única:
                                cada uno responde a una pregunta diferente del negocio.
                            </p>
                            <p class="text-body-secondary mb-0">
                                El servidor valida las transiciones de mantenimiento y los estados permitidos; los estados de ticket son
                                alternativas de atención y no una escalera obligatoria.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-states"
                                data-doc-title="Estados y transiciones">Ver reglas</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="assets"
                         aria-labelledby="assets-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="assets-title">Bienes e inventario</h2>
                            <p class="mb-0">Cada bien se identifica por sus datos y código institucional. La ficha puede incluir descripción, modelo, serial, color, condición y datos de adscripción o asignación disponibles.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-assets"
                                data-doc-title="Bienes e inventario">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="dashboard"
                         aria-labelledby="dashboard-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="dashboard-title">Dashboard</h2>
                            <p class="mb-0">Presenta el inventario en una tabla con indicadores, filtros por condición y, según el rol, datos de asignación, tickets y acciones disponibles por bien.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-dashboard"
                                data-doc-title="Dashboard">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="maintenances"
                         aria-labelledby="maintenances-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="maintenances-title">Mantenimientos</h2>
                            <p class="mb-0">La bitácora registra bien, tipo de trabajo, técnico, fecha, motivo, descripción, notas, estado y fecha del siguiente mantenimiento. El alta y la edición se hacen en drawer, con ticket opcional del mismo bien, filtros, acciones masivas, CSV e impresión.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-maintenances"
                                data-doc-title="Mantenimientos">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="renditions"
                         aria-labelledby="renditions-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="renditions-title">Informes de Rendición</h2>
                            <p class="mb-0">Consolidan mantenimientos y tickets de un período y alcance definidos. Se generan en un drawer asistido por IA o con respaldo local, se editan en Markdown y permanecen como borrador o informe aprobado.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-renditions"
                                data-doc-title="Informes de Rendición">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="tickets"
                         aria-labelledby="tickets-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="tickets-title">Tickets de soporte</h2>
                            <p class="mb-0">Un ticket documenta una solicitud o incidencia asociada a un bien. Se registra mediante un wizard en drawer y luego se consulta con filtros; no existe edición posterior del contenido original. Los roles operativos añaden seguimientos y gestionan estados.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-tickets"
                                data-doc-title="Tickets de soporte">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="personal"
                         aria-labelledby="personal-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="personal-title">Portal Personal</h2>
                            <p class="mb-0">El rol Personal utiliza un área propia para registrar solicitudes y revisar únicamente sus tickets. La lista se pagina de 25 en 25, admite filtros, exporta la página visible a CSV y señala los tickets no finalizados sin actividad durante 7 días.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-personal"
                                data-doc-title="Portal Personal">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="reports"
                         aria-labelledby="reports-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="reports-title">Historiales e informes</h2>
                            <p class="mb-0">El historial de un bien reúne mantenimientos y tickets asociados. Los informes imprimibles presentan la información de seguimiento del bien y apoyan la consulta o evaluación técnica.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-reports"
                                data-doc-title="Historiales e informes">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="imports"
                         aria-labelledby="imports-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="imports-title">Importación y sincronización</h2>
                            <p class="mb-0">Los roles autorizados importan bienes y mantenimientos o sincronizan asignaciones. La caché institucional es best-effort: puede quedar ausente o vencida sin impedir la importación, y su TTL no garantiza un refresco automático.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-imports"
                                data-doc-title="Importación y sincronización">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="administration"
                         aria-labelledby="administration-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="administration-title">Usuarios y cuenta</h2>
                            <p class="mb-0">La administración de cuentas se realiza en <code>users.php</code> y está reservada al administrador. Incluye filtros, CSV, drawer de alta/edición, perfiles, desbloqueo, activación y eliminación permanente con doble confirmación.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-administration"
                                data-doc-title="Usuarios y cuenta">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="migrations"
                         aria-labelledby="migrations-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="migrations-title">Migraciones y datos</h2>
                            <p class="mb-0">El runner CLI exige un backup verificado y registra cada migración en <code>schema_migrations</code>. Las migraciones 009–011 y el seeder de perfiles requieren revisar los usernames canónicos antes de ejecutar.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-migrations"
                                data-doc-title="Migraciones y datos">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="security"
                         aria-labelledby="security-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="security-title">Seguridad y sesiones</h2>
                            <p class="mb-0">La aplicación emite CSP y headers reales, valida sesión, rol y CSRF, y limita los intentos fallidos de login. Desactivar o eliminar una cuenta no revoca de inmediato una sesión ya abierta.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-security"
                                data-doc-title="Seguridad y sesiones">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="operations"
                         aria-labelledby="operations-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="operations-title">Operación y despliegue</h2>
                            <p class="mb-0">El runtime documentado es PHP 8.2 o superior. Existe un backup verificable y programable, pero la operación productiva aún tiene dependencias de TI y resta probar la recuperación.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-operations"
                                data-doc-title="Operación y despliegue">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="ai"
                         aria-labelledby="ai-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="ai-title">Asistencia con IA</h2>
                            <p class="mb-0">La IA propone mejoras de redacción para tickets, mantenimientos, notas de seguimiento e Informes de Rendición. Ciertos flujos usan un fallback local si el servicio no está disponible; la propuesta siempre se revisa antes de guardar.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-ai"
                                data-doc-title="Asistencia con IA">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="good-practices"
                         aria-labelledby="good-practices-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="good-practices-title">Buenas prácticas</h2>
                            <p class="mb-0">Registra información concreta, verifica el bien seleccionado, conserva la trazabilidad y confirma los datos antes de guardar o cerrar un ticket o mantenimiento.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-good-practices"
                                data-doc-title="Buenas prácticas">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="limits"
                         aria-labelledby="limits-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="limits-title">Alcance y límites</h2>
                            <p class="mb-2">
                                Esta guía describe el sistema implementado, no una versión ideal. Hay controles parciales y deudas
                                conocidas que cambian la forma de operar.
                            </p>
                            <p class="text-body-secondary mb-0">
                                Cada límite indica su efecto y la mitigación disponible. No debe interpretarse como una certificación
                                de seguridad ni como una promesa de comportamiento futuro.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-limits"
                                data-doc-title="Alcance y límites">Ver límites</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="glossary"
                         aria-labelledby="glossary-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="glossary-title">Glosario</h2>
                            <p class="mb-2">
                                El idioma del sistema distingue <strong>bien</strong>, <strong>ticket</strong>, <strong>mantenimiento</strong>,
                                <strong>historial</strong> e <strong>informe</strong>. Usar estos términos evita mezclar solicitudes con trabajos
                                y reportes con informes persistentes.
                            </p>
                            <p class="text-body-secondary mb-0">
                                El glosario completo incluye además adscripción, desincorporación, baja lógica, alcance, lote, caché,
                                migración, perfil, sesión y throttle.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-glossary"
                                data-doc-title="Glosario">Ver glosario</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="faq"
                         aria-labelledby="faq-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="faq-title">Preguntas frecuentes</h2>
                            <p class="text-body-secondary mb-0">
                                Respuestas rápidas sobre accesos, tickets, mantenimientos, informes, datos, IA y seguridad.
                                Cada respuesta remite a la regla o limitación que la origina.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-faq"
                                data-doc-title="Preguntas frecuentes">Ver preguntas</button>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <section class="card shadow-sm border-0 mt-4 documentation-sources" aria-labelledby="documentation-sources-title">
        <div class="card-body">
            <h2 class="h5 mb-2" id="documentation-sources-title">Vigencia, fuentes y mantenimiento de esta guía</h2>
            <div class="row g-3">
                <div class="col-12 col-lg-7">
                    <p class="mb-2">
                        Esta guía corresponde a <strong>SysTec v1.6</strong>, con fecha de corte <strong>2026-11-26</strong>.
                        Se contrastó con el código PHP, las migraciones <code>001</code>–<code>011</code>, el snapshot de
                        <code>storage/db.db</code> en modo de solo lectura, la documentación de <code>docs/</code> y la suite
                        de pruebas del proyecto.
                    </p>
                    <p class="text-body-secondary mb-0">
                        Los conteos de bienes, mantenimientos, tickets y usuarios son una fotografía de esa fecha. Una diferencia
                        posterior no invalida la guía por sí sola, pero sí exige revisar la sección afectada.
                    </p>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="rounded border bg-body-tertiary p-3 h-100">
                        <h3 class="h6 mb-2">Criterio de actualización</h3>
                        <ul class="small mb-0 ps-3">
                            <li>Un cambio de ruta, permiso o regla actualiza la sección correspondiente y la matriz de roles.</li>
                            <li>Un cambio de esquema actualiza Migraciones y datos, y el glosario si introduce un término nuevo.</li>
                            <li>Un límite resuelto se retira de Alcance y límites; uno nuevo se documenta con su mitigación.</li>
                            <li>Si el código cambia y esta guía no, se trata como incidente documental.</li>
                        </ul>
                    </div>
                </div>
            </div>
            <p class="small text-body-secondary mb-0 mt-3">
                Para una revisión técnica completa, consulta la documentación as-built del repositorio: arquitectura, modelo de datos,
                API, seguridad, backups, decisiones y backlog.
            </p>
        </div>
    </section>

    <?php if ($printMode) : ?>
        <div class="documentation-print-actions d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-primary" id="documentationPrintButton"
                    disabled aria-busy="true">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>
                <span>Preparando diagramas...</span>
            </button>
        </div>
    <?php else : ?>
        <section class="card shadow-sm border-0 mt-4" aria-labelledby="documentation-print-title">
            <div class="card-body d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-1" id="documentation-print-title">¿Necesitas una copia en papel?</h2>
                    <p class="text-body-secondary mb-0">Abre la guía completa en una vista HTML preparada para imprimir.</p>
                </div>
                <a class="btn btn-outline-primary flex-shrink-0"
                   href="<?= e(url('documentation.php?print=1')) ?>"
                   target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-file-earmark-richtext me-1" aria-hidden="true"></i>Ver versión imprimible
                </a>
            </div>
        </section>
    <?php endif; ?>
</div>

<div class="offcanvas offcanvas-end documentation-drawer text-start" tabindex="-1" id="documentationDrawer"
     aria-labelledby="documentationDrawerTitle">
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title h5 mb-0" id="documentationDrawerTitle">Detalle de la guía</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body" id="documentationDrawerBody"></div>
</div>

<template id="detail-overview">
    <p class="text-body-secondary">
        SysTec resuelve un problema de continuidad: que el inventario, las solicitudes y los trabajos técnicos no queden
        dispersos en hojas de cálculo o mensajes. El bien funciona como raíz común y cada registro conserva quién lo creó,
        cuándo y en qué estado quedó.
    </p>

    <h3 class="h6">Qué problema resuelve</h3>
    <ul>
        <li><strong>Inventario disperso:</strong> centraliza código, descripción, condición, adscripción y asignaciones del bien.</li>
        <li><strong>Solicitudes sin seguimiento:</strong> el ticket conserva la incidencia y su historial de atención.</li>
        <li><strong>Trabajos sin evidencia:</strong> el mantenimiento documenta tipo, técnico, fechas, motivo, notas y horas.</li>
        <li><strong>Reportes manuales:</strong> el historial por bien, el reporte de dañados y los Informes de Rendición se generan desde los datos registrados.</li>
        <li><strong>Accesos sin control:</strong> cada rol ve y ejecuta solo lo que el servidor le autoriza.</li>
    </ul>

    <h3 class="h6">Flujo general</h3>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Del bien al historial</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo general de atención desde la identificación del bien hasta el historial y el informe"><?= e($documentationDiagrams['overview']) ?></pre>
        <p class="visually-hidden">Identificar el bien, registrar un ticket si hay una solicitud, registrar un mantenimiento si se realiza un trabajo y consultar después el historial.</p>
    </figure>
    <ol>
        <li><strong>Ubica el bien</strong> desde el Dashboard o el selector de bien. El código y la descripción evitan registrar sobre el activo equivocado.</li>
        <li><strong>Registra un ticket</strong> si existe una solicitud o incidencia. El ticket deja constancia de lo reportado, no del trabajo ejecutado.</li>
        <li><strong>Registra el mantenimiento</strong> cuando se realiza la intervención. Puedes vincular un ticket activo del mismo bien; el servidor rechaza tickets de otro bien.</li>
        <li><strong>Consulta el historial</strong> para revisar la secuencia de tickets y mantenimientos. Desde allí se generan reportes e informes.</li>
    </ol>

    <h3 class="h6">Alcance funcional</h3>
    <div class="row g-2">
        <div class="col-12 col-md-6">
            <ul class="small mb-0">
                <li>Inventario, condición y datos de adscripción/asignación.</li>
                <li>Mantenimientos con transiciones y acciones masivas.</li>
                <li>Tickets globales y Portal Personal.</li>
                <li>Historial por bien y reporte de dañados.</li>
            </ul>
        </div>
        <div class="col-12 col-md-6">
            <ul class="small mb-0">
                <li>Informes de Rendición persistentes.</li>
                <li>Importación CSV/XLS/PostgreSQL y sincronización.</li>
                <li>Usuarios, perfiles, roles y desbloqueo.</li>
                <li>Migraciones, backups y asistencia opcional con IA.</li>
            </ul>
        </div>
    </div>

    <h3 class="h6">Cómo leer esta guía</h3>
    <p class="mb-0">
        Las tarjetas resumen cada tema; el botón <em>Ampliar</em> abre el detalle, las reglas y los límites. Los permisos
        pueden variar entre consulta, creación, actualización y administración. Cuando una sección menciona una inconsistencia,
        significa que el comportamiento real difiere de la intención documentada y que debes operar con esa cautela.
    </p>
</template>
<template id="detail-foundations">
    <p class="text-body-secondary">
        Los fundamentos explican por qué el sistema se comporta como se describe en el resto de la guía. No son una
        declaración de intenciones: corresponden a decisiones implementadas en el código actual.
    </p>

    <h3 class="h6">Qué es y qué no es SysTec</h3>
    <div class="row g-2 mb-3">
        <div class="col-12 col-md-6">
            <div class="border rounded p-3 h-100">
                <h4 class="h6 text-success-emphasis">Sí es</h4>
                <ul class="small mb-0 ps-3">
                    <li>Un sistema interno de inventario, mantenimientos y tickets por bien.</li>
                    <li>Una aplicación PHP renderizada en servidor con SQLite como persistencia principal.</li>
                    <li>Un registro operativo con historial por bien, reportes e informes de rendición.</li>
                    <li>Un sistema con roles, sesiones, CSRF, auditoría funcional y backups verificables.</li>
                </ul>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="border rounded p-3 h-100">
                <h4 class="h6 text-danger-emphasis">No es</h4>
                <ul class="small mb-0 ps-3">
                    <li>Una API pública ni una plataforma para integraciones externas.</li>
                    <li>Un ERP contable, de compras o de recursos humanos.</li>
                    <li>Un gestor documental de adjuntos, contratos o expedientes.</li>
                    <li>Un sistema de auditoría general inmutable de cada cambio.</li>
                    <li>Un sustituto de la política institucional de respaldo y custodia.</li>
                </ul>
            </div>
        </div>
    </div>

    <h3 class="h6">Arquitectura funcional</h3>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Cómo se conectan las piezas</figcaption>
        <pre class="mermaid" role="img" aria-label="Arquitectura funcional: persona, interfaz, páginas PHP, repositorios, SQLite, archivos y servicios opcionales"><?= e($documentationDiagrams['architecture']) ?></pre>
        <p class="visually-hidden">La persona usa la interfaz; las páginas PHP aplican reglas y repositorios; SQLite conserva los datos; los CSV, la caché y los logs viven en storage; NVIDIA, PostgreSQL legacy y los avatares son servicios opcionales; SQLite se respalda con el script verificable.</p>
    </figure>

    <h3 class="h6">Principios que sostienen el sistema</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Principios de diseño y su efecto visible</caption>
            <thead class="table-light">
                <tr><th scope="col">Principio</th><th scope="col">Qué significa</th><th scope="col">Dónde se nota</th></tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">El servidor decide</th>
                    <td>La interfaz puede ocultar acciones, pero el backend vuelve a validar sesión, rol, CSRF y datos.</td>
                    <td>Páginas y endpoints rechazan accesos no autorizados aunque se construya una petición manual.</td>
                </tr>
                <tr>
                    <th scope="row">SQLite es la fuente de verdad</th>
                    <td>Inventario, tickets, mantenimientos, perfiles e informes viven en un único archivo operativo.</td>
                    <td>Los reportes y el historial leen la misma base; los backups copian ese archivo de forma consistente.</td>
                </tr>
                <tr>
                    <th scope="row">El bien es la raíz</th>
                    <td>Tickets y mantenimientos se explican por el bien al que pertenecen.</td>
                    <td>El historial por bien reúne ambas fuentes y evita perder la secuencia de atención.</td>
                </tr>
                <tr>
                    <th scope="row">Solicitud no es trabajo</th>
                    <td>El ticket documenta lo reportado; el mantenimiento documenta lo ejecutado.</td>
                    <td>Tienen campos, estados y permisos distintos; el vínculo entre ambos es opcional y del mismo bien.</td>
                </tr>
                <tr>
                    <th scope="row">Trazabilidad antes que edición</th>
                    <td>El contenido original de un ticket no se reescribe; los cambios se registran como seguimientos.</td>
                    <td>El historial conserva actor y fecha; la baja de tickets es lógica y no borra la fila.</td>
                </tr>
                <tr>
                    <th scope="row">Automatización opcional</th>
                    <td>La IA y la fuente PostgreSQL legacy mejoran la operación, pero no son requisitos del núcleo.</td>
                    <td>El sistema funciona sin internet; la caché y los fallbacks evitan bloquear el registro manual.</td>
                </tr>
                <tr>
                    <th scope="row">Operación recuperable</th>
                    <td>Las migraciones exigen backup; los backups verifican integridad; los informes aprobados son inmutables.</td>
                    <td>El runner CLI, <code>bin/backup.sh</code> y el estado del informe protegen la reconstrucción del dato.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Garantías verificables</h3>
    <ul>
        <li><strong>Acceso:</strong> toda página funcional exige sesión; los roles se validan en servidor y no solo en la interfaz.</li>
        <li><strong>Integridad de formularios:</strong> las mutaciones usan POST y token CSRF; el SQL usa consultas parametrizadas y listas blancas.</li>
        <li><strong>Presentación:</strong> la salida HTML pasa por escape y la CSP limita scripts, estilos, marcos y conexiones.</li>
        <li><strong>Operaciones críticas:</strong> importaciones, perfiles, seguimientos y cambios de estado usan transacciones cuando el flujo lo exige; SQLite aplica triggers de catálogo y transición.</li>
        <li><strong>Continuidad:</strong> el backup verifica <code>PRAGMA integrity_check</code> y el runner de migraciones exige una copia verificada.</li>
        <li><strong>Verificación:</strong> la suite PHPUnit del proyecto registra 115 pruebas y 1095 aserciones en la fecha de corte.</li>
    </ul>

    <div class="alert alert-warning small mb-0" role="alert">
        <strong>Lectura correcta:</strong> estas garantías describen lo implementado, no una certificación externa. Los controles
        pendientes y las deudas conocidas se detallan en <em>Alcance y límites</em>; las decisiones de diseño, en la documentación
        de arquitectura y decisiones del repositorio.
    </div>
</template>
<template id="detail-lifecycle">
    <p class="text-body-secondary">
        El ciclo de vida responde dónde nace cada registro, qué lo relaciona y qué se conserva cuando algo se cierra o se oculta.
    </p>

    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Del bien al historial y al informe</figcaption>
        <pre class="mermaid" role="img" aria-label="Ciclo de vida: bien, solicitud, ticket, trabajo, mantenimiento, historial, reporte y respaldo"><?= e($documentationDiagrams['lifecycle']) ?></pre>
        <p class="visually-hidden">El bien origina una solicitud que se convierte en ticket y un trabajo que se convierte en mantenimiento; ambos alimentan el historial, que alimenta reportes e informes y se protege con respaldos.</p>
    </figure>

    <h3 class="h6">Etapas y responsabilidad</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Etapas del ciclo de vida y su persistencia</caption>
            <thead class="table-light">
                <tr><th scope="col">Etapa</th><th scope="col">Qué registra</th><th scope="col">Dónde vive</th><th scope="col">Regla clave</th></tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">Bien</th>
                    <td>Código, descripción, modelo, serial, color, condición, adscripción y asignaciones.</td>
                    <td><code>properties</code> y caché institucional.</td>
                    <td>No se elimina desde la aplicación; la condición se selecciona explícitamente.</td>
                </tr>
                <tr>
                    <th scope="row">Solicitud y ticket</th>
                    <td>Incidencia reportada, estado, creador y secuencia de seguimientos.</td>
                    <td><code>tickets</code> y <code>ticket_histories</code>.</td>
                    <td>El contenido original no se edita; se amplía con seguimientos.</td>
                </tr>
                <tr>
                    <th scope="row">Trabajo y mantenimiento</th>
                    <td>Tipo, técnico, fecha, motivo, descripción, notas, horas y próxima fecha.</td>
                    <td><code>maintenances</code>; vínculo opcional con un ticket del mismo bien.</td>
                    <td>El alta manual nace iniciada; al finalizar se calculan las horas totales.</td>
                </tr>
                <tr>
                    <th scope="row">Historial</th>
                    <td>Secuencia cronológica de tickets y mantenimientos del bien.</td>
                    <td>Consultas de historial y eventos de ticket.</td>
                    <td>Es una vista de trazabilidad, no una tabla que reemplace los registros originales.</td>
                </tr>
                <tr>
                    <th scope="row">Reporte o informe</th>
                    <td>Reporte de dañados, historial imprimible o Informe de Rendición por período y alcance.</td>
                    <td>Datos vivos para reportes legacy; <code>rendition_reports</code> para informes persistentes.</td>
                    <td>El informe aprobado es inmutable; el reporte legacy se regenera con los datos actuales.</td>
                </tr>
                <tr>
                    <th scope="row">Respaldo</th>
                    <td>Snapshot consistente de SQLite, CSV y manifiesto de verificación.</td>
                    <td><code>storage/backups</code>, con retención diaria, semanal y mensual.</td>
                    <td>La existencia del script no implica que el cron esté programado; la restauración debe probarse.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Qué se conserva y qué se oculta</h3>
    <ul>
        <li><strong>Ticket eliminado:</strong> se marca con <code>deleted_at</code>; desaparece de la bandeja activa, pero conserva su historial para no romper la trazabilidad.</li>
        <li><strong>Bien:</strong> no se elimina desde la interfaz; puede cambiar de condición y acumular mantenimientos y tickets.</li>
        <li><strong>Perfil:</strong> se elimina en cascada con la cuenta. Desactivar la cuenta conserva mejor la visualización de autores.</li>
        <li><strong>Informe aprobado:</strong> no admite modificación ni regeneración para el mismo período y alcance.</li>
        <li><strong>Reporte legacy:</strong> no se persiste como snapshot; se reconstruye con los datos vigentes.</li>
    </ul>

    <h3 class="h6">Por qué este orden</h3>
    <p>
        Separar solicitud, trabajo e informe evita mezclar responsabilidades: quien reporta no necesariamente ejecuta el trabajo,
        y el informe formal no debe depender de que alguien recuerde el historial. El bien actúa como raíz común para que la
        consulta por activo siga siendo posible aunque cambien los responsables o se elimine lógicamente un ticket.
    </p>
</template>
<template id="detail-role-admin">
    <p><strong>Escenario:</strong> administrar el acceso y supervisar la operación.</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo de trabajo del Administrador</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de trabajo del Administrador para gestionar usuarios, módulos operativos e importaciones"><?= e($documentationDiagrams['role-admin']) ?></pre>
        <p class="visually-hidden">El Administrador inicia sesión, elige entre administrar cuentas, gestionar módulos operativos o importar y sincronizar datos, y verifica el resultado.</p>
    </figure>
    <ul>
        <li>Administra cuentas en <code>users.php</code>: buscar, filtrar por rol/estado, revisar indicadores y exportar a CSV el conjunto filtrado.</li>
        <li>Crea o edita en un drawer el rol, el estado y la contraseña; el perfil admite <code>first_name</code>, <code>last_name</code> y <code>avatar_url</code> (URL HTTP/HTTPS opcional).</li>
        <li>Al editar, el <code>username</code> queda solo lectura. El <code>email</code> es informativo: se muestra y exporta, pero no se edita en el drawer.</li>
        <li>Desbloquea cuentas con throttle, activa/desactiva el acceso y elimina de forma permanente solo tras dos confirmaciones; no puede eliminarse ni desactivarse a sí mismo ni borrar el último administrador activo.</li>
        <li>El perfil tiene relación uno a uno con la cuenta y se elimina en cascada. La eliminación de la cuenta puede afectar la visualización de autores en tickets y seguimientos; desactivar es la alternativa para conservar la trazabilidad.</li>
        <li>Accede a inventario, mantenimientos, tickets, informes de rendición e importaciones. Puede eliminar tickets y también Informes de Rendición.</li>
        <li>Los cambios de rol o estado no invalidan automáticamente una sesión ya abierta; la revocación centralizada sigue pendiente.</li>
    </ul>

    <h3 class="h6">Fundamento del rol</h3>
    <p>
        El Administrador concentra la configuración del acceso y la supervisión operativa. No es un &laquo;superusuario
        invisible&raquo;: sus acciones también pasan por sesión, CSRF y validación de servidor. La protección para no
        desactivarse a sí mismo o dejar el sistema sin administradores evita un bloqueo total por error.
    </p>

    <h3 class="h6">Decisiones que debe tomar con cuidado</h3>
    <ul>
        <li><strong>Desactivar antes que eliminar:</strong> desactivar impide nuevos inicios de sesión y conserva la relación con autores; eliminar borra la cuenta y su perfil de forma permanente.</li>
        <li><strong>Rotar la clave inicial:</strong> las cuentas personales sembradas por la migración 009 comparten una clave inicial que debe cambiarse antes de exponer el sistema.</li>
        <li><strong>Revisar el último administrador:</strong> el sistema impide eliminar el último administrador activo, pero no impide que una configuración descuidada deje sin acceso operativo a otras áreas.</li>
        <li><strong>No compartir sesiones:</strong> una sesión ya abierta conserva su rol hasta que se cierra o expira.</li>
    </ul>
</template>
<template id="detail-role-operator">
    <p><strong>Escenario:</strong> mantener actualizados los datos institucionales y coordinar solicitudes.</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo de trabajo del Operador</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo del Operador para gestionar inventario, mantenimientos o tickets"><?= e($documentationDiagrams['role-operator']) ?></pre>
        <p class="visually-hidden">El Operador inicia sesión, selecciona una gestión de inventario, mantenimiento o tickets, realiza la operación y verifica el resultado.</p>
    </figure>
    <ul>
        <li>Consultar el inventario, importar o sincronizar información y actualizar la condición del bien según las acciones habilitadas.</li>
        <li>Registrar y actualizar mantenimientos y tickets, administrar sus estados y añadir seguimientos.</li>
        <li>Eliminar tickets mediante baja lógica; la administración de cuentas permanece reservada al Administrador.</li>
    </ul>

    <h3 class="h6">Qué puede hacer</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-3">
            <caption class="visually-hidden">Alcance operativo del rol Operador</caption>
            <thead class="table-light"><tr><th scope="col">Área</th><th scope="col">Acciones</th><th scope="col">Límite</th></tr></thead>
            <tbody>
                <tr><td>Inventario</td><td>Consultar, filtrar, exportar, cambiar condición e importar/sincronizar.</td><td>No elimina bienes ni administra usuarios.</td></tr>
                <tr><td>Mantenimientos</td><td>Crear, editar, cambiar estado, lote, CSV e impresión.</td><td>No elimina mantenimientos; no adjunta archivos.</td></tr>
                <tr><td>Tickets</td><td>Crear, seguir, cambiar estado y eliminar lógicamente.</td><td>No edita el contenido original del ticket.</td></tr>
                <tr><td>Informes</td><td>Generar y aprobar Informes de Rendición.</td><td>No elimina informes; eso corresponde al Administrador.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Fundamento del rol</h3>
    <p class="mb-0">
        El Operador sostiene la operación diaria: mantiene el inventario al día, coordina solicitudes y documenta el trabajo.
        Su alcance excluye la gestión de identidades para separar la operación de datos de la administración de accesos,
        que tiene un impacto de seguridad mayor.
    </p>
</template>
<template id="detail-role-technician">
    <p><strong>Escenario:</strong> atender incidencias y dejar constancia del trabajo técnico.</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo de atención del Técnico</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo del Técnico para atender un ticket y registrar un mantenimiento si se realiza un trabajo"><?= e($documentationDiagrams['role-technician']) ?></pre>
        <p class="visually-hidden">El Técnico revisa los tickets disponibles, registra seguimiento y estado; si realiza mantenimiento, lo asocia al bien y solo vincula un ticket del mismo bien cuando corresponda. No tiene una cola exclusiva por asignación.</p>
    </figure>
    <ul>
        <li>Consultar la bandeja de tickets y sus historiales; crear tickets, registrar seguimientos y actualizar estados.</li>
        <li>No existe una cola exclusiva: el alta deja <code>assigned_to</code> en <code>NULL</code> y el técnico puede ver y atender los tickets del sistema, no solo los asignados a él.</li>
        <li>Registrar, editar y cambiar el estado de mantenimientos, incluidos los datos narrativos y la siguiente fecha sugerida.</li>
        <li>Consultar bienes y reportes, y generar Informes de Rendición. No tiene las acciones de importación, administración de usuarios ni eliminación de tickets o informes.</li>
    </ul>

    <h3 class="h6">Flujo técnico recomendado</h3>
    <ol>
        <li>Revisa la bandeja y el historial del bien para entender la solicitud y evitar duplicar trabajos.</li>
        <li>Registra el seguimiento del ticket con hechos observables; cambia el estado cuando la atención realmente avance.</li>
        <li>Si realizaste un trabajo, crea el mantenimiento y vincula solo un ticket activo del mismo bien.</li>
        <li>Al finalizar, verifica la descripción, el técnico, las horas y la próxima fecha sugerida.</li>
    </ol>

    <h3 class="h6">Por qué no hay una cola por técnico</h3>
    <p class="mb-0">
        El sistema no implementa asignación exclusiva de tickets. El campo <em>usuario asignado</em> que aparece en los
        filtros se refiere al bien, no al ticket. Por eso el Técnico ve la bandeja global y debe coordinar verbalmente o
        por el seguimiento quién atiende cada solicitud. La asignación formal de tickets es una mejora pendiente, no una
        función disponible.
    </p>
</template>
<template id="detail-role-consult">
    <p><strong>Escenario:</strong> consultar tickets sin modificarlos.</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo de consulta de tickets</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de solo lectura para el rol Consulta"><?= e($documentationDiagrams['role-consult']) ?></pre>
        <p class="visually-hidden">El usuario Consulta abre el listado, busca o filtra tickets y revisa el drawer disponible. No puede abrir el detalle completo ni ejecutar acciones de gestión; el enlace de ID del listado es una inconsistencia conocida.</p>
    </figure>
    <ul>
        <li>Abre el listado de tickets, busca o filtra y utiliza el drawer para revisar la información disponible, incluidos mantenimientos asociados e historial.</li>
        <li>No puede crear tickets, cambiar estados, añadir seguimientos, eliminar registros ni abrir el detalle completo desde la bandeja.</li>
        <li><strong>Inconsistencia conocida:</strong> el listado aún muestra un enlace de ID hacia el detalle completo, pero esa ruta exige Administrador, Operador o Técnico y puede responder 403 para Consulta. El drawer sí está permitido; las acciones de gestión no.</li>
        <li>La disponibilidad de otras páginas y acciones se determina por los permisos propios de cada módulo; inventario, mantenimientos y reportes son de solo lectura.</li>
    </ul>

    <h3 class="h6">Fundamento del rol</h3>
    <p>
        Consulta existe para supervisión y auditoría funcional: necesita ver qué se reportó, cómo se atendió y qué evidencia
        queda, sin modificar el registro. Por eso el acceso es de solo lectura y no incluye acciones de gestión ni
        administración.
    </p>

    <h3 class="h6">Cómo operar con la inconsistencia actual</h3>
    <ul class="mb-0">
        <li>Usa el botón de resumen o drawer del listado para revisar estado, bien, mantenimientos e historial.</li>
        <li>Evita el enlace de ID si tu objetivo es solo consultar: puede devolver <code>403</code> para tu rol.</li>
        <li>Si necesitas el detalle completo o una acción, solicita apoyo a Administrador, Operador o Técnico.</li>
    </ul>
</template>
<template id="detail-role-personal">
    <p><strong>Escenario:</strong> reportar un problema y verificar su atención.</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo de solicitud del usuario Personal</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo del usuario Personal para crear un ticket y consultar su atención"><?= e($documentationDiagrams['personal']) ?></pre>
        <p class="visually-hidden">La persona selecciona un bien, describe la solicitud, opcionalmente revisa una propuesta de IA, envía el ticket y consulta sus propios seguimientos. El seguimiento es opcional y solo se permite si el ticket no está finalizado.</p>
    </figure>
    <ul>
        <li>Registra un ticket en el drawer mediante el wizard de bien, detalles y resumen; no puede editar después el título, la descripción, el bien o la observación original.</li>
        <li>En «Mis tickets» utiliza búsqueda, estado, categoría, adscripción, intervalo de fechas y ordenación. La paginación es de 25 tickets por página.</li>
        <li>El CSV exporta exclusivamente los tickets visibles en la página actual, no todos los resultados filtrados.</li>
        <li>Abre el detalle y añade seguimientos solo a tickets propios que no estén finalizados; un ticket finalizado bloquea esta acción.</li>
        <li>Los tickets no finalizados con más de 7 días sin cambios muestran el indicador «Sin actividad».</li>
        <li>La creación puede usar asistencia de IA; revisa la propuesta antes de enviar. El portal restringe los registros al usuario creador autenticado.</li>
    </ul>

    <h3 class="h6">Principio de aislamiento</h3>
    <p>
        El Portal Personal no es una bandeja global reducida: todas las consultas se limitan a los tickets cuyo
        <code>created_by</code> coincide con la cuenta autenticada. Esto protege la privacidad de otras personas y evita
        que un usuario modifique solicitudes ajenas.
    </p>

    <h3 class="h6">Qué puede y qué no puede hacer</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <caption class="visually-hidden">Alcance del rol Personal</caption>
            <thead class="table-light"><tr><th scope="col">Acción</th><th scope="col">Disponible</th><th scope="col">Condición</th></tr></thead>
            <tbody>
                <tr><td>Crear ticket</td><td>Sí</td><td>Bien existente, título y descripción.</td></tr>
                <tr><td>Ver sus tickets</td><td>Sí</td><td>Solo los creados por la cuenta.</td></tr>
                <tr><td>Añadir seguimiento</td><td>Sí</td><td>Ticket propio no finalizado.</td></tr>
                <tr><td>Cambiar estado</td><td>No</td><td>Corresponde a roles operativos.</td></tr>
                <tr><td>Eliminar ticket</td><td>No</td><td>Corresponde a Administrador u Operador.</td></tr>
                <tr><td>Ver inventario operativo</td><td>No</td><td>El portal solo expone el selector necesario para registrar.</td></tr>
            </tbody>
        </table>
    </div>
</template>
<template id="detail-states">
    <p class="text-body-secondary">
        Los estados no son intercambiables entre entidades. Un bien no está &laquo;en progreso&raquo; y un ticket no está
        &laquo;finalizado&raquo; en el mismo sentido que un mantenimiento. Esta sección reúne las reglas reales.
    </p>

    <h3 class="h6">Resumen por entidad</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Catálogo de estados por entidad</caption>
            <thead class="table-light">
                <tr><th scope="col">Entidad</th><th scope="col">Estados</th><th scope="col">Quién decide</th><th scope="col">Naturaleza</th></tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">Bien</th>
                    <td><code>BUENO</code>, <code>REGULAR</code>, <code>MALO</code>, <code>MUY MALO</code></td>
                    <td>Administrador y Operador.</td>
                    <td>Condición física seleccionada explícitamente; no se deriva de los mantenimientos.</td>
                </tr>
                <tr>
                    <th scope="row">Mantenimiento</th>
                    <td><code>iniciado</code>, <code>revision</code>, <code>finalizado</code></td>
                    <td>Administrador, Operador y Técnico.</td>
                    <td>Ciclo de ejecución con transiciones limitadas por servidor y triggers.</td>
                </tr>
                <tr>
                    <th scope="row">Ticket</th>
                    <td><code>iniciado</code>, <code>en_progreso</code>, <code>con_retraso</code>, <code>en_espera</code>, <code>finalizado</code></td>
                    <td>Administrador, Operador y Técnico.</td>
                    <td>Alternativas de atención; no una secuencia obligatoria. <code>finalizado</code> bloquea seguimientos personales.</td>
                </tr>
                <tr>
                    <th scope="row">Informe de Rendición</th>
                    <td><code>borrador</code>, <code>aprobado</code></td>
                    <td>Administrador, Operador y Técnico.</td>
                    <td>El aprobado es inmutable y único por período y alcance.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Bien</h3>
    <p>
        La condición describe el estado registrado del activo, no el resultado automático de un mantenimiento. El selector
        permite elegir cualquiera de los cuatro valores; el servidor valida el destino, exige rol y CSRF, y aplica el cambio
        de forma idempotente. La acción queda en el log, pero todavía no existe un motivo estructurado ni una tabla de auditoría
        general.
    </p>
    <ul>
        <li><code>MUY BUENO</code> no es un estado válido; el catálogo solo admite los cuatro valores indicados.</li>
        <li>Un bien <code>MALO</code> o <code>MUY MALO</code> aparece en el reporte de dañados solo si además tiene una nota que contiene <code>desincorporación</code>.</li>
        <li>La desincorporación es una constancia textual, no un estado formal del bien.</li>
    </ul>

    <h3 class="h6">Mantenimiento</h3>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Transiciones permitidas</figcaption>
        <pre class="mermaid" role="img" aria-label="Transiciones de estado de mantenimiento"><?= e($documentationDiagrams['maintenances']) ?></pre>
        <p class="visually-hidden">Iniciado puede pasar a revisión o finalizado; revisión puede finalizar; finalizado puede volver a revisión.</p>
    </figure>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Transiciones de mantenimiento aceptadas</caption>
            <thead class="table-light"><tr><th scope="col">Desde</th><th scope="col">Hacia</th><th scope="col">Condición</th></tr></thead>
            <tbody>
                <tr><td><code>iniciado</code></td><td><code>revision</code></td><td>Transición válida.</td></tr>
                <tr><td><code>iniciado</code></td><td><code>finalizado</code></td><td>Finaliza y calcula las horas totales.</td></tr>
                <tr><td><code>revision</code></td><td><code>finalizado</code></td><td>Finaliza y calcula las horas totales.</td></tr>
                <tr><td><code>finalizado</code></td><td><code>revision</code></td><td>Reapertura controlada; no vuelve a iniciado.</td></tr>
            </tbody>
        </table>
    </div>
    <p class="mb-0">
        Las acciones masivas omiten los registros que no admiten la transición y no son atómicas como lote. La importación
        histórica nace finalizada y no reconstruye <code>time_taken</code> si la fuente no lo trae.
    </p>

    <h3 class="h6">Ticket</h3>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Estados alternativos de atención</figcaption>
        <pre class="mermaid" role="img" aria-label="Estados alternativos de un ticket y registro de seguimientos"><?= e($documentationDiagrams['tickets']) ?></pre>
        <p class="visually-hidden">El ticket se registra y luego se elige uno de los estados alternativos; los cambios y seguimientos quedan en el historial.</p>
    </figure>
    <p>
        El servidor acepta los estados del catálogo, pero no impone la secuencia del diagrama. Un ticket puede pasar de
        <code>en_espera</code> a <code>en_progreso</code> o cerrarse directamente. El estado <code>finalizado</code> es
        terminal para el seguimiento del rol Personal: el portal deshabilita la acción y el endpoint la rechaza.
    </p>

    <h3 class="h6">Informe de Rendición</h3>
    <p class="mb-0">
        El informe nace como <code>borrador</code> y solo pasa a <code>aprobado</code> con confirmación explícita. Un aprobado
        no se modifica ni se regenera para el mismo período y alcance. La eliminación es permanente y está reservada al
        Administrador. El estado del informe no debe confundirse con el filtro de estado de mantenimiento usado al generar
        el alcance; esa reutilización de <code>status</code> es un límite conocido.
    </p>
</template>
<template id="detail-assets">
    <p class="text-body-secondary">
        El bien es la unidad central del sistema. Su código, descripción y condición permiten ubicarlo; sus relaciones
        permiten reconstruir qué solicitudes y trabajos ha tenido.
    </p>

    <h3 class="h6">Qué datos lo identifican</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Datos principales del bien</caption>
            <thead class="table-light"><tr><th scope="col">Dato</th><th scope="col">Uso</th><th scope="col">Regla</th></tr></thead>
            <tbody>
                <tr><td>Código compuesto</td><td>Grupo, subgrupo, sección e identificador con relleno de ceros.</td><td>Es la referencia visible; <code>ident</code> es la clave de importación y no tiene restricción única en base.</td></tr>
                <tr><td>Descripción, modelo, serial y color</td><td>Identificación física y búsqueda.</td><td>Se conservan tal como se importan; no se editan desde el listado.</td></tr>
                <tr><td>Condición</td><td>Estado físico del activo.</td><td><code>BUENO</code>, <code>REGULAR</code>, <code>MALO</code> o <code>MUY MALO</code>; se selecciona explícitamente.</td></tr>
                <tr><td>Adscripción</td><td>Unidad operativa institucional.</td><td>Se importa o se resuelve desde la caché legacy; puede quedar desactualizada.</td></tr>
                <tr><td>Responsable y usuario asignado</td><td>Datos de la fuente institucional.</td><td>Son nombres del bien, no cuentas de SysTec ni asignación de tickets.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Reglas de operación</h3>
    <ul>
        <li>Busca por código, descripción, condición y otros filtros disponibles en la vista.</li>
        <li>La condición del bien se distingue de los estados de ticket y mantenimiento: describe el estado registrado del activo.</li>
        <li>La información de responsable o adscripción depende de la sincronización con el catálogo institucional.</li>
        <li>El historial del bien ayuda a revisar las intervenciones y solicitudes vinculadas.</li>
        <li>No se elimina un bien desde la aplicación; la baja o desincorporación se documenta en notas y reportes.</li>
    </ul>

    <h3 class="h6">Por qué la condición es manual</h3>
    <p class="mb-0">
        Un mantenimiento finalizado no cambia automáticamente la condición del bien: la evaluación física es una decisión
        administrativa. El selector explícito evita ciclos ambiguos y deja el cambio en el log, aunque todavía no exista
        un motivo estructurado ni auditoría general.
    </p>
</template>
<template id="detail-dashboard">
    <p class="text-body-secondary">
        El Dashboard es la vista operativa del inventario. No es un tablero contable ni un reporte histórico: sirve para
        localizar bienes, revisar su condición y actuar sobre ellos sin perder el contexto de los filtros.
    </p>

    <h3 class="h6">Qué muestra</h3>
    <ul>
        <li>Indicadores de condición y del universo filtrado para orientar la revisión.</li>
        <li>Tabla de bienes con código, descripción, condición, adscripción y datos de asignación disponibles.</li>
        <li>Modo compacto o extendido; la preferencia se recuerda en el navegador.</li>
        <li>Enlaces al historial y a los mantenimientos o tickets del bien.</li>
    </ul>

    <h3 class="h6">Acciones por rol</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Acciones del Dashboard según rol</caption>
            <thead class="table-light"><tr><th scope="col">Acción</th><th scope="col">Admin</th><th scope="col">Operador</th><th scope="col">Técnico</th><th scope="col">Consulta</th></tr></thead>
            <tbody>
                <tr><td>Consultar y filtrar</td><td>Sí</td><td>Sí</td><td>Sí</td><td>Sí</td></tr>
                <tr><td>Exportar CSV</td><td>Sí</td><td>Sí</td><td>Sí</td><td>Sí</td></tr>
                <tr><td>Cambiar condición</td><td>Sí</td><td>Sí</td><td>No</td><td>No</td></tr>
                <tr><td>Crear ticket o mantenimiento</td><td>Sí</td><td>Sí</td><td>Sí</td><td>No</td></tr>
                <tr><td>Abrir historial</td><td>Sí</td><td>Sí</td><td>Sí</td><td>Sí</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Reglas y límites</h3>
    <ul>
        <li>Usa los indicadores de condición para filtrar rápidamente los bienes y exporta la vista filtrada a CSV en cualquier momento.</li>
        <li>El cambio de condición preserva los filtros activos para que puedas continuar la revisión.</li>
        <li>Las acciones por fila dependen del rol y de la página desde la que se abran.</li>
        <li>Los datos de adscripción y asignación proceden de una caché local con vigencia de 24 horas. Al vencerse se marca como desactualizada; el TTL no garantiza por sí solo un refresco automático.</li>
        <li>El Dashboard no pagina en servidor: la tabla se pagina y filtra en el cliente.</li>
    </ul>

    <h3 class="h6">Por qué es una vista y no un registro</h3>
    <p class="mb-0">
        El Dashboard no posee datos propios: lee el inventario y las relaciones existentes. Por eso un cambio de condición
        se refleja en el bien, no en una copia del tablero, y el historial sigue siendo la fuente para reconstruir la
        secuencia de atención.
    </p>
</template>
<template id="detail-maintenances">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Estados y transiciones permitidas</figcaption>
        <pre class="mermaid" role="img" aria-label="Transiciones de estados de un mantenimiento"><?= e($documentationDiagrams['maintenances']) ?></pre>
        <p class="visually-hidden">Un mantenimiento iniciado puede pasar a revisión o finalizar; uno en revisión puede finalizar; un mantenimiento finalizado puede volver a revisión.</p>
    </figure>
    <h3 class="h6">Qué registra un mantenimiento</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Campos principales del mantenimiento</caption>
            <thead class="table-light"><tr><th scope="col">Campo</th><th scope="col">Para qué sirve</th><th scope="col">Regla</th></tr></thead>
            <tbody>
                <tr><td>Bien</td><td>Activo intervenido.</td><td>Obligatorio; debe existir.</td></tr>
                <tr><td>Ticket</td><td>Solicitud que originó o acompaña el trabajo.</td><td>Opcional; debe ser un ticket activo del mismo bien.</td></tr>
                <tr><td>Tipo</td><td>Naturaleza del trabajo.</td><td><code>preventivo</code>, <code>correctivo</code>, <code>predictivo</code> o <code>evaluativo</code>.</td></tr>
                <tr><td>Técnico</td><td>Responsable de la intervención.</td><td>Catálogo híbrido de históricos y usuarios <code>tecnico</code> activos; se guarda como texto.</td></tr>
                <tr><td>Fecha, motivo, descripción y notas</td><td>Contexto y evidencia del trabajo.</td><td>Separar causa, trabajo y observaciones evita ambigüedad.</td></tr>
                <tr><td>Horas</td><td>Duración total del trabajo.</td><td>Se calcula al finalizar; la importación histórica puede dejar el valor nulo.</td></tr>
                <tr><td>Próximo mantenimiento</td><td>Referencia para el seguimiento.</td><td>Es una fecha sugerida, no un vencimiento automático.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Reglas de operación</h3>
    <ul>
        <li>El alta y la edición se realizan en un drawer. El formulario permite asociar el mantenimiento con un bien y, opcionalmente, con un ticket no eliminado del mismo bien; el servidor rechaza tickets de otro bien.</li>
        <li>Al editar, el selector de tickets se carga para el bien asociado al mantenimiento.</li>
        <li>Los estados disponibles incluyen iniciado, revisión y finalizado; el vencimiento puede señalarse cuando pasó la fecha siguiente sin finalizar el registro.</li>
        <li>La selección masiva permite enviar a revisión o finalizar varios registros; se omiten los que no permiten esa transición y el lote no es atómico.</li>
        <li>Los filtros ayudan a localizar por texto, categoría, adscripción, responsable, usuario asignado al bien, estado, tipo, técnico, fechas, vencidos o próximos 30 días. La exportación CSV utiliza los registros filtrados.</li>
        <li>«Imprimir historiales» abre una versión imprimible según los filtros aplicados; el historial por bien reúne tickets y mantenimientos relacionados.</li>
    </ul>

    <h3 class="h6">Por qué el ticket es opcional</h3>
    <p class="mb-0">
        No todo mantenimiento nace de una solicitud: puede ser preventivo, programado o detectado en una ronda. Exigir un
        ticket artificial distorsionaría la trazabilidad. Cuando existe un ticket, el vínculo es valioso porque conecta la
        solicitud con el trabajo, pero el sistema no obliga a inventarlo.
    </p>
</template>
<template id="detail-tickets">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Atención de una solicitud</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de actualización del estado y registro de seguimientos de un ticket"><?= e($documentationDiagrams['tickets']) ?></pre>
        <p class="visually-hidden">Registrar la solicitud en un drawer, elegir entre estados alternativos y añadir seguimientos cuando corresponda. El alta no asigna un técnico exclusivo y no permite editar después el contenido original.</p>
    </figure>
    <h3 class="h6">Qué documenta un ticket</h3>
    <p>
        Un ticket deja constancia de una solicitud o incidencia asociada a un bien: qué se reportó, quién lo reportó, en
        qué estado está y qué seguimientos recibió. No documenta el trabajo técnico; para eso existe el mantenimiento.
    </p>

    <h3 class="h6">Alta y edición</h3>
    <ul>
        <li>El registro usa un wizard en drawer de tres pasos: seleccionar bien, describir la incidencia y revisar el resumen.</li>
        <li>Una vez guardado no se edita el contenido original; las operaciones posteriores son seguimientos y cambios de estado.</li>
        <li>El alta deja <code>assigned_to</code> en <code>NULL</code>. No existe asignación exclusiva por técnico ni una cola filtrada por ese campo; el «Usuario asignado» de los filtros corresponde al bien, no al ticket.</li>
        <li>El ticket nace en <code>iniciado</code> y escribe un evento <code>created</code> en el historial.</li>
    </ul>

    <h3 class="h6">Estados y seguimientos</h3>
    <ul>
        <li>Los estados Iniciado, En progreso, Con retraso, En espera y Finalizado son alternativas de atención, no una secuencia obligatoria.</li>
        <li>El historial conserva cada seguimiento o cambio de estado con actor y fecha; los seguimientos y cambios operativos se registran de forma atómica.</li>
        <li>Un ticket <code>finalizado</code> bloquea el seguimiento del rol Personal, pero sigue visible para consulta y para los roles operativos.</li>
    </ul>

    <h3 class="h6">Bandeja y permisos</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Permisos sobre tickets</caption>
            <thead class="table-light"><tr><th scope="col">Acción</th><th scope="col">Admin</th><th scope="col">Operador</th><th scope="col">Técnico</th><th scope="col">Consulta</th><th scope="col">Personal</th></tr></thead>
            <tbody>
                <tr><td>Ver bandeja global</td><td>Sí</td><td>Sí</td><td>Sí</td><td>Sí</td><td>No</td></tr>
                <tr><td>Ver detalle completo</td><td>Sí</td><td>Sí</td><td>Sí</td><td>No (403)</td><td>Solo propios</td></tr>
                <tr><td>Crear ticket</td><td>Sí</td><td>Sí</td><td>Sí</td><td>No</td><td>Sí</td></tr>
                <tr><td>Seguimiento y estado</td><td>Sí</td><td>Sí</td><td>Sí</td><td>No</td><td>Solo propios no finalizados</td></tr>
                <tr><td>Eliminar (baja lógica)</td><td>Sí</td><td>Sí</td><td>No</td><td>No</td><td>No</td></tr>
            </tbody>
        </table>
    </div>
    <ul>
        <li>Los indicadores superiores y la barra de estado permiten filtrar en un clic (Todos, Abiertos y cada estado). También hay búsqueda y filtros por usuario asignado al bien, adscripción y estado; el CSV respeta los filtros.</li>
        <li>Administrador, Operador y Técnico pueden abrir el detalle completo, registrar seguimiento y actualizar el estado preservando sus filtros activos. Administrador y Operador pueden eliminar tickets mediante borrado lógico.</li>
        <li>Consulta puede ver el listado y el drawer, pero no el detalle completo ni las acciones. El enlace de ID que aún aparece en el listado es una inconsistencia conocida y puede llevar a un 403.</li>
        <li>Un ticket no sustituye al registro de mantenimiento: describe la solicitud, mientras que el mantenimiento documenta el trabajo realizado.</li>
    </ul>

    <h3 class="h6">Por qué no se edita el contenido original</h3>
    <p class="mb-0">
        Reescribir el título o la descripción destruiría la evidencia de lo que se reportó. El seguimiento permite corregir
        el contexto sin borrar la historia. La baja lógica conserva la fila y su historial para que el bien no pierda
        trazabilidad cuando un ticket se considera inválido o duplicado.
    </p>
</template>
<template id="detail-personal">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Crear y dar seguimiento a una solicitud</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo del usuario Personal al registrar y consultar sus tickets"><?= e($documentationDiagrams['personal']) ?></pre>
        <p class="visually-hidden">La persona selecciona un bien, redacta una solicitud, decide si quiere asistencia de IA, revisa el contenido, envía el ticket y consulta su estado y seguimientos. Los seguimientos son opcionales y solo se admiten en tickets no finalizados.</p>
    </figure>
    <h3 class="h6">Cómo registrar una solicitud</h3>
    <ol>
        <li>Selecciona el bien afectado; el código y la descripción evitan confundirlo con otro activo.</li>
        <li>Escribe un título claro y una descripción con hechos observables: qué ocurre, desde cuándo y qué se intentó.</li>
        <li>Si lo deseas, solicita asistencia de IA y revisa la propuesta antes de usarla.</li>
        <li>Confirma el resumen. El ticket queda asociado a tu cuenta y aparece en «Mis tickets».</li>
    </ol>

    <h3 class="h6">Qué puedes hacer en «Mis tickets»</h3>
    <ul>
        <li>Buscar y filtrar por estado, categoría, adscripción e intervalo de fechas; ordenar por ticket, bien, estado o actualización.</li>
        <li>Consultar el detalle del bien, el estado y la secuencia de seguimientos.</li>
        <li>Añadir seguimientos únicamente a tus tickets no finalizados. La acción se deshabilita para un ticket finalizado.</li>
        <li>Exportar a CSV solo los tickets visibles en la página actual, no todo el conjunto filtrado.</li>
    </ul>

    <h3 class="h6">Reglas de privacidad y atención</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <caption class="visually-hidden">Reglas del Portal Personal</caption>
            <thead class="table-light"><tr><th scope="col">Regla</th><th scope="col">Efecto</th></tr></thead>
            <tbody>
                <tr><td>Solo tickets propios</td><td>No puedes ver ni modificar solicitudes de otras personas.</td></tr>
                <tr><td>Paginación de 25</td><td>La bandeja se divide en páginas; el CSV no exporta páginas ocultas.</td></tr>
                <tr><td>Sin actividad</td><td>Un ticket no finalizado sin cambios durante 7 días o más se señala para seguimiento.</td></tr>
                <tr><td>Ticket finalizado</td><td>No admite nuevos seguimientos personales; consulta el estado o contacta al área de soporte.</td></tr>
                <tr><td>Contenido original</td><td>No se edita después del alta; los cambios se hacen con seguimientos.</td></tr>
            </tbody>
        </table>
    </div>
</template>
<template id="detail-reports">
    <p class="text-body-secondary">
        Los reportes responden preguntas concretas: ¿qué pasó con este bien?, ¿qué bienes están en condición crítica?
        y ¿qué ocurrió en un período? Cada salida tiene una fuente y una vigencia distintas.
    </p>

    <h3 class="h6">Tipos de salida</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Tipos de reportes y su fuente</caption>
            <thead class="table-light"><tr><th scope="col">Salida</th><th scope="col">Fuente</th><th scope="col">Persistencia</th></tr></thead>
            <tbody>
                <tr><td>Historial por bien</td><td>Mantenimientos y tickets del bien.</td><td>Se reconstruye al abrir la página.</td></tr>
                <tr><td>Impresión de historiales filtrados</td><td>Resultado de los filtros activos en Mantenimientos.</td><td>HTML imprimible; el PDF depende del navegador.</td></tr>
                <tr><td>Reporte de bienes dañados</td><td>Bienes con condición y notas de desincorporación.</td><td>Se regenera con datos vivos; admite CSV e impresión.</td></tr>
                <tr><td>Informe de Rendición</td><td>Período, alcance, mantenimientos y tickets.</td><td>Persiste en <code>rendition_reports</code> con Markdown y estado.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Regla del reporte de dañados</h3>
    <ul>
        <li>Se considera dañado un bien en condición <code>MALO</code> o <code>MUY MALO</code>.</li>
        <li>Además debe existir una nota que contenga <code>desincorporación</code>; la desincorporación no es un estado formal.</li>
        <li>La consulta evita duplicados cuando un bien tiene varias notas o mantenimientos relacionados.</li>
        <li>Puedes filtrar por condición o búsqueda para imprimir selectivamente y exportar a CSV.</li>
    </ul>

    <h3 class="h6">Cómo interpretar la información</h3>
    <ul>
        <li>Un ticket eliminado lógicamente no debe confundirse con un ticket activo; puede aparecer en el historial del bien.</li>
        <li>Las adscripciones y asignaciones mostradas pueden venir de la caché local; una marca de desactualización indica que no se garantizó el refresco.</li>
        <li>Los reportes legacy no son snapshots: si los datos cambian, el reporte cambia. Para un informe formal usa un Informe de Rendición aprobado.</li>
    </ul>

    <h3 class="h6">Fundamento</h3>
    <p class="mb-0">
        Mantener dos mecanismos es deliberado: los reportes rápidos sirven para la operación diaria y siempre reflejan el
        dato actual, mientras que el informe de rendición conserva una versión congelada y aprobable para presentación.
        Confundirlos llevaría a citar cifras que ya no existen o a guardar copias innecesarias de cada consulta.
    </p>
</template>
<template id="detail-renditions">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Del período y alcance al informe aprobado</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de un Informe de Rendición con período, alcance, IA o fallback, borrador, aprobación, inmutabilidad, impresión y descarga Markdown"><?= e($documentationDiagrams['renditions']) ?></pre>
        <p class="visually-hidden">Se define el período y alcance, se reúnen mantenimientos y tickets, se genera la narrativa con IA o fallback local, se edita el Markdown, se guarda un borrador y, si se confirma, se aprueba un informe inmutable que puede imprimirse o descargarse.</p>
    </figure>
    <h3 class="h6">Qué problema resuelve</h3>
    <p>
        Un reporte generado desde datos vivos no es reproducible: si cambia un mantenimiento, el reporte cambia. El Informe
        de Rendición conserva un período, un alcance, los identificadores de origen, las estadísticas y el Markdown aprobado
        para que la versión presentada pueda auditarse después.
    </p>

    <h3 class="h6">Cómo se define el alcance</h3>
    <ul>
        <li>Se abre desde Mantenimientos mediante un wizard en drawer. Define fechas, unidad emisora, destinatario, responsable de preparar, título, notas y si usa los filtros activos.</li>
        <li>El alcance se calcula con el período, los filtros y un <code>scope_hash</code>; deben existir mantenimientos en ese rango. Los tickets asociados se incluyen si la opción está activa.</li>
        <li>La combinación de período y alcance es única: no se puede duplicar un informe aprobado para el mismo conjunto.</li>
        <li>Los datos de origen se guardan como identificadores y estadísticas, no como una copia completa de cada registro.</li>
    </ul>

    <h3 class="h6">Generación y revisión</h3>
    <ul>
        <li>La IA genera las secciones narrativas y el sistema conserva los datos reales en tablas Markdown. Si falta la configuración o falla el servicio, este flujo usa un fallback local y lo señala.</li>
        <li>Puedes editar el Markdown, previsualizarlo, guardar un <strong>borrador</strong> o confirmar la revisión para guardarlo como <strong>aprobado</strong>.</li>
        <li>El Markdown tiene un límite de 150 000 caracteres; un contenido excesivo se rechaza antes de guardar.</li>
        <li>Un informe aprobado es definitivo: no admite modificaciones ni regeneración para el mismo período y alcance. Solo un administrador puede eliminar el registro completo.</li>
        <li>La vista imprimible permite imprimir o guardar en PDF y descargar el archivo <code>.md</code>. El historial de informes permite consultar aprobados o retomar borradores.</li>
    </ul>

    <h3 class="h6">Permisos</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Permisos sobre Informes de Rendición</caption>
            <thead class="table-light"><tr><th scope="col">Acción</th><th scope="col">Admin</th><th scope="col">Operador</th><th scope="col">Técnico</th><th scope="col">Consulta</th></tr></thead>
            <tbody>
                <tr><td>Generar y guardar</td><td>Sí</td><td>Sí</td><td>Sí</td><td>No desde la interfaz</td></tr>
                <tr><td>Aprobar</td><td>Sí</td><td>Sí</td><td>Sí</td><td>No</td></tr>
                <tr><td>Consultar impresión por URL</td><td>Sí</td><td>Sí</td><td>Sí</td><td>Sí</td></tr>
                <tr><td>Eliminar</td><td>Sí</td><td>No</td><td>No</td><td>No</td></tr>
            </tbody>
        </table>
    </div>

    <div class="alert alert-warning small mb-0" role="alert">
        <strong>Límite conocido:</strong> al guardar, el campo <code>status</code> se usa tanto para el estado del informe
        (<code>borrador</code>/<code>aprobado</code>) como para el filtro de mantenimiento. Si generaste el alcance con un
        filtro por estado, revisa el informe guardado antes de aprobarlo: el alcance puede diferir del generado. Es una
        deuda técnica documentada, no una regla de negocio deseada.
    </div>
</template>
<template id="detail-imports">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Procesamiento de importaciones y sincronización</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de importación de datos o sincronización de asignaciones institucionales"><?= e($documentationDiagrams['imports']) ?></pre>
        <p class="visually-hidden">Administrador u Operador elige un proceso, se validan sus datos y se importan en el sistema. La actualización de la caché local es opcional y best-effort; si no está disponible se usan datos locales o se muestra un aviso.</p>
    </figure>
    <h3 class="h6">Fuentes y formatos</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Fuentes de importación y sincronización</caption>
            <thead class="table-light"><tr><th scope="col">Proceso</th><th scope="col">Formato</th><th scope="col">Qué actualiza</th></tr></thead>
            <tbody>
                <tr><td>Bienes desde archivo</td><td>CSV o XLS.</td><td>Crea bienes nuevos; en los existentes normalmente solo actualiza la condición.</td></tr>
                <tr><td>Bienes desde PostgreSQL legacy</td><td>Lectura de la fuente institucional.</td><td>Conserva la condición local y sincroniza adscripciones y asignaciones cuando la fuente responde.</td></tr>
                <tr><td>Mantenimientos</td><td>CSV local o archivo en servidor.</td><td>Crea registros históricos finalizados; no reconstruye horas ausentes.</td></tr>
                <tr><td>Sincronización de asignaciones</td><td>Fuente legacy.</td><td>Actualiza responsables y usuarios asignados del bien y refresca la caché si es posible.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Límites y garantías</h3>
    <ul>
        <li>Las opciones de importación y sincronización están disponibles para Administrador y Operador.</li>
        <li>La importación exige confirmación, se ejecuta en transacción y produce un informe de filas aceptadas y omitidas.</li>
        <li>La importación de mantenimientos permite subir un archivo CSV desde el equipo (hasta 5 MB) o procesar el archivo en servidor; exige confirmación y backup previo.</li>
        <li>El proceso está acotado: hasta 5000 filas por operación y 5 MB por archivo para proteger la disponibilidad del sistema.</li>
        <li>Las filas inválidas se omiten y se informan; no se detiene toda la operación por un registro mal formado.</li>
    </ul>

    <h3 class="h6">Caché institucional</h3>
    <ul>
        <li>Después de una importación PostgreSQL, la actualización de la caché local es <strong>best-effort</strong>: si falla por permisos o almacenamiento, el inventario puede quedar importado y la interfaz debe mostrar el aviso correspondiente.</li>
        <li>La caché leída por las vistas tiene un TTL de 24 horas por defecto. Al vencerse se marca como desactualizada; ese TTL no garantiza un refresco automático. Algunas consultas pueden intentar un refresco en segundo plano, pero no es una garantía.</li>
        <li>Si no hay caché institucional vigente, se usan los últimos datos locales o adscripciones guardadas y se muestra su estado; no debe interpretarse como una sincronización exitosa.</li>
    </ul>

    <h3 class="h6">Por qué la importación es controlada</h3>
    <p class="mb-0">
        La fuente legacy no está normalizada y una importación directa en la página podía duplicar bienes o sobrescribir
        condiciones durante una simple navegación. El flujo actual exige POST, confirmación y backup, y separa la importación
        de la sincronización para que un fallo de la fuente externa no invalide los datos ya guardados.
    </p>
</template>
<template id="detail-administration">
    <h3 class="h6">Cuenta y perfil son cosas distintas</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Diferencia entre cuenta y perfil</caption>
            <thead class="table-light"><tr><th scope="col">Objeto</th><th scope="col">Contiene</th><th scope="col">Regla</th></tr></thead>
            <tbody>
                <tr><td>Cuenta (<code>users</code>)</td><td><code>username</code>, email informativo, hash de contraseña, rol y estado.</td><td>Define autenticación y autorización; el <code>username</code> no se cambia en edición.</td></tr>
                <tr><td>Perfil (<code>profile</code>)</td><td><code>first_name</code>, <code>last_name</code> y <code>avatar_url</code>.</td><td>Uno por cuenta, con eliminación en cascada; no contiene permisos.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Gestión disponible</h3>
    <ul>
        <li><code>users.php</code> es exclusivo del Administrador. Permite buscar, filtrar por rol o estado, usar los indicadores de total/activos/técnicos/inactivos y exportar a CSV los usuarios filtrados.</li>
        <li>El alta y la edición se hacen en un drawer. El perfil incluye <code>first_name</code>, <code>last_name</code> y <code>avatar_url</code>; ambos nombres se completan juntos y el avatar debe ser una URL HTTP/HTTPS.</li>
        <li>En edición, <code>username</code> es solo lectura. El <code>email</code> se muestra en el listado y el CSV, pero es informativo y no editable en el drawer.</li>
        <li>Las cuentas bloqueadas temporalmente por exceso de intentos fallidos se muestran en el listado y pueden desbloquearse manualmente. Restablecer la contraseña también limpia el throttle del usuario.</li>
        <li>La activación/desactivación controla nuevos inicios de sesión. El Administrador no puede desactivarse, eliminarse ni retirarse su propio rol, y se impide borrar el último administrador activo.</li>
        <li>La eliminación es permanente y exige dos confirmaciones: aceptar los riesgos y escribir el username exacto. El perfil se elimina en cascada; los autores de tickets y seguimientos pueden pasar a mostrarse como «Usuario». Desactivar conserva mejor la trazabilidad.</li>
        <li>Cada usuario puede cambiar su contraseña desde la barra superior. Cierra sesión al terminar, especialmente en equipos compartidos.</li>
    </ul>

    <h3 class="h6">Reglas de seguridad y privacidad</h3>
    <ul>
        <li>La cuenta y el perfil se guardan en una transacción: si falla una parte, no queda una cuenta sin perfil coherente.</li>
        <li>El avatar es una URL remota, no una carga de archivo. El navegador la solicita a un tercero; no hay proxy ni lista blanca de hosts.</li>
        <li>El email es informativo: no se usa para iniciar sesión ni para recuperar contraseñas.</li>
        <li>La política de contraseña no es homogénea: la UI y el CLI piden 6 caracteres, pero el backend del alta web acepta 4. La edición web y el cambio de contraseña propia exigen 6. Esta diferencia es una limitación conocida, no una política unificada.</li>
    </ul>

    <h3 class="h6">Fundamento</h3>
    <p class="mb-0">
        Separar credenciales de presentación permite cambiar el nombre visible de una persona sin alterar su acceso, y
        evita que el rol viva en dos tablas. La cascada mantiene la integridad del perfil; por eso desactivar suele ser
        preferible a eliminar cuando se quiere conservar la autoría de tickets y seguimientos.
    </p>
</template>
<template id="detail-migrations">
    <h3 class="h6">Por qué existen migraciones versionadas</h3>
    <p>
        El esquema base creado al abrir una base vacía no incluye las evoluciones posteriores. Aplicar DDL de forma
        implícita en cada visita podía alterar una base en producción sin control. El flujo normal es por CLI: se verifica
        una copia, se aplican archivos en orden y se registra cada uno en <code>schema_migrations</code>.
    </p>

    <h3 class="h6">Qué hace el runner</h3>
    <ol>
        <li>Prepara un backup legible y verificado de la base actual.</li>
        <li>Ejecuta <code>php bin/migrate.php --backup=RUTA_A_BACKUP_VERIFICADO</code>.</li>
        <li>Comprueba <code>PRAGMA integrity_check</code> y que los conteos de <code>properties</code>, <code>maintenances</code> y <code>users</code> coincidan con la base actual.</li>
        <li>Aplica en orden natural las migraciones pendientes, cada una en su transacción, y registra el nombre del archivo en <code>schema_migrations</code>.</li>
    </ol>
    <p>No existen migraciones <code>down</code>: para revertir un cambio confirmado se restaura el backup verificado.</p>

    <h3 class="h6">Estado actual</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Grupos de migraciones aplicadas</caption>
            <thead class="table-light"><tr><th scope="col">Rango</th><th scope="col">Contenido principal</th></tr></thead>
            <tbody>
                <tr><td>001–002</td><td>Normalización de datos legacy, triggers de catálogo/transición e índices mínimos.</td></tr>
                <tr><td>003–005</td><td>Tickets, historial y baja lógica.</td></tr>
                <tr><td>006–008</td><td>Asignaciones de bienes, vínculo mantenimiento–ticket e Informes de Rendición.</td></tr>
                <tr><td>009–011</td><td>Cuentas personales, tabla <code>profile</code> y corrección de usernames canónicos.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Migraciones 009–011 y seeder</h3>
    <ul>
        <li><code>009_seed_personal_users.sql</code>: añade email y crea cuentas Personal iniciales con usernames canónicos; las cuentas existentes no se sobrescriben.</li>
        <li><code>010_create_profile.sql</code>: crea el perfil uno a uno (<code>first_name</code>, <code>last_name</code>, <code>avatar_url</code>) con eliminación en cascada y carga los perfiles canónicos disponibles.</li>
        <li><code>011_rename_personal_usernames.sql</code>: renombra usernames Personal heredados a los canónicos y alinea el email, sin cambiar IDs, perfiles ni credenciales.</li>
        <li><code>php bin/seed-profiles.php</code> es idempotente, no crea usuarios, actualiza nombre y apellidos por username y conserva <code>avatar_url</code>. Exige que existan todos los usernames canónicos; si falta uno, revierte toda la carga.</li>
    </ul>

    <h3 class="h6">Compatibilidad de restauración</h3>
    <ul>
        <li>Un backup anterior a 009 no contiene las tablas ni columnas actuales: debe restaurarse en un entorno aislado y migrarse antes de servir tráfico.</li>
        <li>Una base recién creada por el esquema base todavía necesita el runner para obtener triggers, índices y las columnas 004–011.</li>
        <li>El módulo de Informes de Rendición conserva una salvaguarda runtime que crea su tabla e índices si faltan; es una excepción documentada y no sustituye la migración 008.</li>
    </ul>

    <div class="alert alert-warning small mb-0" role="alert">
        <strong>Advertencia de datos actuales:</strong> la base de esta guía contiene <code>abrizuela</code>, mientras que el seeder exige <code>abrizzuela</code>. No se afirma que esa diferencia esté conciliada. Debe revisarse y resolverse con backup antes de ejecutar el seeder o dar por completadas las migraciones.
    </div>
</template>
<template id="detail-security">
    <h3 class="h6">Controles activos</h3>
    <ul>
        <li>Autenticación con sesión PHP, contraseñas con <code>password_hash</code>/<code>password_verify</code>, regeneración de ID al entrar y cookie <code>HttpOnly</code>, <code>SameSite=Lax</code> y <code>Secure</code> cuando se detecta HTTPS.</li>
        <li>Autorización por rol en el servidor y token CSRF por sesión en las mutaciones POST.</li>
        <li>Rate limiting de login: 5 intentos fallidos por usuario y 15 por IP dentro de 15 minutos. Al alcanzar el umbral responde HTTP 429; los contadores se guardan en <code>storage/cache/throttles</code> y un Administrador puede desbloquear una cuenta.</li>
        <li>CSP emitida por <code>include/security.php</code>: <code>default-src 'self'</code>, scripts propios con nonce por petición, <code>object-src 'none'</code>, <code>frame-ancestors 'none'</code>, <code>base-uri 'self'</code>, <code>form-action 'self'</code> y <code>connect-src 'self'</code>.</li>
        <li>La CSP permite imágenes propias, <code>data:</code> y HTTP/HTTPS. Solo esta página de documentación añade jsDelivr para Mermaid; sus estilos inline usan la excepción real <code>style-src 'unsafe-inline'</code>. Si el CDN no carga, el texto y la impresión siguen disponibles.</li>
        <li>También se envían <code>X-Content-Type-Options: nosniff</code>, <code>X-Frame-Options: DENY</code>, <code>Referrer-Policy: no-referrer</code>, <code>Permissions-Policy</code> y <code>Cache-Control: no-store, private</code>.</li>
    </ul>
    <h3 class="h6">Modelo de confianza</h3>
    <p>
        SysTec se diseñó para uso en red institucional de confianza por personas autenticadas. No es un servicio público:
        todas las páginas funcionales exigen sesión y cada rol limita páginas y acciones. El almacenamiento sensible vive
        en <code>storage/</code>, denegado por el router embebido y por reglas del servidor web.
    </p>

    <h3 class="h6">Controles por capa</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Controles de seguridad implementados por capa</caption>
            <thead class="table-light"><tr><th scope="col">Capa</th><th scope="col">Control</th><th scope="col">Efecto</th></tr></thead>
            <tbody>
                <tr><td>Acceso</td><td>Sesión PHP, regeneración de ID y roles en servidor.</td><td>Sin sesión no hay páginas funcionales.</td></tr>
                <tr><td>Mutación</td><td>POST + token CSRF por sesión.</td><td>Una petición externa no puede cambiar datos sin el token.</td></tr>
                <tr><td>Datos</td><td>SQL parametrizado y listas blancas de identificadores.</td><td>Los valores del formulario no se concatenan en la consulta.</td></tr>
                <tr><td>Presentación</td><td>Escape <code>e()</code>, serialización <code>js()</code> y CSP con nonce.</td><td>Se reduce la ejecución de contenido inyectado.</td></tr>
                <tr><td>Abuso de login</td><td>Throttle de 5 intentos por usuario y 15 por IP en 15 minutos.</td><td>Respuesta 429 y desbloqueo administrativo.</td></tr>
                <tr><td>Continuidad</td><td>Backup con verificación de integridad.</td><td>Existe una copia comprobada para recuperación.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Sesiones y roles</h3>
    <p>
        El rol se copia en la sesión al iniciar sesión y las páginas no vuelven a comprobar en cada petición si la cuenta
        sigue activa o conserva ese rol. Desactivar, eliminar o cambiar el rol impide nuevos inicios, pero
        <strong>no revoca inmediatamente una sesión ya abierta</strong>; puede continuar hasta que se cierre o expire.
        Por eso, ante un cambio de permisos crítico, cierra la sesión afectada o pide al usuario que vuelva a entrar.
    </p>

    <h3 class="h6">Privacidad y terceros</h3>
    <ul>
        <li>Los textos enviados a NVIDIA Build se procesan fuera de la institución; evita incluir datos personales innecesarios.</li>
        <li>Los avatares son URLs externas: el navegador contacta al host cuando muestra la imagen.</li>
        <li>Los logs se rotan y no deben contener contraseñas, claves ni datos personales innecesarios.</li>
        <li>El email no se usa para recuperación de contraseña; es un dato informativo del directorio.</li>
    </ul>

    <h3 class="h6">Límites conocidos</h3>
    <ul>
        <li>El throttle es local al host; no constituye un límite centralizado para varias instancias.</li>
        <li>La auditoría general persistente <code>audit_events</code> y la revocación centralizada de sesiones siguen pendientes.</li>
        <li>HTTPS/HSTS debe configurarse en el servidor real.</li>
        <li>La longitud mínima de contraseña no es uniforme entre UI, CLI y backend; consulta la sección Usuarios y cuenta.</li>
        <li>Importación, sincronización e IA no tienen el mismo límite de intentos que el login.</li>
        <li><code>router.php</code> y <code>.htaccess</code> no son equivalentes: hay que replicar las denegaciones en Apache o Nginx.</li>
    </ul>
</template>
<template id="detail-operations">
    <h3 class="h6">Runtime y almacenamiento</h3>
    <ul>
        <li>PHP 8.2 o superior, con extensiones <code>sqlite3</code>, <code>mbstring</code> y <code>session</code>. Las funciones opcionales de IA, PostgreSQL y cifrado requieren además <code>cURL</code>, <code>PDO/pgsql</code> y OpenSSL cuando se utilicen.</li>
        <li>La base operativa es SQLite en <code>storage/db.db</code>. Los backups, CSV, caché y logs permanecen en <code>storage/</code> y no deben publicarse ni commitearse con datos reales.</li>
        <li>El núcleo funciona sin internet. NVIDIA Build, la fuente PostgreSQL y los avatares remotos dependen de conectividad y configuración externa.</li>
    </ul>
    <h3 class="h6">Backups y recuperación</h3>
    <ul>
        <li><code>bin/backup.sh [--require-encryption]</code> crea un snapshot consistente con la API de SQLite, verifica <code>PRAGMA integrity_check</code>, incluye los CSV fuente y un manifiesto, y aplica retención de 30 diarias, 12 semanales y 12 mensuales.</li>
        <li>El cifrado puede exigirse con una clave externa; nunca incluyas la clave en el repositorio, el backup o los logs.</li>
        <li>Las migraciones se ejecutan con <code>bin/migrate.php --backup=RUTA</code>; no hay reversión <code>down</code>, por lo que un rollback confirmado utiliza el backup.</li>
        <li>Una restauración debe probarse en un entorno aislado, verificando integridad, conteos, CSV asociados y acceso funcional.</li>
    </ul>
    <h3 class="h6">Requisitos y configuración</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Requisitos de ejecución de SysTec</caption>
            <thead class="table-light"><tr><th scope="col">Componente</th><th scope="col">Requisito</th><th scope="col">Cuándo es necesario</th></tr></thead>
            <tbody>
                <tr><td>PHP</td><td>8.2 o superior, extensiones <code>sqlite3</code>, <code>mbstring</code> y <code>session</code>.</td><td>Siempre; Composer y CI fijan la plataforma 8.2.</td></tr>
                <tr><td>SQLite</td><td>Archivo <code>storage/db.db</code> con permisos de escritura.</td><td>Siempre; es la persistencia principal.</td></tr>
                <tr><td>cURL</td><td>Cliente HTTP para NVIDIA Build.</td><td>Solo si se usa asistencia narrativa.</td></tr>
                <tr><td>PDO/pgsql</td><td>Acceso a la fuente legacy.</td><td>Solo para importación PostgreSQL o sincronización.</td></tr>
                <tr><td>OpenSSL y <code>tar</code></td><td>Cifrado y empaquetado del backup.</td><td>Si se exige cifrado; el cifrado es opcional por defecto.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Despliegue básico</h3>
    <ol>
        <li>Instalar dependencias con Composer y verificar que PHP use la plataforma 8.2.</li>
        <li>Asegurar permisos de lectura y escritura en <code>storage/</code>, <code>storage/cache</code> y <code>storage/logs</code>, tanto para CLI como para el servidor web.</li>
        <li>Crear la primera cuenta con <code>bin/create-user.php</code> y aplicar migraciones con <code>bin/migrate.php --backup=RUTA</code>.</li>
        <li>Configurar el servidor web para denegar <code>storage/</code>, <code>docs/</code>, <code>grafo/</code>, <code>bin/</code>, <code>include/</code>, <code>migrations/</code> y archivos sensibles.</li>
        <li>Definir HTTPS/HSTS en el servidor real; el sistema no los activa por sí solo.</li>
    </ol>

    <h3 class="h6">Pendientes operativos</h3>
    <ul>
        <li>El cron productivo, el destino separado del host, la custodia de claves, las alertas de fallo y la primera restauración operativa siguen pendientes de TI; la existencia del script no significa que el respaldo esté programado.</li>
        <li>HTTPS/HSTS en producción, auditoría general persistente y revocación centralizada de sesiones.</li>
        <li>Pruebas E2E y medición de cobertura; las verificaciones actuales son unitarias/integración local.</li>
        <li>Conciliación de usernames canónicos en la base actual antes del seeder de perfiles; ver Migraciones y datos.</li>
    </ul>

    <h3 class="h6">Verificación recomendada</h3>
    <p class="mb-0">
        Antes de dar por buen un cambio: ejecutar el lint de PHP, la suite PHPUnit, comprobar que las mutaciones siguen
        usando POST + CSRF y confirmar que el backup y la restauración funcionan en un entorno aislado. La guía se
        actualizó con 115 pruebas y 1095 aserciones en verde en la fecha de corte.
    </p>
</template>
<template id="detail-ai">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Propuesta de texto asistida por IA</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de generación, revisión y decisión de guardar una propuesta de IA"><?= e($documentationDiagrams['ai']) ?></pre>
        <p class="visually-hidden">La persona completa el texto y solicita asistencia externa. Si la respuesta es válida, revisa la propuesta; si falla, solo ciertos flujos usan un fallback local y los demás muestran un aviso para editar manualmente. El sistema no guarda automáticamente.</p>
    </figure>
    <h3 class="h6">Dónde se usa y qué hace</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Flujos de asistencia con IA</caption>
            <thead class="table-light"><tr><th scope="col">Flujo</th><th scope="col">Qué propone</th><th scope="col">Fallback local</th></tr></thead>
            <tbody>
                <tr><td>Relato del Portal Personal</td><td>Título, descripción y observación de un ticket.</td><td>Sí</td></tr>
                <tr><td>Nota de seguimiento</td><td>Redacción de la nota para tickets.</td><td>Sí</td></tr>
                <tr><td>Informe de Rendición</td><td>Secciones narrativas del Markdown.</td><td>Sí</td></tr>
                <tr><td>Campos de ticket</td><td>Mejora de redacción de campos.</td><td>No; error controlado.</td></tr>
                <tr><td>Campos de mantenimiento</td><td>Descripción, motivo y notas por separado.</td><td>No; error controlado.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Cómo funciona</h3>
    <ol>
        <li>La persona escribe el texto original en el formulario.</li>
        <li>Si solicita asistencia, el sistema envía el contenido al servicio configurado y recibe una propuesta.</li>
        <li>La propuesta se muestra para revisión; no sustituye el criterio del usuario.</li>
        <li>El registro se guarda solo cuando la persona confirma el formulario.</li>
    </ol>

    <h3 class="h6">Reglas y límites</h3>
    <ul>
        <li>La IA propone texto; no guarda automáticamente el ticket, seguimiento, mantenimiento ni Informe de Rendición.</li>
        <li>La IA es opcional: el núcleo del sistema funciona sin clave, sin conexión y sin servicio externo.</li>
        <li>En tickets puede mejorar campos o procesar un relato; en mantenimiento puede considerar descripción, motivo de falla y notas, manteniéndolos separados; en seguimientos puede mejorar una nota.</li>
        <li>Los Informes de Rendición conservan los datos reales del período en tablas Markdown; la narrativa es solo una parte del documento.</li>
        <li>El fallback local existe solo en los flujos indicados; si falla un flujo sin fallback, el sistema muestra un aviso y permite continuar manualmente.</li>
        <li>Revisa nombres, códigos, hechos, causas y resultado antes de confirmar. Una propuesta debe resumir la solicitud, no inventar datos.</li>
    </ul>

    <h3 class="h6">Privacidad</h3>
    <p class="mb-0">
        Los textos enviados al servicio de IA se procesan externamente para generar la propuesta. Evita incluir datos
        personales, números de identificación o información sensible que no sea necesaria para la redacción. La
        responsabilidad sobre el contenido final es de quien guarda el registro.
    </p>
</template>
<template id="detail-limits">
    <h3 class="h6">Cómo leer esta sección</h3>
    <p>
        Cada límite se documenta con su efecto real y una mitigación. Esto permite operar con criterio mientras la deuda
        se resuelve. No es una certificación de seguridad ni una promesa de que el comportamiento cambiará en una fecha.
    </p>

    <h3 class="h6">Límites vigentes</h3>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Límites conocidos del sistema</caption>
            <thead class="table-light">
                <tr><th scope="col">Límite</th><th scope="col">Efecto para el usuario</th><th scope="col">Mitigación o decisión pendiente</th></tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">Permisos de Consulta en tickets</th>
                    <td>Listado y drawer disponibles; detalle completo y acciones responden 403.</td>
                    <td>Usar el resumen del listado; unificar la política de acceso es una decisión pendiente.</td>
                </tr>
                <tr>
                    <th scope="row">Sesiones que conservan el rol</th>
                    <td>Desactivar, eliminar o cambiar el rol no cierra una sesión ya abierta.</td>
                    <td>Cerrar sesión manualmente al rotar permisos; revocación centralizada pendiente.</td>
                </tr>
                <tr>
                    <th scope="row">Política de contraseña no uniforme</th>
                    <td>La UI y el CLI piden 6 caracteres; el backend de alta web acepta 4.</td>
                    <td>Unificar la validación en servidor antes de reforzar la política.</td>
                </tr>
                <tr>
                    <th scope="row">Sin auditoría general</th>
                    <td>No hay una tabla <code>audit_events</code> con cada cambio.</td>
                    <td>Quedan logs, historial de tickets e informes; auditoría persistente pendiente.</td>
                </tr>
                <tr>
                    <th scope="row">Conflicto de <code>status</code> en informes</th>
                    <td>El alcance guardado puede diferir del generado si se usó filtro por estado.</td>
                    <td>Revisar el informe antes de aprobarlo; separar ambos usos del campo.</td>
                </tr>
                <tr>
                    <th scope="row">DDL runtime en informes</th>
                    <td>Una petición web puede crear tabla/índices si faltan.</td>
                    <td>Aplicar migraciones por CLI; retirar la salvaguarda runtime.</td>
                </tr>
                <tr>
                    <th scope="row">Backup no programado</th>
                    <td>El script existe, pero no hay cron productivo ni destino fuera del host.</td>
                    <td>Ejecutar backup manual con clave; programar y probar restauración con TI.</td>
                </tr>
                <tr>
                    <th scope="row">Avatares sin lista blanca</th>
                    <td>El navegador contacta cualquier host HTTP/HTTPS indicado en la URL.</td>
                    <td>Usar URLs institucionales de confianza; política de hosts o proxy pendiente.</td>
                </tr>
                <tr>
                    <th scope="row">Reglas de servidor dispares</th>
                    <td><code>router.php</code> y <code>.htaccess</code> no deniegan exactamente lo mismo.</td>
                    <td>Replicar denegaciones en Apache/Nginx o mover código fuera del webroot.</td>
                </tr>
                <tr>
                    <th scope="row">Paginación en cliente</th>
                    <td>Las tablas cargan datos y paginan en el navegador.</td>
                    <td>Aceptable para el volumen actual; paginación en servidor pendiente.</td>
                </tr>
                <tr>
                    <th scope="row">Cobertura E2E</th>
                    <td>La suite es unitaria/integración local; no hay pruebas HTTP de extremo a extremo.</td>
                    <td>Verificación manual de flujos críticos; E2E y cobertura pendientes.</td>
                </tr>
                <tr>
                    <th scope="row">Usuario canónico ausente</th>
                    <td>La base contiene <code>abrizuela</code> y el seeder exige <code>abrizzuela</code>.</td>
                    <td>No ejecutar el seeder hasta reconciliar la identidad con backup.</td>
                </tr>
                <tr>
                    <th scope="row">Procesamiento externo de texto</th>
                    <td>Los textos enviados a IA salen de la institución.</td>
                    <td>Evitar datos sensibles; la IA es opcional y siempre se revisa antes de guardar.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Qué no es un defecto</h3>
    <ul>
        <li>La condición del bien sea manual: es una decisión administrativa, no un error de cálculo.</li>
        <li>Que un reporte legacy cambie con los datos: está diseñado para reflejar el estado vigente.</li>
        <li>Que la IA no guarde por sí sola: la revisión humana es parte del diseño.</li>
        <li>Que el ticket no se edite: el seguimiento es el mecanismo de corrección previsto.</li>
        <li>Que el cifrado del backup sea opcional: es una decisión operativa que debe exigirse explícitamente.</li>
    </ul>

    <div class="alert alert-warning small mb-0" role="alert">
        <strong>Uso responsable:</strong> si una discrepancia afecta un dato o un acceso, documenta el caso concreto y
        verifica la sección correspondiente antes de atribuirlo a una falla de la guía.
    </div>
</template>
<template id="detail-glossary">
    <p class="text-body-secondary">
        Estos términos se usan con el mismo sentido en la interfaz, los reportes y la documentación del sistema.
        La distinción entre solicitud, trabajo e informe es la más importante.
    </p>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <caption class="visually-hidden">Glosario del dominio de SysTec</caption>
            <thead class="table-light"><tr><th scope="col">Término</th><th scope="col">Sentido en SysTec</th></tr></thead>
            <tbody>
                <tr><th scope="row">Bien</th><td>Objeto físico inventariable; raíz de mantenimientos y tickets.</td></tr>
                <tr><th scope="row">Código de bien</th><td>Grupo, subgrupo, sección e identificador con relleno de ceros.</td></tr>
                <tr><th scope="row">Ident</th><td>Parte del código usada como clave de importación; no es única en base.</td></tr>
                <tr><th scope="row">Adscripción</th><td>Unidad operativa institucional del bien, importada o resuelta desde la caché legacy.</td></tr>
                <tr><th scope="row">Responsable / usuario asignado</th><td>Nombres sincronizados del bien; no son cuentas de SysTec ni asignación de tickets.</td></tr>
                <tr><th scope="row">Condición del bien</th><td><code>BUENO</code>, <code>REGULAR</code>, <code>MALO</code> o <code>MUY MALO</code>; se selecciona explícitamente.</td></tr>
                <tr><th scope="row">Desincorporación</th><td>Constancia detectada en notas; no es un estado formal del bien.</td></tr>
                <tr><th scope="row">Mantenimiento</th><td>Registro del trabajo técnico realizado sobre un bien.</td></tr>
                <tr><th scope="row">Tipo</th><td><code>preventivo</code>, <code>correctivo</code>, <code>predictivo</code> o <code>evaluativo</code>.</td></tr>
                <tr><th scope="row">Técnico</th><td>Persona que ejecutó el trabajo; se guarda como texto de un catálogo híbrido.</td></tr>
                <tr><th scope="row">Vencido</th><td>Mantenimiento no finalizado cuya próxima fecha ya pasó.</td></tr>
                <tr><th scope="row">Próximo mantenimiento</th><td>Fecha sugerida de seguimiento, no un disparador automático.</td></tr>
                <tr><th scope="row">Ticket</th><td>Solicitud o incidencia reportada sobre un bien.</td></tr>
                <tr><th scope="row">Seguimiento</th><td>Nota añadida al historial para documentar avances sin editar el contenido original.</td></tr>
                <tr><th scope="row">Baja lógica</th><td>Marca <code>deleted_at</code> que oculta el ticket sin borrar su historial.</td></tr>
                <tr><th scope="row">Historial</th><td>Secuencia de tickets y mantenimientos de un bien.</td></tr>
                <tr><th scope="row">Reporte</th><td>Salida generada desde datos vivos: historial imprimible o bienes dañados.</td></tr>
                <tr><th scope="row">Informe de Rendición</th><td>Documento persistente por período y alcance, con Markdown y estado borrador/aprobado.</td></tr>
                <tr><th scope="row">Alcance / <code>scope_hash</code></th><td>Conjunto de filtros y fechas que identifica de forma única un informe.</td></tr>
                <tr><th scope="row">Lote</th><td>Acción masiva sobre mantenimientos; omite transiciones no permitidas.</td></tr>
                <tr><th scope="row">Importación</th><td>Carga validada y transaccional desde CSV/XLS o PostgreSQL legacy.</td></tr>
                <tr><th scope="row">Sincronización</th><td>Actualización de responsables y asignaciones desde la fuente institucional.</td></tr>
                <tr><th scope="row">Caché</th><td>Copia local de adscripciones/asignaciones con TTL de 24 horas y refresco no garantizado.</td></tr>
                <tr><th scope="row">Migración</th><td>Evolución SQL versionada, registrada en <code>schema_migrations</code>.</td></tr>
                <tr><th scope="row">Backup</th><td>Snapshot consistente de SQLite con CSV y manifiesto de verificación.</td></tr>
                <tr><th scope="row">Cuenta</th><td>Registro de autenticación en <code>users</code>: usuario, rol, estado y credencial.</td></tr>
                <tr><th scope="row">Perfil</th><td>Datos de presentación en <code>profile</code>: nombre, apellidos y avatar.</td></tr>
                <tr><th scope="row">Rol</th><td><code>admin</code>, <code>operador</code>, <code>tecnico</code>, <code>consulta</code> o <code>personal</code>.</td></tr>
                <tr><th scope="row">Sesión</th><td>Estado autenticado que conserva el rol definido al iniciar sesión.</td></tr>
                <tr><th scope="row">Throttle</th><td>Contador local de intentos fallidos de login por usuario y por IP.</td></tr>
            </tbody>
        </table>
    </div>
</template>
<template id="detail-faq">
    <h3 class="h6">Acceso y cuenta</h3>
    <h4 class="h6">¿Por qué no veo un módulo?</h4>
    <p>
        Cada módulo exige un rol. La interfaz oculta acciones no permitidas y el servidor las rechaza aunque se intente
        una petición manual. Consulta la sección <em>Roles y casos de uso</em>.
    </p>
    <h4 class="h6">¿Por qué sigo teniendo acceso si me cambiaron el rol?</h4>
    <p>
        La sesión conserva el rol con el que se inició. El cambio impide un nuevo ingreso, pero no revoca la sesión abierta;
        cierra sesión y vuelve a entrar. La revocación centralizada está pendiente.
    </p>
    <h4 class="h6">¿Cómo cambio mi contraseña?</h4>
    <p>
        Desde la opción de la barra superior. Necesitas la contraseña actual y una nueva de al menos 6 caracteres.
        Si una cuenta está bloqueada por intentos fallidos, un Administrador puede desbloquearla.
    </p>

    <h3 class="h6">Tickets</h3>
    <h4 class="h6">¿Puedo editar un ticket después de crearlo?</h4>
    <p>
        No. El contenido original se conserva; los cambios se documentan con seguimientos y el estado se actualiza por separado.
    </p>
    <h4 class="h6">¿Quién puede eliminar un ticket?</h4>
    <p>
        Administrador y Operador, mediante baja lógica. La fila y su historial se conservan; desaparece de la bandeja activa.
    </p>
    <h4 class="h6">¿Por qué Consulta no abre el detalle?</h4>
    <p>
        El listado y el drawer están permitidos, pero la ruta de detalle exige roles operativos. Es una inconsistencia
        conocida y documentada; usa el resumen del listado para consultar.
    </p>
    <h4 class="h6">¿Personal puede ver tickets de otras personas?</h4>
    <p>
        No. El Portal Personal filtra por el creador autenticado. No existe una bandeja global para ese rol.
    </p>

    <h3 class="h6">Mantenimientos</h3>
    <h4 class="h6">¿Puedo finalizar sin pasar por revisión?</h4>
    <p>
        Sí. Las transiciones permitidas incluyen <code>iniciado → finalizado</code>. La revisión es una etapa opcional, no obligatoria.
    </p>
    <h4 class="h6">¿Cómo se calculan las horas?</h4>
    <p>
        Al finalizar se calculan horas totales redondeadas. Las importaciones históricas pueden dejar el valor nulo si la fuente no lo trae.
    </p>
    <h4 class="h6">¿Puedo vincular un ticket de otro bien?</h4>
    <p>
        No. El servidor exige que el ticket esté activo y pertenezca al mismo bien del mantenimiento.
    </p>

    <h3 class="h6">Informes y reportes</h3>
    <h4 class="h6">¿Puedo modificar un informe aprobado?</h4>
    <p>
        No. El estado aprobado es inmutable. Solo un Administrador puede eliminar el registro completo.
    </p>
    <h4 class="h6">¿Por qué no aparece mi informe al generarlo?</h4>
    <p>
        Debe existir al menos un mantenimiento en el período y la combinación de período y alcance es única. Revisa los filtros y el rango.
    </p>
    <h4 class="h6">¿El reporte de dañados es un informe formal?</h4>
    <p>
        No. Es una salida desde datos vivos. Para un documento formal usa un Informe de Rendición y apruébalo.
    </p>

    <h3 class="h6">Datos y operación</h3>
    <h4 class="h6">¿De dónde salen las adscripciones y asignaciones?</h4>
    <p>
        Se importan desde la fuente institucional o se leen de una caché local. Si la caché está vencida, la vista lo indica;
        el TTL de 24 horas no garantiza un refresco automático.
    </p>
    <h4 class="h6">¿Se puede borrar un bien?</h4>
    <p>
        No desde la aplicación. El bien se conserva porque es la raíz de la trazabilidad.
    </p>
    <h4 class="h6">¿Hay respaldo automático?</h4>
    <p>
        Existe un script verificable, pero la programación por cron, el destino separado y la restauración operativa
        siguen pendientes de TI. Un <code>STATUS=OK</code> confirma integridad del snapshot, no cifrado ni recuperación probada.
    </p>

    <h3 class="h6">Asistencia con IA</h3>
    <h4 class="h6">¿La IA guarda el registro?</h4>
    <p>
        No. Solo propone texto; la persona debe revisarlo y confirmar el formulario.
    </p>
    <h4 class="h6">¿Qué pasa si el servicio no responde?</h4>
    <p>
        Algunos flujos usan un fallback local y lo señalan; otros muestran un aviso para continuar manualmente.
    </p>
    <h4 class="h6">¿Qué datos se envían?</h4>
    <p>
        El texto que se solicita mejorar se procesa externamente. Evita incluir información personal o sensible innecesaria.
    </p>
</template>
<template id="detail-good-practices">
    <p class="text-body-secondary">
        Estas recomendaciones reducen errores de registro y aprovechan la trazabilidad que el sistema ya ofrece.
        No cambian permisos ni validaciones: solo ayudan a que los datos sean útiles después.
    </p>

    <h3 class="h6">Antes de registrar</h3>
    <ul>
        <li>Comprueba el código y la descripción del bien; el selector de bienes puede incluir activos con nombres parecidos.</li>
        <li>Si vas a importar, prepara el archivo con encabezados válidos y revisa la fila de ejemplo antes de confirmar.</li>
        <li>Si usas IA, ten a mano los datos reales para contrastar la propuesta.</li>
    </ul>

    <h3 class="h6">Al abrir o atender un ticket</h3>
    <ul>
        <li>Escribe qué ocurre, desde cuándo y qué se intentó; evita adjetivos sin evidencia.</li>
        <li>Separa la causa reportada del diagnóstico y del trabajo realizado.</li>
        <li>Actualiza el estado cuando la atención cambie realmente, no solo por costumbre.</li>
        <li>Registra seguimientos breves y fechados; el contenido original no se edita.</li>
        <li>Si eres Personal y el ticket está finalizado, contacta al área de soporte en lugar de intentar reabrirlo.</li>
    </ul>

    <h3 class="h6">Al registrar un mantenimiento</h3>
    <ul>
        <li>Usa el tipo correcto: preventivo, correctivo, predictivo o evaluativo.</li>
        <li>Indica técnico, fecha y horas reales; las horas se calculan al finalizar.</li>
        <li>Vincula un ticket solo si es del mismo bien y está activo.</li>
        <li>Usa la fecha próxima como referencia de seguimiento, no como promesa automática.</li>
        <li>Finaliza solo cuando el trabajo esté terminado; una reapertura posterior debe pasar por revisión.</li>
    </ul>

    <h3 class="h6">Al consultar o reportar</h3>
    <ul>
        <li>Revisa el historial del bien antes de duplicar un ticket o una intervención.</li>
        <li>Diferencia el reporte de dañados, que depende de notas de desincorporación, de un informe aprobado.</li>
        <li>Si exportas CSV, recuerda que la exportación respeta los filtros y, en el Portal Personal, solo la página visible.</li>
        <li>Si la adscripción aparece como desactualizada, no la presentes como dato sincronizado.</li>
    </ul>

    <h3 class="h6">Seguridad y cuenta</h3>
    <ul>
        <li>Cierra sesión al terminar, especialmente en equipos compartidos.</li>
        <li>No compartas contraseñas ni dejes la clave inicial de las cuentas sembradas sin rotar.</li>
        <li>Revisa la política de avatares: la imagen se carga desde un servidor externo.</li>
        <li>Si detectas una conducta distinta a lo documentado, regístrala como discrepancia: puede ser un límite conocido o un defecto.</li>
    </ul>

    <h3 class="h6">Responsabilidad</h3>
    <p class="mb-0">
        La IA puede ayudar a redactar, pero no verifica hechos ni decide estados. La persona que guarda el registro responde
        por su exactitud, y el Administrador responde por la configuración de cuentas, roles y respaldos.
    </p>
</template>

<script nonce="<?= e(systec_csp_nonce()) ?>" defer
        src="https://cdn.jsdelivr.net/npm/mermaid@10.9.3/dist/mermaid.min.js"
        integrity="sha384-R63zfMfSwJF4xCR11wXii+QUsbiBIdiDzDbtxia72oGWfkT7WHJfmD/I/eeHPJyT"
        crossorigin="anonymous"></script>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const documentationPrintMode = <?= js($printMode) ?>;
    const documentationLayout = document.getElementById('documentationLayout');
    const documentationIndexToggle = document.getElementById('documentationIndexToggle');
    const indexStorageKey = 'systec.documentation.indexCollapsed';

    const applyIndexCollapsed = (collapsed) => {
        documentationLayout.classList.toggle('documentation-index-collapsed', collapsed);
        const label = collapsed ? 'Expandir índice' : 'Colapsar índice';
        documentationIndexToggle.setAttribute('aria-expanded', String(!collapsed));
        documentationIndexToggle.setAttribute('aria-label', label);
        documentationIndexToggle.title = label;
        documentationIndexToggle.querySelector('i').className = collapsed
            ? 'bi bi-chevron-bar-right'
            : 'bi bi-chevron-bar-left';
    };

    try {
        if (!documentationPrintMode && window.localStorage.getItem(indexStorageKey) === '1') {
            applyIndexCollapsed(true);
        }
    } catch {
        // Ignora restricciones de almacenamiento local.
    }

    documentationIndexToggle.addEventListener('click', () => {
        const nextCollapsed = !documentationLayout.classList.contains('documentation-index-collapsed');
        applyIndexCollapsed(nextCollapsed);
        try {
            window.localStorage.setItem(indexStorageKey, nextCollapsed ? '1' : '0');
        } catch {
            // Ignora restricciones de almacenamiento local.
        }
    });

    if (!documentationPrintMode) {
        const docSearchInput = document.getElementById('documentationSearch');
        const docSearchReset = document.getElementById('resetDocumentationSearch');
        const docSearchCount = document.getElementById('documentationSearchCount');
        const docNoMatches = document.getElementById('documentationNoMatches');
        const docSections = Array.from(document.querySelectorAll('.documentation-section'));
        const docIndexLinks = Array.from(document.querySelectorAll('#documentationIndex .list-group-item'));

        // Precalcula texto buscable incluyendo el contenido oculto de los <template> asociados
        const sectionHaystacks = new Map();
        docSections.forEach((section) => {
            const parts = [section.textContent || ''];
            section.querySelectorAll('[data-doc-template]').forEach((trigger) => {
                const tpl = document.getElementById(trigger.dataset.docTemplate || '');
                if (tpl instanceof HTMLTemplateElement) {
                    parts.push(tpl.content.textContent || '');
                }
            });
            sectionHaystacks.set(section.id, parts.join(' ').toLowerCase());
        });

        const applyDocSearch = () => {
            const term = (docSearchInput ? docSearchInput.value : '').trim().toLowerCase();
            let visibleCount = 0;

            docSections.forEach((section) => {
                const visible = !term || (sectionHaystacks.get(section.id) || '').includes(term);
                section.classList.toggle('d-none', !visible);
                if (visible) visibleCount++;
                const indexLink = docIndexLinks.find((link) => link.getAttribute('href') === `#${section.id}`);
                if (indexLink) {
                    indexLink.classList.toggle('d-none', !visible);
                }
            });

            if (docSearchCount) {
                docSearchCount.textContent = term
                    ? `Mostrando ${visibleCount} de ${docSections.length} secciones`
                    : `${docSections.length} secciones`;
            }
            if (docNoMatches) {
                docNoMatches.classList.toggle('d-none', visibleCount > 0);
            }
        };

        if (docSearchInput) {
            docSearchInput.addEventListener('input', applyDocSearch);
        }
        if (docSearchReset) {
            docSearchReset.addEventListener('click', () => {
                if (docSearchInput) docSearchInput.value = '';
                applyDocSearch();
            });
        }

        if ('IntersectionObserver' in window && docSections.length && docIndexLinks.length) {
            const setActiveIndex = (id) => {
                docIndexLinks.forEach((link) => {
                    const active = link.getAttribute('href') === `#${id}`;
                    link.classList.toggle('active', active);
                    if (active) {
                        link.setAttribute('aria-current', 'true');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });
            };
            const observer = new IntersectionObserver((entries) => {
                const visibleEntry = entries.find((entry) => entry.isIntersecting);
                if (visibleEntry && visibleEntry.target.id) {
                    setActiveIndex(visibleEntry.target.id);
                }
            }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });
            docSections.forEach((section) => observer.observe(section));
        }
    }

    const documentationPrintDetails = [
        ['detail-overview', 'Cómo funciona SysTec'],
        ['detail-foundations', 'Fundamentos y garantías'],
        ['detail-lifecycle', 'Ciclo de vida del dato'],
        ['detail-role-admin', 'Caso de uso: Administrador'],
        ['detail-role-operator', 'Caso de uso: Operador'],
        ['detail-role-technician', 'Caso de uso: Técnico'],
        ['detail-role-consult', 'Caso de uso: Consulta'],
        ['detail-role-personal', 'Caso de uso: Personal'],
        ['detail-states', 'Estados y transiciones'],
        ['detail-assets', 'Bienes e inventario'],
        ['detail-dashboard', 'Dashboard'],
        ['detail-maintenances', 'Mantenimientos'],
        ['detail-renditions', 'Informes de Rendición'],
        ['detail-tickets', 'Tickets de soporte'],
        ['detail-personal', 'Portal Personal'],
        ['detail-reports', 'Historiales e informes'],
        ['detail-imports', 'Importación y sincronización'],
        ['detail-administration', 'Usuarios y cuenta'],
        ['detail-migrations', 'Migraciones y datos'],
        ['detail-security', 'Seguridad y sesiones'],
        ['detail-operations', 'Operación y despliegue'],
        ['detail-ai', 'Asistencia con IA'],
        ['detail-good-practices', 'Buenas prácticas'],
        ['detail-limits', 'Alcance y límites'],
        ['detail-glossary', 'Glosario'],
        ['detail-faq', 'Preguntas frecuentes']
    ];

    if (documentationPrintMode) {
        document.body.classList.add('documentation-print-mode');
        const page = document.querySelector('.documentation-page');
        const appendix = document.createElement('section');
        appendix.className = 'documentation-print-appendix d-flex flex-column gap-3 mt-4';
        appendix.setAttribute('aria-label', 'Contenido ampliado de la documentación');
        const appendixTitle = document.createElement('h2');
        appendixTitle.className = 'h4 mb-0';
        appendixTitle.textContent = 'Contenido ampliado';
        appendix.appendChild(appendixTitle);

        documentationPrintDetails.forEach(([templateId, title]) => {
            const template = document.getElementById(templateId);
            if (!(template instanceof HTMLTemplateElement)) return;

            const section = document.createElement('section');
            section.className = 'card border documentation-print-detail';
            const body = document.createElement('div');
            body.className = 'card-body';
            const heading = document.createElement('h2');
            heading.className = 'h5 mb-3';
            heading.textContent = title;
            body.appendChild(heading);
            body.appendChild(template.content.cloneNode(true));
            section.appendChild(body);
            appendix.appendChild(section);
        });
        page.appendChild(appendix);

        document.getElementById('documentationPrintButton').addEventListener('click', () => window.print());
    }

    document.addEventListener('DOMContentLoaded', async () => {
        const showPrintDiagramError = () => {
            const error = document.createElement('p');
            error.className = 'alert alert-warning mb-0';
            error.setAttribute('role', 'alert');
            error.textContent = 'No se pudieron representar todos los diagramas (puede requerir acceso a CDN). El contenido textual sigue disponible y puedes imprimir igualmente.';
            document.querySelector('.documentation-print-appendix')?.appendChild(error);
            const printButton = document.getElementById('documentationPrintButton');
            if (printButton) {
                printButton.disabled = false;
                printButton.querySelector('span').textContent = 'Imprimir guía (sin diagramas)';
                printButton.removeAttribute('aria-busy');
            }
        };

        if (!window.mermaid) {
            if (documentationPrintMode) showPrintDiagramError();
            return;
        }

        window.mermaid.initialize({
            startOnLoad: false,
            securityLevel: 'strict',
            theme: 'default',
            flowchart: { useMaxWidth: true, htmlLabels: false }
        });

        if (documentationPrintMode) {
            const diagramNodes = Array.from(
                document.querySelectorAll('.documentation-print-mode .mermaid')
            );
            const printPageDiagramThreshold = 850;
            let failedCount = 0;
            for (const [index, node] of diagramNodes.entries()) {
                try {
                    await window.mermaid.run({ nodes: [node] });
                } catch (error) {
                    failedCount += 1;
                    console.error(`No se pudo representar el diagrama imprimible ${index + 1}.`, error);
                    continue;
                }

                const svg = node.querySelector('svg');
                const viewBox = svg?.viewBox.baseVal;
                if (!svg || !viewBox || viewBox.width <= 0 || viewBox.height <= 0) {
                    failedCount += 1;
                    console.error(`El diagrama imprimible ${index + 1} no produjo un SVG válido.`);
                    continue;
                }

                if (Math.max(viewBox.width, viewBox.height) >= printPageDiagramThreshold) {
                    const figure = node.closest('.documentation-flowchart');
                    if (!figure) continue;

                    const orientation = viewBox.width / viewBox.height >= 1.2
                        ? 'landscape'
                        : 'portrait';
                    figure.classList.add(
                        'documentation-flowchart-print-page',
                        `documentation-flowchart-print-${orientation}`
                    );
                    figure.closest('.documentation-section, .documentation-print-detail')
                        ?.classList.add('documentation-has-paged-flowchart');
                }
            }

            if (diagramNodes.length === 0 || failedCount > 0) {
                showPrintDiagramError();
                return;
            }

            const printButton = document.getElementById('documentationPrintButton');
            printButton.disabled = false;
            printButton.removeAttribute('aria-busy');
            printButton.querySelector('span').textContent = 'Imprimir o guardar como PDF';
        }
    }, { once: true });

    const documentationDrawer = document.getElementById('documentationDrawer');
    documentationDrawer.addEventListener('show.bs.offcanvas', (event) => {
        const trigger = event.relatedTarget;
        const templateId = trigger && trigger.dataset ? trigger.dataset.docTemplate : '';
        const template = templateId ? document.getElementById(templateId) : null;
        const title = (trigger && trigger.dataset && trigger.dataset.docTitle)
            || trigger.closest('article')?.querySelector('h3')?.textContent
            || trigger.closest('.documentation-section')?.querySelector('h2')?.textContent
            || 'Detalle de la guía';
        document.getElementById('documentationDrawerTitle').textContent = title;
        const body = document.getElementById('documentationDrawerBody');
        body.replaceChildren();
        if (template instanceof HTMLTemplateElement) {
            body.appendChild(template.content.cloneNode(true));
        }
    });

    documentationDrawer.addEventListener('hide.bs.offcanvas', () => {
        if (documentationDrawer.contains(document.activeElement)) {
            document.activeElement.blur();
        }
    });

    documentationDrawer.addEventListener('shown.bs.offcanvas', async () => {
        const diagramNodes = document.querySelectorAll('#documentationDrawerBody .mermaid');
        if (diagramNodes.length === 0) return;

        const showRenderError = () => {
            const error = document.createElement('p');
            error.className = 'alert alert-warning mt-3';
            error.setAttribute('role', 'alert');
            error.textContent = 'No se pudo representar el diagrama. El contenido textual sigue disponible en esta sección.';
            document.getElementById('documentationDrawerBody').appendChild(error);
        };

        if (!window.mermaid) {
            showRenderError();
            return;
        }

        try {
            await window.mermaid.run({ nodes: diagramNodes });
        } catch (error) {
            console.error('No se pudo representar un diagrama de la documentación.', error);
            showRenderError();
        }
    });
</script>
<style nonce="<?= e(systec_csp_nonce()) ?>">
    @page documentation-diagram-portrait {
        size: portrait;
        margin: 10mm;
    }

    @page documentation-diagram-landscape {
        size: landscape;
        margin: 10mm;
    }

    .documentation-index {
        position: sticky;
        top: .75rem;
        max-height: calc(100vh - 1.5rem);
        overflow-y: auto;
    }

    .documentation-index .list-group-item {
        padding: .5rem .75rem;
    }

    .documentation-index-collapsed > .documentation-index-column {
        flex: 0 0 4.5rem;
        width: 4.5rem;
        max-width: 4.5rem;
    }

    .documentation-index-collapsed > .documentation-content-column {
        flex: 1 0 0%;
        width: auto;
        max-width: none;
    }

    .documentation-index-collapsed .documentation-index-title,
    .documentation-index-collapsed .documentation-index .list-group-item span {
        display: none;
    }

    .documentation-index-collapsed .documentation-index .list-group-item {
        justify-content: center;
        padding-right: .25rem;
        padding-left: .25rem;
    }

    .documentation-section {
        scroll-margin-top: 1rem;
    }

    .documentation-drawer {
        --bs-offcanvas-width: 50vw;
        max-width: 100vw;
    }

    .documentation-flowchart {
        min-width: 0;
        margin: 0 0 1rem;
        padding: .75rem;
        border: 1px solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        background: var(--bs-tertiary-bg);
    }

    .documentation-flowchart .mermaid {
        max-width: 100%;
        margin: 0;
        overflow-x: auto;
        text-align: center;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .documentation-flowchart svg {
        max-width: 100%;
        height: auto;
    }

    .documentation-print-mode .app-header,
    .documentation-print-mode .app-footer,
    .documentation-print-mode .documentation-drawer {
        display: none;
    }

    .documentation-print-mode .documentation-page > .row > .col-lg-3 {
        display: none;
    }

    .documentation-print-mode .documentation-page > .row > .col-lg-9 {
        width: 100%;
    }

    .documentation-print-mode .app-main {
        margin-top: 0;
        padding-right: .5rem;
        padding-left: .5rem;
    }

    @media print {
        .documentation-print-actions,
        .documentation-page .btn,
        .documentation-print-mode .app-header,
        .documentation-print-mode .app-footer {
            display: none;
        }

        .documentation-print-mode .app-main {
            padding: 0;
        }

        .documentation-print-mode .documentation-page,
        .documentation-print-mode .documentation-section,
        .documentation-print-mode .documentation-print-detail {
            color: #000;
            background: #fff;
            box-shadow: none;
        }

        .documentation-print-mode .documentation-flowchart svg {
            max-height: 78vh;
        }

        .documentation-print-mode .documentation-section,
        .documentation-print-mode .documentation-print-detail,
        .documentation-print-mode .documentation-flowchart {
            break-inside: avoid;
        }

        .documentation-print-mode .documentation-section.documentation-has-paged-flowchart,
        .documentation-print-mode .documentation-print-detail.documentation-has-paged-flowchart {
            break-inside: auto;
        }

        .documentation-print-mode .documentation-flowchart-print-page {
            break-before: page;
            break-after: page;
            break-inside: avoid;
        }

        .documentation-print-mode .documentation-flowchart-print-portrait {
            page: documentation-diagram-portrait;
        }

        .documentation-print-mode .documentation-flowchart-print-landscape {
            page: documentation-diagram-landscape;
        }

        .documentation-print-mode .documentation-flowchart-print-page .mermaid {
            overflow: visible;
        }

        .documentation-print-mode .documentation-flowchart-print-page .mermaid svg {
            width: auto;
            max-width: 100%;
            max-height: 72vh;
            height: auto;
            margin: 0 auto;
        }

        .documentation-print-mode .documentation-flowchart-print-portrait .mermaid svg {
            max-height: 78vh;
        }

        .documentation-print-mode .documentation-flowchart-print-landscape .mermaid svg {
            max-height: 68vh;
        }
    }

    @media (max-width: 991.98px) {
        .documentation-index {
            position: static;
            max-height: none;
        }

        .documentation-index-collapsed > .documentation-index-column,
        .documentation-index-collapsed > .documentation-content-column {
            flex: 0 0 100%;
            width: 100%;
            max-width: 100%;
        }

        .documentation-index-collapsed .documentation-index .list-group {
            flex-direction: row;
            flex-wrap: wrap;
        }

        .documentation-index-collapsed .documentation-index .list-group-item {
            flex: 0 0 3rem;
        }
    }

    @media (max-width: 575.98px) {
        .documentation-drawer {
            --bs-offcanvas-width: 100vw;
        }
    }
</style>
<?php
$main = ob_get_clean();
include __DIR__ . '/include/layout.php';
