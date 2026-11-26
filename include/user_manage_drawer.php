<div class="offcanvas offcanvas-end user-manage-drawer" id="userManageDrawer" tabindex="-1"
     aria-labelledby="userManageDrawerTitle" aria-hidden="true">
    <form method="post" action="<?= e(url('users/actions/save.php')) ?>" id="userManageForm"
          class="d-flex flex-column h-100 text-start user-manage-drawer-form">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" id="manageUserId" value="">

        <div class="offcanvas-header border-bottom">
            <div>
                <h2 class="h5 mb-1" id="userManageDrawerTitle">
                    <i class="bi bi-person-plus text-primary me-2" id="userManageDrawerIcon" aria-hidden="true"></i>
                    <span id="userManageDrawerTitleText">Nuevo usuario</span>
                </h2>
                <p class="small text-body-secondary mb-0" id="userManageDrawerSubtitle" aria-live="polite">
                    Registra una nueva cuenta de acceso en el sistema.
                </p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>

        <div class="offcanvas-body">
            <div class="alert alert-info py-2 small d-none" id="manageSelfNotice" role="note">
                <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                Estás editando tu propia cuenta en sesión: el rol de administrador y el estado activo están protegidos.
            </div>

            <section class="mb-4" aria-labelledby="manageAccountSectionTitle">
                <h3 class="h6 text-body-secondary mb-3" id="manageAccountSectionTitle">Cuenta y acceso</h3>

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

                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label for="manageRole" class="form-label fw-semibold small">
                            Rol <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select" id="manageRole" name="role" required>
                            <?php foreach ($roles as $roleKey => $roleLabel) : ?>
                                <option value="<?= e($roleKey) ?>"><?= e($roleLabel) ?> (<?= e($roleKey) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="manageActive" class="form-label fw-semibold small">
                            Estado <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select" id="manageActive" name="active" required>
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="form-text">
                    Los usuarios activos con rol <strong>Técnico</strong> aparecen automáticamente en los selectores de mantenimiento.
                </div>

                <div class="mt-3">
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
                    <div class="form-text" id="managePasswordHelp">Mínimo 6 caracteres.</div>
                </div>
            </section>

            <section aria-labelledby="manageProfileSectionTitle">
                <h3 class="h6 text-body-secondary mb-3" id="manageProfileSectionTitle">Perfil</h3>

                <div class="d-flex align-items-center gap-3 mb-3 p-3 border rounded bg-body-tertiary">
                    <img id="manageAvatarPreview" alt="Vista previa del avatar"
                         class="rounded-circle border user-avatar-preview d-none" width="72" height="72"
                         referrerpolicy="no-referrer">
                    <span id="manageAvatarFallback"
                          class="rounded-circle border bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center user-avatar-preview"
                          aria-hidden="true">
                        <i class="bi bi-person fs-4"></i>
                    </span>
                    <div class="user-profile-text">
                        <span class="fw-semibold d-block">Imagen de perfil</span>
                        <span class="small text-body-secondary">Opcional. Se mostrará en el listado de usuarios.</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label for="manageFirstName" class="form-label fw-semibold small">Nombre</label>
                        <input type="text" class="form-control" id="manageFirstName" name="first_name"
                               maxlength="120" autocomplete="given-name" placeholder="Ej.: Noe">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="manageLastName" class="form-label fw-semibold small">Apellidos</label>
                        <input type="text" class="form-control" id="manageLastName" name="last_name"
                               maxlength="120" autocomplete="family-name" placeholder="Ej.: Dominguez Brito">
                    </div>
                </div>
                <div class="form-text mb-3">Completa ambos campos para crear o actualizar el perfil.</div>

                <div class="mb-0">
                    <label for="manageAvatarUrl" class="form-label fw-semibold small">URL del avatar</label>
                    <input type="url" class="form-control" id="manageAvatarUrl" name="avatar_url"
                           maxlength="2048" inputmode="url" placeholder="https://cmsf.gob.ve/avatars/noe.png">
                    <div class="form-text">Debe comenzar con http:// o https://. Déjala vacía para quitar la imagen.</div>
                </div>
            </section>
        </div>

        <div class="border-top p-3 d-flex flex-wrap justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cancelar</button>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1" aria-hidden="true"></i>
                <span id="userManageSubmitText">Crear usuario</span>
            </button>
        </div>
    </form>
</div>
<script nonce="<?= e(systec_csp_nonce()) ?>">
    (function () {
        const drawer = document.getElementById('userManageDrawer');
        if (!drawer) return;

        const form = document.getElementById('userManageForm');
        const idInput = document.getElementById('manageUserId');
        const usernameInput = document.getElementById('manageUsername');
        const roleSelect = document.getElementById('manageRole');
        const activeSelect = document.getElementById('manageActive');
        const passwordInput = document.getElementById('managePassword');
        const togglePasswordBtn = document.getElementById('toggleManagePassword');
        const togglePasswordIcon = document.getElementById('toggleManagePasswordIcon');
        const generatePasswordBtn = document.getElementById('generateManagePassword');
        const firstNameInput = document.getElementById('manageFirstName');
        const lastNameInput = document.getElementById('manageLastName');
        const avatarUrlInput = document.getElementById('manageAvatarUrl');
        const avatarPreview = document.getElementById('manageAvatarPreview');
        const avatarFallback = document.getElementById('manageAvatarFallback');
        const selfNotice = document.getElementById('manageSelfNotice');
        const titleText = document.getElementById('userManageDrawerTitleText');
        const subtitleText = document.getElementById('userManageDrawerSubtitle');
        const icon = document.getElementById('userManageDrawerIcon');
        const submitText = document.getElementById('userManageSubmitText');
        const passwordLabel = document.getElementById('managePasswordLabel');
        const passwordHelp = document.getElementById('managePasswordHelp');

        const setPasswordVisible = (visible) => {
            passwordInput.type = visible ? 'text' : 'password';
            if (togglePasswordIcon) {
                togglePasswordIcon.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        };

        const setAvatarPreview = (value) => {
            const url = String(value || '').trim();
            let isValid = false;
            if (url) {
                try {
                    const parsed = new URL(url);
                    isValid = parsed.protocol === 'http:' || parsed.protocol === 'https:';
                } catch (error) {
                    isValid = false;
                }
            }

            avatarPreview.classList.toggle('d-none', !isValid);
            avatarFallback.classList.toggle('d-none', isValid);
            if (isValid) {
                avatarPreview.src = url;
            } else {
                avatarPreview.removeAttribute('src');
            }
        };

        const setSelfProtection = (isSelf) => {
            selfNotice.classList.toggle('d-none', !isSelf);
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

        avatarUrlInput.addEventListener('input', () => setAvatarPreview(avatarUrlInput.value));
        avatarPreview.addEventListener('error', () => {
            avatarPreview.classList.add('d-none');
            avatarFallback.classList.remove('d-none');
        });

        drawer.addEventListener('show.bs.offcanvas', (event) => {
            const trigger = event.relatedTarget;
            form.reset();
            setPasswordVisible(false);

            if (!trigger || !trigger.dataset.user) {
                idInput.value = '';
                usernameInput.readOnly = false;
                roleSelect.value = 'consulta';
                activeSelect.value = '1';
                firstNameInput.value = '';
                lastNameInput.value = '';
                avatarUrlInput.value = '';
                setAvatarPreview('');
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
            firstNameInput.value = user.first_name || '';
            lastNameInput.value = user.last_name || '';
            avatarUrlInput.value = user.avatar_url || '';
            setAvatarPreview(user.avatar_url || '');
            setSelfProtection(Boolean(user.is_self));
            passwordInput.required = false;
            passwordInput.value = '';
            titleText.textContent = `Editar usuario: ${user.username}`;
            subtitleText.textContent = 'Actualiza la cuenta, el perfil o restablece la contraseña.';
            icon.className = 'bi bi-person-gear text-primary me-2';
            submitText.textContent = 'Guardar cambios';
            passwordLabel.textContent = 'Nueva contraseña (opcional)';
            passwordHelp.textContent = 'Déjala en blanco para conservar la contraseña actual (mínimo 6 caracteres si se cambia).';
        });

        drawer.addEventListener('hidden.bs.offcanvas', () => {
            if (drawer.contains(document.activeElement)) {
                document.activeElement.blur();
            }
            avatarPreview.removeAttribute('src');
        });
    })();
</script>
