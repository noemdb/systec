<div class="modal fade" id="userManageModal" tabindex="-1"
     aria-labelledby="userManageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content text-start">
            <form method="post" action="<?= e(url('users/actions/save.php')) ?>" id="userManageForm">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" id="manageUserId" value="">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="userManageModalTitle">
                            <i class="bi bi-person-plus text-primary me-2" id="userManageModalIcon" aria-hidden="true"></i>
                            <span id="userManageModalTitleText">Nuevo usuario</span>
                        </h2>
                        <p class="small text-body-secondary mb-0" id="userManageModalSubtitle">
                            Registra una nueva cuenta de acceso en el sistema.
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="manageUsername" class="form-label fw-semibold small">
                            Nombre de usuario <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input type="text" class="form-control" id="manageUsername" name="username"
                               pattern="[a-zA-Z0-9._-]{3,64}" minlength="3" maxlength="64" required
                               placeholder="Ej.: jmedina, operador02">
                        <div class="form-text" id="manageUsernameHelp">
                            De 3 a 64 caracteres alfanuméricos, punto, guion o guion bajo.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="manageRole" class="form-label fw-semibold small">
                            Rol <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select" id="manageRole" name="role" required>
                            <?php foreach ($roles as $roleKey => $roleLabel) : ?>
                                <option value="<?= e($roleKey) ?>"><?= e($roleLabel) ?> (<?= e($roleKey) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Los usuarios activos con rol <strong>Técnico</strong> aparecen automáticamente en los selectores de mantenimiento.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="manageActive" class="form-label fw-semibold small">
                            Estado de la cuenta <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select" id="manageActive" name="active" required>
                            <option value="1">Activo (puede iniciar sesión)</option>
                            <option value="0">Inactivo (acceso suspendido)</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label for="managePassword" class="form-label fw-semibold small" id="managePasswordLabel">
                            Contraseña <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input type="password" class="form-control" id="managePassword" name="password"
                               minlength="6" autocomplete="new-password" required>
                        <div class="form-text" id="managePasswordHelp">
                            Mínimo 6 caracteres.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1" aria-hidden="true"></i>
                        <span id="userManageSubmitText">Crear usuario</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    const userManageModal = document.getElementById('userManageModal');
    if (userManageModal) {
        userManageModal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            const form = document.getElementById('userManageForm');
            const idInput = document.getElementById('manageUserId');
            const usernameInput = document.getElementById('manageUsername');
            const roleSelect = document.getElementById('manageRole');
            const activeSelect = document.getElementById('manageActive');
            const passwordInput = document.getElementById('managePassword');
            const titleText = document.getElementById('userManageModalTitleText');
            const subtitleText = document.getElementById('userManageModalSubtitle');
            const icon = document.getElementById('userManageModalIcon');
            const submitText = document.getElementById('userManageSubmitText');
            const passwordLabel = document.getElementById('managePasswordLabel');
            const passwordHelp = document.getElementById('managePasswordHelp');

            form.reset();
            if (!trigger || !trigger.dataset.user) {
                idInput.value = '';
                usernameInput.readOnly = false;
                roleSelect.value = 'consulta';
                activeSelect.value = '1';
                passwordInput.required = true;
                titleText.textContent = 'Nuevo usuario';
                subtitleText.textContent = 'Registra una nueva cuenta de acceso en el sistema.';
                icon.className = 'bi bi-person-plus text-primary me-2';
                submitText.textContent = 'Crear usuario';
                passwordLabel.innerHTML = 'Contraseña <span class="text-danger" aria-hidden="true">*</span>';
                passwordHelp.textContent = 'Mínimo 6 caracteres.';
                return;
            }

            const user = JSON.parse(trigger.dataset.user);
            idInput.value = String(user.id);
            usernameInput.value = user.username;
            usernameInput.readOnly = true;
            roleSelect.value = user.role;
            activeSelect.value = String(user.active);
            passwordInput.required = false;
            passwordInput.value = '';
            titleText.textContent = `Editar usuario: ${user.username}`;
            subtitleText.textContent = 'Actualiza el rol, estado o restablece la contraseña.';
            icon.className = 'bi bi-person-gear text-primary me-2';
            submitText.textContent = 'Guardar cambios';
            passwordLabel.textContent = 'Nueva contraseña (opcional)';
            passwordHelp.textContent = 'Déjala en blanco para conservar la contraseña actual (mínimo 6 caracteres si se cambia).';
        });
    }
</script>
