<?php
$ticketRegisterOptions = isset($ticketRegisterOptions) && is_array($ticketRegisterOptions)
    ? $ticketRegisterOptions
    : array();
$registerTicketSelectedPropertyId = isset($ticketRegisterOptions['selected_property_id'])
    && is_string($ticketRegisterOptions['selected_property_id'])
        ? $ticketRegisterOptions['selected_property_id']
        : '';
$registerTicketError = isset($ticketRegisterOptions['error']) && is_string($ticketRegisterOptions['error'])
    ? $ticketRegisterOptions['error']
    : '';
$registerTicketValues = isset($ticketRegisterOptions['values']) && is_array($ticketRegisterOptions['values'])
    ? $ticketRegisterOptions['values']
    : array();
$registerTicketPropertiesPath = isset($ticketRegisterOptions['properties_url'])
    && is_string($ticketRegisterOptions['properties_url'])
        ? $ticketRegisterOptions['properties_url']
        : 'personal/actions/properties.php';
$registerTicketFormPath = isset($ticketRegisterOptions['form_action']) && is_string($ticketRegisterOptions['form_action'])
    ? $ticketRegisterOptions['form_action']
    : 'personal/actions/register.php';
?>
<div class="modal fade ticket-register-modal" id="ticketRegisterModal" tabindex="-1"
     data-properties-url="<?= e(url($registerTicketPropertiesPath)) ?>"
     data-selected-property-id="<?= e($registerTicketSelectedPropertyId) ?>"
     data-open-after-redirect="<?= $registerTicketError !== '' ? 'true' : 'false' ?>"
     aria-labelledby="ticketRegisterHeading" aria-describedby="ticketRegisterHelp" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-start">
            <form method="post" action="<?= e(url($registerTicketFormPath)) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="ticketRegisterHeading">Registrar ticket</h2>
                        <p class="small text-body-secondary mb-0" id="ticketRegisterHelp">Describe la incidencia y selecciona el bien afectado.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <ol class="personal-ticket-wizard-progress list-unstyled mb-4" aria-label="Progreso del registro">
                        <li data-wizard-indicator="1" aria-current="step">
                            <span class="personal-ticket-wizard-number">1</span>
                            <span>Seleccionar bien</span>
                        </li>
                        <li data-wizard-indicator="2">
                            <span class="personal-ticket-wizard-number">2</span>
                            <span>Detalles</span>
                        </li>
                        <li data-wizard-indicator="3">
                            <span class="personal-ticket-wizard-number">3</span>
                            <span>Resumen</span>
                        </li>
                    </ol>
                    <p class="visually-hidden" id="ticketRegisterWizardStatus" aria-live="polite">Paso 1 de 3: Seleccionar bien</p>
                    <?php if ($registerTicketError !== '') : ?>
                        <div class="alert alert-danger" role="alert"><?= e($registerTicketError) ?></div>
                    <?php endif; ?>
                    <div data-wizard-step="1" role="group" aria-labelledby="ticketRegisterPropertyLabel">
                        <section class="mb-4 property-picker" aria-labelledby="ticketRegisterPropertyLabel">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-1 mb-3">
                                <div>
                                    <h3 class="h6 mb-1" id="ticketRegisterPropertyLabel" tabindex="-1">
                                        Bien asociado <span class="text-danger" aria-hidden="true">*</span>
                                    </h3>
                                    <p class="form-text mb-0">Filtra el inventario y selecciona el bien donde ocurre la incidencia.</p>
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-12 col-md-6">
                                    <label for="ticketRegisterPropertyDirection" class="form-label fw-semibold small">Dirección de dependencia</label>
                                    <select id="ticketRegisterPropertyDirection" class="form-select form-select-sm"
                                            data-property-picker-direction>
                                        <option value="">Todas las direcciones</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="ticketRegisterPropertyCategory" class="form-label fw-semibold small">Categoría del bien</label>
                                    <select id="ticketRegisterPropertyCategory" class="form-select form-select-sm"
                                            data-property-picker-category>
                                        <option value="">Todas las categorías</option>
                                        <option value="informatica">Informática</option>
                                        <option value="redes">Redes y telecomunicaciones</option>
                                        <option value="otros">Otros bienes</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="ticketRegisterPropertySearch" class="form-label fw-semibold small">Buscar bien por código o descripción</label>
                                    <input type="search" class="form-control form-control-sm"
                                           id="ticketRegisterPropertySearch" data-property-picker-search="ticketRegisterPropertyId"
                                           placeholder="Ej.: 2-0101, laptop, impresora" autocomplete="off">
                                </div>
                            </div>
                            <div class="form-text mb-2" id="ticketRegisterPropertyMatches" role="status" aria-live="polite" aria-atomic="true">
                                Los bienes se cargarán al abrir este selector.
                            </div>
                            <div id="ticketRegisterPropertySourceNotice" class="small mb-2" role="status" aria-live="polite"></div>
                            <div class="alert alert-info py-2 d-none" id="ticketRegisterPropertySelection" role="status" aria-live="polite">
                                <span id="ticketRegisterPropertySelectionText"></span>
                                <button type="button" class="btn btn-sm btn-link p-0 ms-2" id="ticketRegisterPropertyClear">Cambiar bien</button>
                            </div>

                            <fieldset class="border-0 p-0 m-0">
                                <div class="list-group property-picker-results" id="ticketRegisterPropertyId"
                                     role="radiogroup" aria-labelledby="ticketRegisterPropertyLabel"
                                     data-property-picker data-search-input="ticketRegisterPropertySearch"
                                     data-result-count="ticketRegisterPropertyMatches"
                                     data-category-filter="ticketRegisterPropertyCategory"
                                     data-direction-filter="ticketRegisterPropertyDirection"
                                     data-preserve-selection
                                     data-validation-message="ticketRegisterPropertyError">
                                </div>
                                <div class="invalid-feedback" id="ticketRegisterPropertyError" role="alert">
                                    Selecciona un bien de la lista para registrar el ticket.
                                </div>
                            </fieldset>
                        </section>
                    </div>
                    <section data-wizard-step="2" aria-labelledby="ticketRegisterDetailsLabel" hidden>
                        <h3 class="h6 mb-3" id="ticketRegisterDetailsLabel" tabindex="-1">Describe la incidencia</h3>
                        <div class="mb-3">
                            <label for="ticketRegisterTitleInput" class="form-label fw-semibold">Título</label>
                            <input type="text" class="form-control" id="ticketRegisterTitleInput" name="title"
                                   maxlength="255" value="<?= e((string) ($registerTicketValues['title'] ?? '')) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="ticketRegisterDescriptionInput" class="form-label fw-semibold">Descripción</label>
                            <textarea class="form-control" id="ticketRegisterDescriptionInput" name="description" rows="4"
                                      maxlength="5000" required><?= e((string) ($registerTicketValues['description'] ?? '')) ?></textarea>
                        </div>
                        <div>
                            <label for="ticketRegisterObservationInput" class="form-label fw-semibold">Observación <span class="text-body-secondary fw-normal">(opcional)</span></label>
                            <textarea class="form-control" id="ticketRegisterObservationInput" name="observation" rows="3"
                                      maxlength="5000"><?= e((string) ($registerTicketValues['observation'] ?? '')) ?></textarea>
                        </div>
                    </section>
                    <section data-wizard-step="3" aria-labelledby="ticketRegisterSummaryLabel" hidden>
                        <h3 class="h6 mb-3" id="ticketRegisterSummaryLabel" tabindex="-1">Revisa la información antes de guardar</h3>
                        <div class="card bg-body-tertiary">
                            <div class="card-body">
                                <dl class="personal-ticket-wizard-summary mb-0">
                                    <dt>Bien</dt>
                                    <dd id="ticketRegisterSummaryProperty" class="mb-3">Sin seleccionar</dd>
                                    <dt>Título</dt>
                                    <dd id="ticketRegisterSummaryTitle" class="mb-3">—</dd>
                                    <dt>Descripción</dt>
                                    <dd id="ticketRegisterSummaryDescription" class="mb-3">—</dd>
                                    <dt>Observación</dt>
                                    <dd id="ticketRegisterSummaryObservation" class="mb-0">Sin observación</dd>
                                </dl>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <div class="d-flex flex-wrap justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-primary" id="ticketRegisterWizardBack" hidden>
                            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Anterior
                        </button>
                        <button type="button" class="btn btn-primary" id="ticketRegisterWizardNext">
                            Continuar<i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                        </button>
                        <button type="submit" class="btn btn-primary" id="ticketRegisterWizardSubmit" hidden>
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Guardar ticket
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?= e(url('assets/js/property-picker.js')) ?>"></script>
<script src="<?= e(url('assets/js/ticket-register-modal.js')) ?>"></script>
