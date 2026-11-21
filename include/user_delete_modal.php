<div class="modal fade" id="deleteUserModal" tabindex="-1"
     aria-labelledby="deleteUserModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content text-start border-danger">
            <form method="post" action="<?= e(url('users/actions/save.php')) ?>" id="deleteUserForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId" value="">

                <div class="modal-header bg-danger-subtle">
                    <div>
                        <h2 class="modal-title fs-5 text-danger-emphasis" id="deleteUserModalTitle">
                            <i class="bi bi-exclamation-octagon-fill me-2" aria-hidden="true"></i>
                            Eliminar usuario permanentemente
                        </h2>
                        <p class="small text-body-secondary mb-0" id="deleteUserStepLabel">
                            Paso 1 de 2 — Revisa los riesgos y consecuencias
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div id="deleteUserStep1">
                        <p class="mb-2">
                            Estás a punto de eliminar la cuenta
                            <strong id="deleteUserNameStep1"></strong>
                            (<span id="deleteUserRoleStep1" class="fw-semibold"></span>).
                        </p>

                        <div class="alert alert-danger py-2 small mb-3" role="alert">
                            <strong>Riesgos y consecuencias de esta acción:</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                <li><strong>Eliminación irreversible:</strong> la cuenta y sus credenciales se borrarán de la base de datos y no podrán recuperarse.</li>
                                <li><strong>Pérdida inmediata de acceso:</strong> el usuario dejará de poder iniciar sesión en el sistema.</li>
                                <li><strong>Impacto en trazabilidad histórica:</strong> en los tickets y seguimientos donde participó esta cuenta dejará de mostrarse su nombre de usuario (aparecerá como «Usuario»).</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning py-2 small mb-3 d-none" id="deleteUserImpactBox" role="status">
                            <i class="bi bi-info-circle-fill me-1" aria-hidden="true"></i>
                            <span id="deleteUserImpactText"></span>
                        </div>

                        <div class="alert alert-secondary py-2 small mb-0" role="note">
                            <i class="bi bi-lightbulb me-1" aria-hidden="true"></i>
                            <strong>Alternativa recomendada:</strong> si solo necesitas revocar el acceso conservando la auditoría completa, cancela y utiliza el botón <strong>Desactivar</strong>.
                        </div>
                    </div>

                    <div id="deleteUserStep2" class="d-none">
                        <div class="alert alert-danger py-2 small mb-3" role="alert">
                            <i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>
                            <strong>Segunda confirmación obligatoria:</strong> esta acción eliminará permanentemente a
                            <strong id="deleteUserNameStep2"></strong>.
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="deleteUserConfirmRisk"
                                   name="confirm_risk" value="1">
                            <label class="form-check-label small" for="deleteUserConfirmRisk">
                                Comprendo que esta acción es <strong>permanente e irreversible</strong> y acepto el impacto en la trazabilidad histórica.
                            </label>
                        </div>

                        <div class="mb-0">
                            <label for="deleteUserConfirmUsername" class="form-label fw-semibold small">
                                Escribe <code id="deleteUserExpectedCode"></code> para confirmar la eliminación
                            </label>
                            <input type="text" class="form-control" id="deleteUserConfirmUsername"
                                   name="confirm_username" autocomplete="off" spellcheck="false"
                                   placeholder="Escribe el nombre de usuario exacto">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="deleteUserBackBtn">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver
                    </button>
                    <button type="button" class="btn btn-warning" id="deleteUserContinueBtn">
                        Entiendo los riesgos, continuar <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                    </button>
                    <button type="submit" class="btn btn-danger d-none" id="deleteUserSubmitBtn" disabled>
                        <i class="bi bi-trash-fill me-1" aria-hidden="true"></i>Eliminar definitivamente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    (function () {
        const modalEl = document.getElementById('deleteUserModal');
        if (!modalEl) return;

        const form = document.getElementById('deleteUserForm');
        const idInput = document.getElementById('deleteUserId');
        const stepLabel = document.getElementById('deleteUserStepLabel');
        const step1 = document.getElementById('deleteUserStep1');
        const step2 = document.getElementById('deleteUserStep2');
        const nameStep1 = document.getElementById('deleteUserNameStep1');
        const roleStep1 = document.getElementById('deleteUserRoleStep1');
        const nameStep2 = document.getElementById('deleteUserNameStep2');
        const expectedCode = document.getElementById('deleteUserExpectedCode');
        const impactBox = document.getElementById('deleteUserImpactBox');
        const impactText = document.getElementById('deleteUserImpactText');
        const riskCheckbox = document.getElementById('deleteUserConfirmRisk');
        const confirmInput = document.getElementById('deleteUserConfirmUsername');
        const continueBtn = document.getElementById('deleteUserContinueBtn');
        const backBtn = document.getElementById('deleteUserBackBtn');
        const submitBtn = document.getElementById('deleteUserSubmitBtn');

        let expectedUsername = '';

        const updateSubmitState = () => {
            const matches = confirmInput.value.trim() === expectedUsername && expectedUsername !== '';
            submitBtn.disabled = !(riskCheckbox.checked && matches);
        };

        const showStep = (stepNumber) => {
            const isStep2 = stepNumber === 2;
            step1.classList.toggle('d-none', isStep2);
            step2.classList.toggle('d-none', !isStep2);
            continueBtn.classList.toggle('d-none', isStep2);
            backBtn.classList.toggle('d-none', !isStep2);
            submitBtn.classList.toggle('d-none', !isStep2);
            stepLabel.textContent = isStep2
                ? 'Paso 2 de 2 — Confirmación final irreversible'
                : 'Paso 1 de 2 — Revisa los riesgos y consecuencias';
            if (isStep2) {
                updateSubmitState();
                riskCheckbox.focus();
            }
        };

        continueBtn.addEventListener('click', () => showStep(2));
        backBtn.addEventListener('click', () => showStep(1));
        riskCheckbox.addEventListener('change', updateSubmitState);
        confirmInput.addEventListener('input', updateSubmitState);

        modalEl.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            form.reset();
            expectedUsername = trigger.dataset.username || '';
            const roleLabel = trigger.dataset.roleLabel || '';
            const ticketsCount = Number(trigger.dataset.ticketsCount || 0);
            const historiesCount = Number(trigger.dataset.historiesCount || 0);

            idInput.value = trigger.dataset.userId || '';
            nameStep1.textContent = expectedUsername;
            roleStep1.textContent = roleLabel;
            nameStep2.textContent = expectedUsername;
            expectedCode.textContent = expectedUsername;

            if (ticketsCount > 0 || historiesCount > 0) {
                const parts = [];
                if (ticketsCount > 0) {
                    parts.push(`${ticketsCount} ${ticketsCount === 1 ? 'ticket creado' : 'tickets creados'}`);
                }
                if (historiesCount > 0) {
                    parts.push(`${historiesCount} ${historiesCount === 1 ? 'registro de seguimiento' : 'registros de seguimiento'}`);
                }
                impactText.textContent = `Esta cuenta tiene asociados ${parts.join(' y ')} en el historial del sistema.`;
                impactBox.classList.remove('d-none');
            } else {
                impactBox.classList.add('d-none');
            }

            showStep(1);
        });

        modalEl.addEventListener('hide.bs.modal', () => {
            if (modalEl.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });
    })();
</script>
