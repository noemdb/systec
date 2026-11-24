<?php
$currentUserForRendition = auth_user();
$defaultRenditionPreparedBy = is_array($currentUserForRendition) && isset($currentUserForRendition['username'])
    ? (string) $currentUserForRendition['username']
    : 'Equipo Técnico';
?>

<!-- Drawer Wizard de Informe de Rendición (derecha a izquierda) -->
<div class="offcanvas offcanvas-end rendition-wizard-drawer text-start" tabindex="-1"
     id="renditionReportWizardModal"
     aria-labelledby="renditionReportWizardTitle" aria-describedby="renditionReportWizardHelp">
    <div class="offcanvas-header border-bottom">
        <div>
            <h2 class="offcanvas-title h5 mb-1" id="renditionReportWizardTitle">
                <i class="bi bi-file-earmark-richtext text-primary me-2" aria-hidden="true"></i>Informe de Rendición
            </h2>
            <p class="small text-body-secondary mb-0" id="renditionReportWizardHelp">
                Configura el período y los parámetros, genera el informe en formato Markdown con apoyo de IA, edítalo y apruébalo.
            </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <form id="renditionReportWizardForm" class="rendition-wizard-drawer-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" id="renditionReportId" value="">
        <input type="hidden" id="renditionReportCode" value="">
        <input type="hidden" id="renditionScopeHash" value="">
        <input type="hidden" id="renditionAiGenerated" value="1">
        <input type="hidden" id="renditionDateFrom" value="">
        <input type="hidden" id="renditionDateTo" value="">

        <div class="offcanvas-body">
            <ol class="personal-ticket-wizard-progress list-unstyled mb-3" aria-label="Progreso del Informe de Rendición">
                <li data-rendition-indicator="1" class="is-active" aria-current="step">
                    <button type="button" class="personal-ticket-wizard-step-btn" data-rendition-step-trigger="1">
                        <span class="personal-ticket-wizard-number">1</span>
                        <span>Período y parámetros</span>
                    </button>
                </li>
                <li data-rendition-indicator="2">
                    <button type="button" class="personal-ticket-wizard-step-btn" data-rendition-step-trigger="2">
                        <span class="personal-ticket-wizard-number">2</span>
                        <span>Contenido IA y edición</span>
                    </button>
                </li>
                <li data-rendition-indicator="3">
                    <button type="button" class="personal-ticket-wizard-step-btn" data-rendition-step-trigger="3">
                        <span class="personal-ticket-wizard-number">3</span>
                        <span>Revisión y aprobación</span>
                    </button>
                </li>
            </ol>
            <p class="visually-hidden" id="renditionWizardLiveStatus" role="status" aria-live="polite">
                Paso 1 de 3: Período y parámetros
            </p>

            <div id="renditionWizardAlert" class="alert d-none mb-3" role="alert"></div>

            <!-- PASO 1: Período y parámetros -->
            <section data-rendition-step="1" aria-labelledby="renditionStep1Heading">
                <h3 class="h6 mb-3" id="renditionStep1Heading" tabindex="-1">
                    1. Define el período y parámetros del Informe de Rendición
                </h3>

                <div class="row g-3 mb-3 align-items-end">
                    <div class="col-12 col-md-8">
                        <div class="border rounded px-3 py-2 bg-body-tertiary d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <span class="d-block small text-body-secondary fw-semibold">Período seleccionado en filtros</span>
                                <span class="fw-semibold" id="renditionPeriodText">
                                    <span id="renditionDateFromText">—</span> al <span id="renditionDateToText">—</span>
                                </span>
                            </div>
                            <span class="badge text-bg-light border text-body-secondary">
                                <i class="bi bi-calendar-range me-1" aria-hidden="true"></i>Desde filtro de tabla
                            </span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="renditionPreparedBy" class="form-label fw-semibold small">
                            Responsable que rinde <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" id="renditionPreparedBy"
                               maxlength="120" value="<?= e($defaultRenditionPreparedBy) ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label for="renditionTitle" class="form-label fw-semibold small">
                            Título del informe <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" id="renditionTitle"
                               maxlength="180"
                               value="INFORME DE RENDICIÓN DE MANTENIMIENTO Y ACTIVIDADES ASOCIADAS" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="renditionIssuingUnit" class="form-label fw-semibold small">
                            Unidad emisora <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" id="renditionIssuingUnit"
                               maxlength="160"
                               value="Oficina de Informática · San Felipe, Estado Yaracuy" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="renditionRecipientName" class="form-label fw-semibold small">
                            Dirigido a / Destinatario <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input type="text" class="form-control form-control-sm" id="renditionRecipientName"
                               maxlength="160"
                               value="Dirección / Coordinación de Bienes Institucionales" required>
                    </div>
                </div>

                <div class="border rounded p-3 bg-body-tertiary mb-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="renditionUseActiveFilters" checked>
                                <label class="form-check-label small fw-semibold" for="renditionUseActiveFilters">
                                    Aplicar los filtros activos de la tabla al alcance del informe
                                </label>
                            </div>
                            <div class="small text-body-secondary mt-1" id="renditionActiveFiltersSummary">
                                Alcance actual: Todos los mantenimientos del rango de fechas.
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="renditionIncludeTickets" checked>
                                <label class="form-check-label small fw-semibold" for="renditionIncludeTickets">
                                    Incluir actividades asociadas (tickets vinculados y atendidos en el período)
                                </label>
                            </div>
                            <div class="small text-body-secondary mt-1">
                                Incorpora en el informe los tickets vinculados a los mantenimientos y bienes intervenidos.
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="renditionCustomNotes" class="form-label fw-semibold small">
                        Observaciones o contexto adicional para la IA <span class="text-body-secondary fw-normal">(opcional)</span>
                    </label>
                    <textarea class="form-control form-control-sm" id="renditionCustomNotes" rows="3" maxlength="2000"
                              placeholder="Ej.: Destacar el operativo preventivo de equipos de informática o consideraciones relevantes para la rendición del período..."></textarea>
                    <div class="form-text">
                        La IA utilizará la plantilla <code>template/informe_rendicion.md</code> junto con los registros reales del rango seleccionado.
                    </div>
                </div>
            </section>

            <!-- PASO 2: Contenido generado con IA y edición -->
            <section data-rendition-step="2" aria-labelledby="renditionStep2Heading" hidden>
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
                    <div>
                        <h3 class="h6 mb-0" id="renditionStep2Heading" tabindex="-1">
                            2. Revisa y modifica el contenido generado (Formato Markdown)
                        </h3>
                        <p class="small text-body-secondary mb-0" id="renditionStep2Meta">
                            Puedes ajustar libremente cualquier sección antes de continuar a la aprobación.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Modo de visualización del informe">
                            <button type="button" class="btn btn-outline-primary active" id="renditionTabEditBtn" aria-pressed="true">
                                <i class="bi bi-code-square me-1" aria-hidden="true"></i>Editar Markdown
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="renditionTabPreviewBtn" aria-pressed="false">
                                <i class="bi bi-eye me-1" aria-hidden="true"></i>Vista previa
                            </button>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="renditionRegenerateAiBtn"
                                title="Volver a generar el borrador con IA desde los datos del período">
                            <i class="bi bi-magic me-1" id="renditionRegenerateAiIcon" aria-hidden="true"></i>
                            <span id="renditionRegenerateAiLabel">Regenerar con IA</span>
                        </button>
                    </div>
                </div>

                <div id="renditionEditorPane">
                    <label for="renditionMarkdownTextarea" class="visually-hidden">Contenido Markdown del Informe de Rendición</label>
                    <textarea class="form-control font-monospace small" id="renditionMarkdownTextarea"
                              rows="18" required></textarea>
                </div>

                <div id="renditionPreviewPane" class="border rounded p-3 bg-body-tertiary d-none rendition-preview-scroll">
                </div>
            </section>

            <!-- PASO 3: Revisión, aprobación y guardado -->
            <section data-rendition-step="3" aria-labelledby="renditionStep3Heading" hidden>
                <h3 class="h6 mb-3" id="renditionStep3Heading" tabindex="-1">
                    3. Verificación de unicidad, aprobación y resguardo
                </h3>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-lg-5">
                        <div class="border rounded-3 bg-body-tertiary p-3 h-100">
                            <h4 class="h6 text-body-secondary text-uppercase small mb-2">Ficha de control y unicidad</h4>
                            <dl class="row g-2 small mb-0">
                                <dt class="col-5 text-body-secondary">Código asignado</dt>
                                <dd class="col-7 fw-semibold mb-0" id="renditionSummaryCode">—</dd>

                                <dt class="col-5 text-body-secondary">Estado actual</dt>
                                <dd class="col-7 mb-0" id="renditionSummaryStatus">
                                    <span class="badge rounded-pill text-bg-warning">Borrador</span>
                                </dd>

                                <dt class="col-5 text-body-secondary">Período rendido</dt>
                                <dd class="col-7 mb-0" id="renditionSummaryPeriod">—</dd>

                                <dt class="col-5 text-body-secondary">Alcance</dt>
                                <dd class="col-7 mb-0 text-break" id="renditionSummaryScope">—</dd>

                                <dt class="col-5 text-body-secondary">Mantenimientos</dt>
                                <dd class="col-7 fw-semibold mb-0" id="renditionSummaryMaintenances">0</dd>

                                <dt class="col-5 text-body-secondary">Tickets asociados</dt>
                                <dd class="col-7 fw-semibold mb-0" id="renditionSummaryTickets">0</dd>

                                <dt class="col-5 text-body-secondary">Responsable</dt>
                                <dd class="col-7 mb-0" id="renditionSummaryPreparedBy">—</dd>

                                <dt class="col-5 text-body-secondary">Hash de unicidad</dt>
                                <dd class="col-7 font-monospace text-body-secondary mb-0 text-break" id="renditionSummaryHash">—</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7">
                        <div class="border rounded-3 p-3 h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h4 class="h6 text-body-secondary text-uppercase small mb-2">Vista previa de aprobación</h4>
                                <div id="renditionFinalPreview" class="border rounded p-2 bg-body-tertiary small mb-3 rendition-final-preview-scroll"></div>
                            </div>

                            <div class="border-top pt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="renditionConfirmApproval">
                                    <label class="form-check-label small fw-semibold" for="renditionConfirmApproval">
                                        Confirmo que he revisado y validado el contenido del Informe de Rendición y autorizo su aprobación y resguardo en la base de datos.
                                    </label>
                                </div>
                                <div class="small text-body-secondary mt-1">
                                    También puedes guardarlo como <strong>Borrador</strong> si deseas continuar editándolo más adelante.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="border-top p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">Cerrar</button>
            <div class="d-flex flex-wrap justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="renditionWizardBackBtn" hidden>
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Anterior
                </button>

                <button type="button" class="btn btn-primary btn-sm" id="renditionWizardGenerateBtn">
                    <i class="bi bi-magic me-1" id="renditionWizardGenerateIcon" aria-hidden="true"></i>
                    <span id="renditionWizardGenerateLabel">Generar informe con IA</span>
                </button>

                <button type="button" class="btn btn-outline-primary btn-sm" id="renditionSaveDraftBtn" hidden>
                    <i class="bi bi-floppy me-1" aria-hidden="true"></i>Guardar borrador
                </button>

                <button type="button" class="btn btn-primary btn-sm" id="renditionToStep3Btn" hidden>
                    Continuar a aprobación<i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                </button>

                <button type="button" class="btn btn-success btn-sm" id="renditionApproveSaveBtn" hidden>
                    <i class="bi bi-patch-check me-1" id="renditionApproveSaveIcon" aria-hidden="true"></i>
                    <span id="renditionApproveSaveLabel">Aprobar y guardar</span>
                </button>

                <a href="#" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm d-none" id="renditionOpenPrintBtn">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Vista imprimible / .md
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Drawer de Historial de Informes de Rendición (derecha a izquierda) -->
<div class="offcanvas offcanvas-end rendition-history-drawer text-start" tabindex="-1"
     id="renditionReportHistoryModal"
     aria-labelledby="renditionReportHistoryTitle">
    <div class="offcanvas-header border-bottom">
        <div>
            <h2 class="offcanvas-title h5 mb-1" id="renditionReportHistoryTitle">
                <i class="bi bi-collection text-primary me-2" aria-hidden="true"></i>Informes de Rendición guardados
            </h2>
            <p class="small text-body-secondary mb-0">
                Consulta informes aprobados o retoma borradores pendientes de aprobación.
            </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body">
        <div id="renditionHistoryStatus" class="small text-body-secondary mb-2" role="status" aria-live="polite">
            Cargando informes guardados…
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="renditionHistoryTable">
                <caption class="visually-hidden">Listado de Informes de Rendición guardados</caption>
                <thead class="table-light text-nowrap">
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Período</th>
                        <th scope="col">Alcance</th>
                        <th scope="col" class="text-center">Mant. / Tickets</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody id="renditionHistoryTableBody">
                </tbody>
            </table>
        </div>
    </div>
    <div class="border-top p-3 d-flex justify-content-end">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">Cerrar</button>
    </div>
</div>
