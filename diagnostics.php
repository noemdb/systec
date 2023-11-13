<html lang="en" class="h-100" data-bs-theme="light">

<?php include('conn.php'); ?>

<head>

<?php include('diagnostics/include/head.php'); ?>
    
</head>

<body class="d-flex h-100 text-center shadow-none">

    <div class="d-flex w-100 h-100 p-3 mx-auto flex-column">

        <header class="mb-auto" >
            <?php include('diagnostics/include/header.php'); ?> 
        </header>

        <div class="h4">Instrumento de recolección de datos</div>              

        <main class="px-3">
            <?php include('diagnostics/main.php'); ?>
        </main>

        <footer class="mt-auto">
            <?php include('diagnostics/include/footer.php'); ?>
        </footer>

    </div>    

    <?php include('script.php'); ?>

</body>

</html>