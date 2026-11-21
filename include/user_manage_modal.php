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
                    <div class="alert alert-info py-2 small d-none" id="manageSelfNotice" role="note">
                        <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                        Estás editando tu propia cuenta en sesión: el rol de administrador y el estado activo están protegidos.
                    </div>
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
                        <div class="input-group">
                            <input type="password" class="form-control" id="managePassword" name="password"
                                   minlength="6" autocomplete="new-password" required>
                            <button type="button" class="btn btn-outline-secondary" id="toggleManagePassword"
                                    aria-label="Mostrar u ocultar contraseña" title="Mostrar u ocultar contraseña">
                                <i class="bi bi-eye" id="toggleManagePasswordIcon" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="generateManagePassword"
                                    aria-label="Generar contraseña segura" title="Generar contraseña aleatoria">
                                <i class="bi bi-key" aria-hidden="true"></i>
                            </button>
                        </div>
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
    (function () {
        const userManageModal = document.getElementById('userManageModal');
        if (!userManageModal) return;

        const form = document.getElementById('userManageForm');
        const idInput = document.getElementById('manageUserId');
        const usernameInput = document.getElementById('manageUsername');
        const roleSelect = document.getElementById('manageRole');
        const activeSelect = document.getElementById('manageActive');
        const passwordInput = document.getElementById('managePassword');
        const togglePasswordBtn = document.getElementById('toggleManagePassword');
        const togglePasswordIcon = document.getElementById('toggleManagePasswordIcon');
        const generatePasswordBtn = document.getElementById('generateManagePassword');
        const selfNotice = document.getElementById('manageSelfNotice');
        const titleText = document.getElementById('userManageModalTitleText');
        const subtitleText = document.getElementById('userManageModalSubtitle');
        const icon = document.getElementById('userManageModalIcon');
        const submitText = document.getElementById('userManageSubmitText');
        const passwordLabel = document.getElementById('managePasswordLabel');
        const passwordHelp = document.getElementById('managePasswordHelp');

        const setPasswordVisible = (visible) => {
            passwordInput.type = visible ? 'text' : 'password';
            if (togglePasswordIcon) {
                togglePasswordIcon.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        };

        const setSelfProtection = (isSelf) => {
            if (selfNotice) selfNotice.classList.toggle('d-none', !isSelf);
            Array.from(roleSelect.options).forEach((opt) => {
                opt.disabled = isSelf && opt.value !== roleSelect.value;
            });
            Array.from(activeSelect.options).forEach((opt) => {
                opt.disabled = isSelf && opt.value !== activeSelect.value;
            });
        };

        if (togglePasswordBtn) {
            togglePasswordBtn.addEventListener('click', () => {
                setPasswordVisible(passwordInput.type === 'password');
            });
        }

        if (generatePasswordBtn) {
            generatePasswordBtn.addEventListener('click', () => {
                const charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
                const bytes = new Uint32Array(12);
                window.crypto.getRandomValues(bytes);
                let generated = '';
                for (let i = 0; i < bytes.length; i++) {
                    generated += charset[bytes[i] % charset.length];
                }
                passwordInput.value = generated;
                setPasswordVisible(true);
                passwordInput.focus();
                passwordInput.select();
            });
        }

        userManageModal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            form.reset();
            setPasswordVisible(false);

            if (!trigger || !trigger.dataset.user) {
                idInput.value = '';
                usernameInput.readOnly = false;
                roleSelect.value = 'consulta';
                activeSelect.value = '1';
                setSelfProtection(false);
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
            setSelfProtection(Boolean(user.is_self));
            passwordInput.required = false;
            passwordInput.value = '';
            titleText.textContent = `Editar usuario: ${user.username}`;
            subtitleText.textContent = 'Actualiza el rol, estado o restablece la contraseña.';
            icon.className = 'bi bi-person-gear text-primary me-2';
            submitText.textContent = 'Guardar cambios';
            passwordLabel.textContent = 'Nueva contraseña (opcional)';
            passwordHelp.textContent = 'Déjala en blanco para conservar la contraseña actual (mínimo 6 caracteres si se cambia).';
        });

        userManageModal.addEventListener('hide.bs.modal', () => {
            if (userManageModal.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });
    })();
</script>
