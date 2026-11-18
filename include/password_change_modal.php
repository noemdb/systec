<?php if (auth_check()) : ?>
    <div class="modal fade" id="changePasswordModal" tabindex="-1"
         aria-labelledby="changePasswordModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content text-start">
                <form method="post" action="<?= e(url('personal/actions/password.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to"
                           value="<?= e(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : url('dashboard.php')) ?>">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="changePasswordModalTitle">
                                <i class="bi bi-key text-primary me-2" aria-hidden="true"></i>Cambiar contraseña
                            </h2>
                            <p class="small text-body-secondary mb-0">
                                Cuenta: <strong><?= e((string) (auth_user()['username'] ?? '')) ?></strong>
                            </p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="currentPasswordInput" class="form-label fw-semibold small">
                                Contraseña actual <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="password" class="form-control" id="currentPasswordInput"
                                   name="current_password" autocomplete="current-password" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPasswordInput" class="form-label fw-semibold small">
                                Nueva contraseña <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="password" class="form-control" id="newPasswordInput"
                                   name="new_password" minlength="6" autocomplete="new-password" required>
                            <div class="form-text">Mínimo 6 caracteres. Debe ser distinta a la actual.</div>
                        </div>
                        <div class="mb-0">
                            <label for="confirmPasswordInput" class="form-label fw-semibold small">
                                Confirmar nueva contraseña <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input type="password" class="form-control" id="confirmPasswordInput"
                                   name="confirm_password" minlength="6" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Actualizar contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
