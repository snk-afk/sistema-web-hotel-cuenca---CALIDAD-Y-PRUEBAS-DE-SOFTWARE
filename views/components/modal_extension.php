<?php declare(strict_types=1); ?>
<div id="modal-extension" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('modal-extension')">&times;</span>
        <h3>⏳ Extensión de Horas de Hospedaje</h3>
        <form id="form-extension" onsubmit="handleExtensionSubmit(event)">
            <input type="hidden" id="extension-room-id">
            
            <div class="form-group">
                <label>Habitación:</label>
                <input type="text" id="extension-room-number" readonly class="form-control">
            </div>

            <div class="form-group">
                <label>Horas Adicionales:</label>
                <input type="number" id="extension-added-hours" class="form-control" value="2" min="1">
            </div>

            <div class="form-group">
                <label>Precio Adicional (S/):</label>
                <input type="number" step="0.50" id="extension-added-price" class="form-control" value="10.00">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-info">⏱️ Adicionar Horas</button>
                <button type="button" class="btn btn-light" onclick="closeModal('modal-extension')">Cancelar</button>
            </div>
        </form>
    </div>
</div>
