<?php
/**
 * Fase 1.1 — Plantilla base reutilizable (layout)
 *
 * Uso desde cada página (patrón de buffering de salida):
 *   1) Definir title y abrir buffer con ob_start().
 *   2) Escribir el contenido principal (HTML/PHP) de la página.
 *   3) Cerrar buffer (ob_get_clean en $main) e incluir este layout.
 *
 * Variables admitidas (todas opcionales salvo title y main):
 *   title        string  Título de la página (cabecera y pestaña)
 *   main         string  Contenido principal capturado con ob_get_clean()
 *   no_footer    bool    No mostrar el footer estándar (historials, batch)
 *   header_file  string  Ruta de un header alternativo (ej. membrete)
 *   main_class   string  Clases extra para el elemento main
 */

require_once __DIR__ . '/auth.php';

if (auth_check() && auth_has_role(array('personal')) && !auth_is_personal_area()) {
    header('Location: ' . url('personal/index.php'));
    exit();
}
?>
<html lang="es" data-bs-theme="light">
<head>
    <?php include(__DIR__ . '/head.php'); ?>
</head>

<body class="d-flex text-center shadow-none">

    <?php include(__DIR__ . '/script.php'); ?>

    <div class="app-shell d-flex w-100 mx-auto flex-column">

        <header class="app-header bg-secondary-subtle border-bottom shadow-sm py-2 px-2">
            <?php if (!empty($header_file)) {
                echo '<div class="container-fluid">';
                include($header_file);
                echo '</div>';
            } else {
                include(__DIR__ . '/header.php');
            } ?>
        </header>

        <main class="app-main px-3 w-100 mt-2 <?= htmlspecialchars($main_class ?? '') ?>">
            <?= $main ?? '' ?>
        </main>

        <?php if (empty($no_footer)) : ?>
        <footer class="app-footer mt-auto text-white-50 bg-secondary-subtle py-2">
            <?php include(__DIR__ . '/footer.php'); ?>
        </footer>
        <?php endif; ?>

    </div>

</body>
</html>
