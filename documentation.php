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
$roleLabel = is_array($user) && isset($user['role'], $roleLabels[$user['role']])
    ? $roleLabels[$user['role']]
    : 'Usuario';
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
            isset($definition[2]) ? $definition[2] : ''
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
            array('state', 'Actualizar estado según la atención', Node::SQUARE),
            array('choices', 'Iniciado · En progreso · Con retraso · En espera · Finalizado', Node::ROUND),
            array('followup', 'Añadir seguimiento si corresponde', Node::SQUARE),
            array('history', 'Guardar cambios en el historial', Node::STADIUM),
        ),
        array(
            array('register', 'state'),
            array('state', 'choices'),
            array('choices', 'followup'),
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
            array('track', 'Consultar estado y seguimientos propios', Node::STADIUM),
        ),
        array(
            array('login', 'asset'),
            array('asset', 'ai'),
            array('ai', 'proposal', 'Sí'),
            array('ai', 'submit', 'No'),
            array('proposal', 'submit'),
            array('submit', 'track'),
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
            array('maintenance', 'Registrar mantenimiento asociado al bien y ticket', Node::SQUARE),
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
            array('cache', 'Actualizar caché local de adscripciones/asignaciones', Node::SQUARE),
            array('views', 'Consultar los datos desde las vistas del sistema', Node::STADIUM),
        ),
        array(
            array('role', 'source'),
            array('source', 'validate'),
            array('validate', 'persist', 'Importación'),
            array('validate', 'cache', 'Sincronización'),
            array('persist', 'views'),
            array('cache', 'views'),
        )
    ),
    'ai' => $makeFlowchart(
        array(
            array('input', 'Completar relato o texto narrativo', Node::STADIUM),
            array('send', 'Enviar contenido al servicio NVIDIA Build', Node::SQUARE),
            array('proposal', 'Recibir propuesta de redacción', Node::SQUARE),
            array('review', 'Revisar y corregir los campos', Node::SQUARE),
            array('decision', '¿Guardar el formulario?', Node::RHOMBUS),
            array('save', 'Guardar manualmente el registro', Node::STADIUM),
            array('discard', 'Descartar o seguir editando', Node::STADIUM),
        ),
        array(
            array('input', 'send'),
            array('send', 'proposal'),
            array('proposal', 'review'),
            array('review', 'decision'),
            array('decision', 'save', 'Sí'),
            array('decision', 'discard', 'No'),
        )
    ),
);

$sections = array(
    array('id' => 'overview', 'title' => 'Cómo funciona SysTec', 'icon' => 'bi-diagram-3'),
    array('id' => 'roles', 'title' => 'Roles y casos de uso', 'icon' => 'bi-people'),
    array('id' => 'assets', 'title' => 'Bienes e inventario', 'icon' => 'bi-box-seam'),
    array('id' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bi-speedometer2'),
    array('id' => 'maintenances', 'title' => 'Mantenimientos', 'icon' => 'bi-tools'),
    array('id' => 'tickets', 'title' => 'Tickets de soporte', 'icon' => 'bi-ticket-detailed'),
    array('id' => 'personal', 'title' => 'Portal Personal', 'icon' => 'bi-person-workspace'),
    array('id' => 'reports', 'title' => 'Historiales e informes', 'icon' => 'bi-file-earmark-text'),
    array('id' => 'imports', 'title' => 'Importación y sincronización', 'icon' => 'bi-arrow-repeat'),
    array('id' => 'administration', 'title' => 'Usuarios y cuenta', 'icon' => 'bi-shield-lock'),
    array('id' => 'ai', 'title' => 'Asistencia con IA', 'icon' => 'bi-magic'),
    array('id' => 'good-practices', 'title' => 'Buenas prácticas', 'icon' => 'bi-check2-circle'),
);

ob_start();
?>
<div class="container-fluid text-start documentation-page">
    <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3"
             aria-labelledby="documentation-title">
        <div>
            <h1 class="h3 mb-1" id="documentation-title">
                <i class="bi bi-journal-text text-primary me-2" aria-hidden="true"></i>Guía de SysTec
            </h1>
            <p class="text-body-secondary mb-0">
                Manual funcional para conocer los módulos, el flujo de trabajo y las acciones disponibles según tu rol.
            </p>
        </div>
        <span class="badge text-bg-light border text-body-secondary align-self-md-start">
            Tu perfil: <?= e($roleLabel) ?>
        </span>
    </section>

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
                                    data-doc-template="detail-overview">
                                Ampliar <i class="bi bi-arrow-up-right-square ms-1" aria-hidden="true"></i>
                            </button>
                        </div>
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
                                <div class="h-100 rounded border p-3">
                                    <h3 class="h6"><i class="bi bi-shield-fill-check text-danger me-1" aria-hidden="true"></i>Administrador</h3>
                                    <p class="small mb-2">Administra usuarios y roles; además, puede acceder a la gestión operativa, importaciones y sincronización.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-admin">Caso de uso</button>
                                </div>
                            </article>
                            <article class="col-12 col-sm-6">
                                <div class="h-100 rounded border p-3">
                                    <h3 class="h6"><i class="bi bi-person-gear text-primary me-1" aria-hidden="true"></i>Operador</h3>
                                    <p class="small mb-2">Opera el inventario, las importaciones y los registros de mantenimiento y tickets.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-operator">Caso de uso</button>
                                </div>
                            </article>
                            <article class="col-12 col-sm-6">
                                <div class="h-100 rounded border p-3">
                                    <h3 class="h6"><i class="bi bi-tools text-info me-1" aria-hidden="true"></i>Técnico</h3>
                                    <p class="small mb-2">Atiende tickets y registra o actualiza los mantenimientos asignados, sin administrar usuarios ni importar bienes.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-technician">Caso de uso</button>
                                </div>
                            </article>
                            <article class="col-12 col-sm-6">
                                <div class="h-100 rounded border p-3">
                                    <h3 class="h6"><i class="bi bi-eye text-secondary me-1" aria-hidden="true"></i>Consulta</h3>
                                    <p class="small mb-2">Consulta el listado y los datos de tickets sin opciones para crear, cambiar estado, añadir seguimiento o eliminar.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-consult">Caso de uso</button>
                                </div>
                            </article>
                            <article class="col-12 col-sm-6">
                                <div class="h-100 rounded border p-3">
                                    <h3 class="h6"><i class="bi bi-person-badge text-warning me-1" aria-hidden="true"></i>Personal</h3>
                                    <p class="small mb-2">Registra solicitudes y consulta o actualiza el seguimiento de sus propios tickets desde su portal.</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                            data-doc-template="detail-role-personal">Caso de uso</button>
                                </div>
                            </article>
                        </div>
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
                                data-doc-template="detail-assets">Ampliar</button>
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
                                data-doc-template="detail-dashboard">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="maintenances"
                         aria-labelledby="maintenances-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="maintenances-title">Mantenimientos</h2>
                            <p class="mb-0">La bitácora registra bien, tipo de trabajo, técnico, fecha, motivo, descripción, notas, estado y fecha del siguiente mantenimiento; admite búsqueda, filtros, edición, exportación e impresión de informes.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-maintenances">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="tickets"
                         aria-labelledby="tickets-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="tickets-title">Tickets de soporte</h2>
                            <p class="mb-0">Un ticket documenta una solicitud o incidencia asociada a un bien. La bandeja permite consultar el estado y el historial; los roles operativos pueden registrar tickets, añadir seguimientos y gestionar estados.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-tickets">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="personal"
                         aria-labelledby="personal-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="personal-title">Portal Personal</h2>
                            <p class="mb-0">El rol Personal utiliza un área propia para registrar solicitudes, filtrar sus tickets por texto, estado, categoría y fechas, y revisar el detalle y seguimiento de los tickets que le pertenecen.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-personal">Ampliar</button>
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
                                data-doc-template="detail-reports">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="imports"
                         aria-labelledby="imports-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="imports-title">Importación y sincronización</h2>
                            <p class="mb-0">Los roles autorizados pueden importar bienes y mantenimientos, o actualizar datos de asignación desde la fuente institucional. La sincronización mantiene una copia local consultable.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-imports">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="administration"
                         aria-labelledby="administration-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="administration-title">Usuarios y cuenta</h2>
                            <p class="mb-0">La administración de usuarios está reservada al administrador. Cada usuario puede cambiar su contraseña y cerrar su sesión desde la barra superior.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-administration">Ampliar</button>
                    </div>
                </section>

                <section class="card shadow-sm border-0 documentation-section" id="ai"
                         aria-labelledby="ai-title">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-2" id="ai-title">Asistencia con IA</h2>
                            <p class="mb-0">En los formularios habilitados, la IA propone mejoras de redacción para tickets, relatos de solicitudes, mantenimientos y notas de seguimiento. La propuesta se revisa antes de guardar.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary align-self-sm-start flex-shrink-0"
                                data-bs-toggle="offcanvas" data-bs-target="#documentationDrawer"
                                data-doc-template="detail-ai">Ampliar</button>
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
                                data-doc-template="detail-good-practices">Ampliar</button>
                    </div>
                </section>
            </div>
        </div>
    </div>

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
    <p>Los registros quedan vinculados a un bien y permiten consultar la evolución de su atención:</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo general: del bien al historial</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo general de atención desde la identificación del bien hasta el historial y el informe"><?= e($documentationDiagrams['overview']) ?></pre>
        <p class="visually-hidden">Identificar el bien, registrar un ticket si hay una solicitud, registrar un mantenimiento si se realiza un trabajo y consultar después el historial.</p>
    </figure>
    <ol>
        <li>Ubica el bien desde el Dashboard o el selector de bien.</li>
        <li>Registra un ticket para dejar constancia de una solicitud o incidencia, si corresponde.</li>
        <li>Cuando se realiza un trabajo, registra el mantenimiento y vincula el ticket asociado cuando esté disponible.</li>
        <li>Consulta el historial del bien para revisar tickets y mantenimientos relacionados.</li>
    </ol>
    <p class="mb-0">Los permisos pueden variar entre consulta, creación, actualización y administración, según el rol.</p>
</template>
<template id="detail-role-admin">
    <p><strong>Escenario:</strong> administrar el acceso y supervisar la operación.</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo de trabajo del Administrador</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de trabajo del Administrador para gestionar usuarios, módulos operativos e importaciones"><?= e($documentationDiagrams['role-admin']) ?></pre>
        <p class="visually-hidden">El Administrador inicia sesión, elige entre administrar cuentas, gestionar módulos operativos o importar y sincronizar datos, y verifica el resultado.</p>
    </figure>
    <ul>
        <li>Crear o actualizar cuentas, activar o desactivar usuarios y asignar uno de los roles del sistema.</li>
        <li>Acceder a los módulos de inventario, mantenimientos, tickets e importación disponibles para el personal operativo.</li>
        <li>Eliminar tickets mediante el borrado lógico disponible para Administrador y Operador.</li>
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
        <li>Consultar el inventario, importar o sincronizar información y actualizar datos del bien según las acciones habilitadas.</li>
        <li>Registrar y actualizar mantenimientos y tickets, administrar sus estados y añadir seguimientos.</li>
        <li>Eliminar tickets; la administración de cuentas permanece reservada al Administrador.</li>
    </ul>
</template>
<template id="detail-role-technician">
    <p><strong>Escenario:</strong> atender incidencias y dejar constancia del trabajo técnico.</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo de atención del Técnico</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo del Técnico para atender un ticket y registrar un mantenimiento si se realiza un trabajo"><?= e($documentationDiagrams['role-technician']) ?></pre>
        <p class="visually-hidden">El Técnico revisa el ticket, registra seguimiento y estado; si realiza mantenimiento, lo asocia al bien y ticket, actualiza el estado y consulta la trazabilidad.</p>
    </figure>
    <ul>
        <li>Consultar la bandeja de tickets y sus historiales; crear tickets, registrar seguimientos y actualizar estados.</li>
        <li>Registrar, editar y cambiar el estado de mantenimientos, incluidos los datos narrativos y la siguiente fecha sugerida.</li>
        <li>Consultar bienes y reportes. No tiene las acciones de importación, administración de usuarios ni eliminación de tickets.</li>
    </ul>
</template>
<template id="detail-role-consult">
    <p><strong>Escenario:</strong> consultar tickets sin modificarlos.</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo de consulta de tickets</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de solo lectura para el rol Consulta"><?= e($documentationDiagrams['role-consult']) ?></pre>
        <p class="visually-hidden">El usuario Consulta abre el listado, busca o filtra tickets, revisa el resumen, estado e historial disponible, sin crear ni modificar registros.</p>
    </figure>
    <ul>
        <li>Acceder a la bandeja para revisar tickets y abrir la vista de información resumida.</li>
        <li>No puede registrar tickets, cambiar estados, añadir seguimientos, eliminar registros ni abrir el detalle completo desde la bandeja.</li>
        <li>La disponibilidad de otras páginas y acciones se determina por los permisos propios de cada módulo.</li>
    </ul>
</template>
<template id="detail-role-personal">
    <p><strong>Escenario:</strong> reportar un problema y verificar su atención.</p>
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Flujo de solicitud del usuario Personal</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo del usuario Personal para crear un ticket y consultar su atención"><?= e($documentationDiagrams['personal']) ?></pre>
        <p class="visually-hidden">La persona selecciona un bien, describe la solicitud, opcionalmente revisa una propuesta de IA, envía el ticket y consulta sus propios seguimientos.</p>
    </figure>
    <ul>
        <li>Crear un ticket asociado a un bien institucional.</li>
        <li>Consultar sus propios tickets, filtrar por estado, categoría o fechas y revisar los seguimientos.</li>
        <li>No accede a tickets de otros solicitantes ni a los módulos de administración e inventario operativo.</li>
    </ul>
</template>
<template id="detail-assets">
    <ul>
        <li>Busca por código, descripción, condición y otros filtros disponibles en la vista.</li>
        <li>La condición del bien se distingue de los estados de ticket y mantenimiento: describe el estado registrado del activo.</li>
        <li>La información de responsable o adscripción depende de la sincronización con el catálogo institucional.</li>
        <li>El historial del bien ayuda a revisar las intervenciones y solicitudes vinculadas.</li>
    </ul>
</template>
<template id="detail-dashboard">
    <ul>
        <li>Usa los indicadores de condición para filtrar rápidamente los bienes.</li>
        <li>La tabla puede incluir código, descripción, condición, ubicación/adscripción y datos de asignación cuando existan.</li>
        <li>Las acciones por fila dependen del rol e incluyen abrir el historial y, para los roles autorizados, crear tickets o mantenimientos relacionados.</li>
        <li>Los datos de adscripción y asignación proceden de una caché local que puede estar desactualizada hasta la próxima sincronización.</li>
    </ul>
</template>
<template id="detail-maintenances">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Estados y transiciones permitidas</figcaption>
        <pre class="mermaid" role="img" aria-label="Transiciones de estados de un mantenimiento"><?= e($documentationDiagrams['maintenances']) ?></pre>
        <p class="visually-hidden">Un mantenimiento iniciado puede pasar a revisión o finalizar; uno en revisión puede finalizar; un mantenimiento finalizado puede volver a revisión.</p>
    </figure>
    <ul>
        <li>El formulario permite asociar el mantenimiento con un bien y, opcionalmente, con un ticket del mismo bien.</li>
        <li>Al editar, el selector de tickets se carga para el bien asociado al mantenimiento.</li>
        <li>Los estados disponibles incluyen iniciado, revisión y finalizado; el vencimiento puede señalarse cuando pasó la fecha siguiente sin finalizar el registro.</li>
        <li>Los filtros ayudan a localizar por texto, tipo, técnico, estado o fechas. La exportación CSV utiliza los registros filtrados.</li>
        <li>El informe imprimible ofrece el historial técnico relacionado con un bien.</li>
    </ul>
</template>
<template id="detail-tickets">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Atención de una solicitud</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de actualización del estado y registro de seguimientos de un ticket"><?= e($documentationDiagrams['tickets']) ?></pre>
        <p class="visually-hidden">Registrar la solicitud, actualizar su estado, añadir seguimientos cuando correspondan y conservar los cambios en el historial.</p>
    </figure>
    <ul>
        <li>El ticket incluye título, descripción, observación opcional, bien y estado; el historial conserva quién realizó cada seguimiento o cambio.</li>
        <li>Los estados representan la atención de la solicitud (por ejemplo, iniciado, en progreso, en espera, con retraso o finalizado).</li>
        <li>Los roles operativos pueden abrir el detalle completo, registrar seguimiento y actualizar el estado según sus permisos.</li>
        <li>Administrador y Operador pueden eliminar tickets mediante borrado lógico; los elementos eliminados no forman parte del listado activo.</li>
        <li>Un ticket no sustituye al registro de mantenimiento: describe la solicitud, mientras que el mantenimiento documenta el trabajo realizado.</li>
    </ul>
</template>
<template id="detail-personal">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Crear y dar seguimiento a una solicitud</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo del usuario Personal al registrar y consultar sus tickets"><?= e($documentationDiagrams['personal']) ?></pre>
        <p class="visually-hidden">La persona selecciona un bien, redacta una solicitud, decide si quiere asistencia de IA, revisa el contenido, envía el ticket y consulta su estado y seguimientos.</p>
    </figure>
    <ul>
        <li>En «Mis tickets» utiliza la búsqueda y los filtros de estado, categoría, intervalo de fechas y adscripción cuando esté disponible.</li>
        <li>Abre el detalle para revisar el bien, el estado y la secuencia de seguimientos.</li>
        <li>El portal restringe los registros al usuario creador autenticado.</li>
        <li>La creación de tickets puede usar la asistencia de IA para preparar el título, descripción y observación; revisa la propuesta antes de enviar.</li>
    </ul>
</template>
<template id="detail-reports">
    <ul>
        <li>El módulo de Historiales permite consultar por bien los mantenimientos y tickets asociados.</li>
        <li>Los informes de mantenimiento pueden imprimirse para contar con una copia legible del historial.</li>
        <li>El reporte de bienes dañados destaca los activos con condición mala o muy mala y muestra el historial de mantenimientos disponible.</li>
        <li>Los registros eliminados lógicamente no deben confundirse con registros activos.</li>
    </ul>
</template>
<template id="detail-imports">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Procesamiento de importaciones y sincronización</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de importación de datos o sincronización de asignaciones institucionales"><?= e($documentationDiagrams['imports']) ?></pre>
        <p class="visually-hidden">Administrador u Operador elige un proceso, se validan sus datos y se importan en el sistema o se actualiza la caché local de asignaciones para consulta.</p>
    </figure>
    <ul>
        <li>Las opciones de importación y sincronización están disponibles para Administrador y Operador.</li>
        <li>La importación de bienes y mantenimientos incorpora datos desde archivos compatibles con los procesos de carga del sistema.</li>
        <li>La sincronización de asignaciones actualiza la copia local de adscripciones y responsables utilizada por las vistas.</li>
        <li>Revisa los resultados y advertencias que muestra el proceso antes de continuar con otras actualizaciones.</li>
    </ul>
</template>
<template id="detail-administration">
    <ul>
        <li>El Administrador crea y gestiona cuentas, define roles y puede desactivar cuentas.</li>
        <li>Los roles disponibles son Administrador, Operador, Técnico, Consulta y Personal.</li>
        <li>Cada usuario puede cambiar su contraseña desde la barra superior. Cierra sesión al terminar, especialmente en equipos compartidos.</li>
    </ul>
</template>
<template id="detail-ai">
    <figure class="documentation-flowchart">
        <figcaption class="small fw-semibold mb-2">Propuesta de texto asistida por IA</figcaption>
        <pre class="mermaid" role="img" aria-label="Flujo de generación, revisión y decisión de guardar una propuesta de IA"><?= e($documentationDiagrams['ai']) ?></pre>
        <p class="visually-hidden">La persona completa el texto, el sistema lo envía a NVIDIA Build y recibe una propuesta que debe revisar. Luego decide si guarda manualmente el formulario o descarta la propuesta.</p>
    </figure>
    <ul>
        <li>La IA propone texto; no guarda automáticamente el ticket, seguimiento ni mantenimiento.</li>
        <li>Revisa nombres, códigos, hechos, causas y resultado antes de confirmar el formulario.</li>
        <li>En mantenimiento, la asistencia puede considerar descripción, motivo de falla y notas, manteniéndolos en campos separados.</li>
        <li>En tickets, la propuesta debe resumir la solicitud o incidencia, no redactar un plan de mantenimiento.</li>
        <li>Los textos enviados al servicio de IA se procesan externamente para generar la propuesta. Evita incluir datos personales o sensibles que no sean necesarios.</li>
    </ul>
</template>
<template id="detail-good-practices">
    <ul>
        <li>Comprueba el código y descripción del bien antes de registrar un ticket o mantenimiento.</li>
        <li>Describe hechos observables y separa la causa reportada, el trabajo realizado y las notas adicionales.</li>
        <li>Actualiza el ticket conforme avance la atención y registra el seguimiento necesario para conservar la trazabilidad.</li>
        <li>Usa la fecha siguiente de mantenimiento como referencia de seguimiento y verifica que el estado refleje la situación real.</li>
        <li>Si usas una propuesta de IA, valida su exactitud; la responsabilidad sobre el registro final sigue siendo de quien lo envía.</li>
    </ul>
</template>

<script nonce="<?= e(systec_csp_nonce()) ?>" defer
        src="https://cdn.jsdelivr.net/npm/mermaid@10.9.3/dist/mermaid.min.js"
        integrity="sha384-R63zfMfSwJF4xCR11wXii+QUsbiBIdiDzDbtxia72oGWfkT7WHJfmD/I/eeHPJyT"
        crossorigin="anonymous"></script>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const documentationPrintMode = <?= js($printMode) ?>;
    const documentationLayout = document.getElementById('documentationLayout');
    const documentationIndexToggle = document.getElementById('documentationIndexToggle');
    documentationIndexToggle.addEventListener('click', () => {
        const collapsed = documentationLayout.classList.toggle('documentation-index-collapsed');
        const label = collapsed ? 'Expandir índice' : 'Colapsar índice';
        documentationIndexToggle.setAttribute('aria-expanded', String(!collapsed));
        documentationIndexToggle.setAttribute('aria-label', label);
        documentationIndexToggle.title = label;
        documentationIndexToggle.querySelector('i').className = collapsed
            ? 'bi bi-chevron-bar-right'
            : 'bi bi-chevron-bar-left';
    });

    const documentationPrintDetails = [
        ['detail-overview', 'Cómo funciona SysTec'],
        ['detail-role-admin', 'Caso de uso: Administrador'],
        ['detail-role-operator', 'Caso de uso: Operador'],
        ['detail-role-technician', 'Caso de uso: Técnico'],
        ['detail-role-consult', 'Caso de uso: Consulta'],
        ['detail-role-personal', 'Caso de uso: Personal'],
        ['detail-assets', 'Bienes e inventario'],
        ['detail-dashboard', 'Dashboard'],
        ['detail-maintenances', 'Mantenimientos'],
        ['detail-tickets', 'Tickets de soporte'],
        ['detail-personal', 'Portal Personal'],
        ['detail-reports', 'Historiales e informes'],
        ['detail-imports', 'Importación y sincronización'],
        ['detail-administration', 'Usuarios y cuenta'],
        ['detail-ai', 'Asistencia con IA'],
        ['detail-good-practices', 'Buenas prácticas']
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
            error.textContent = 'No se pudieron representar todos los diagramas. Recarga la página para volver a intentarlo; la impresión está deshabilitada para evitar una guía incompleta.';
            document.querySelector('.documentation-print-appendix')?.appendChild(error);
            const printButton = document.getElementById('documentationPrintButton');
            printButton.querySelector('span').textContent = 'No se pudo preparar la impresión';
            printButton.removeAttribute('aria-busy');
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
        const title = trigger.closest('article')?.querySelector('h3')?.textContent
            || trigger.closest('.documentation-section')?.querySelector('h2')?.textContent
            || 'Detalle de la guía';
        document.getElementById('documentationDrawerTitle').textContent = title;
        const body = document.getElementById('documentationDrawerBody');
        body.replaceChildren();
        if (template instanceof HTMLTemplateElement) {
            body.appendChild(template.content.cloneNode(true));
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
