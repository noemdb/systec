document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.app-toast').forEach(function (toastElement) {
        bootstrap.Toast.getOrCreateInstance(toastElement).show();
    });
});
