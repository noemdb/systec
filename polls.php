<html lang="en" class="h-100" data-bs-theme="light">

<?php session_start(); ?>

<head>

<?php include('polls/include/head.php'); ?>
    
</head>

<body class="d-flex h-100 text-center shadow-none">

    <div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">

        <header class="mb-auto" >
            <?php include('polls/include/header.php'); ?> 
        </header>

        <div class="h4">Instrumento de colección de datos</div>              

        <main class="px-3">
            <?php include('polls/main.php'); ?>
        </main>

        <footer class="mt-auto">
            <?php include('polls/include/footer.php'); ?>
        </footer>

    </div>    

    <?php include('script.php'); ?>

</body>

</html>