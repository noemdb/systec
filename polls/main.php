<div class="container border rounded pt-4 mt-4">
    <h5>Formulario de diagnóstico.</h5>
    
    <form class="text-start" action="procesar_formulario.php" method="POST">
        <div class="mb-3">
            <label for="codigo_equipo" class="form-label">Código del equipo a evaluar:</label>
            <input type="text" class="form-control" id="codigo_equipo" name="codigo_equipo" required>
        </div>
        <div class="mb-3">
            <label for="tipo_equipo" class="form-label">Tipo de equipo:</label>
            <select class="form-select" id="tipo_equipo" name="tipo_equipo" required>
                <option value="">Seleccionar tipo de equipo</option>
                <option value="computadora">Computadora</option>
                <option value="impresora">Impresora</option>
                <option value="raton">Ratón</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="frecuencia_uso" class="form-label">Frecuencia de uso:</label>
            <select class="form-select" id="frecuencia_uso" name="frecuencia_uso" required>
                <option value="">Seleccionar frecuencia de uso</option>
                <option value="todo_dia">Todo el día</option>
                <option value="medio_dia">Medio día</option>
                <option value="pocas_horas">Sólo unas pocas horas</option>
                <option value="pocos_minutos">Sólo unos minutos</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="estado_actual" class="form-label">Estado actual:</label>
            <select class="form-select" id="estado_actual" name="estado_actual" required>
                <option value="">Seleccionar estado actual</option>
                <option value="funciona_correctamente">Funciona correctamente</option>
                <option value="presenta_danos">Presenta daños</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="necesidades_usuarios" class="form-label">Necesidades que tienen los usuarios del equipo:</label>
            <textarea class="form-control" id="necesidades_usuarios" name="necesidades_usuarios" rows="4"></textarea>
        </div>
        <div class="mb-3">
            <label for="observaciones" class="form-label">Observaciones adicionales sobre el equipo:</label>
            <textarea class="form-control" id="observaciones" name="observaciones" rows="4"></textarea>
        </div>
        <div class="mb-3">
            <label for="recomendaciones" class="form-label">Recomendaciones específicas para el mantenimiento, reparación, actualización o sustitución del equipo:</label>
            <textarea class="form-control" id="recomendaciones" name="recomendaciones" rows="4"></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100">Enviar</button>
    </form>
</div>