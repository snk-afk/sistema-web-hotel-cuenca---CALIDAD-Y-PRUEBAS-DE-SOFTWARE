<?php declare(strict_types=1); ?>
<div id="modal-transfer" class="modal">
    <div class="modal-content">
        <button type="button" class="close" onclick="closeModal('modal-transfer')" aria-label="Cerrar transferencia">&times;</button>
        <h3>🔄 Transferencia de Habitación</h3>
        <form id="form-transfer" onsubmit="handleTransferSubmit(event)">
            <input type="hidden" id="transfer-from-room-id">
            
            <div class="form-group">
                <label for="transfer-from-room-number">Habitación Origen:</label>
                <input type="text" id="transfer-from-room-number" readonly class="form-control">
            </div>

            <div class="form-group">
                <label for="transfer-to-room-id">Habitación Destino (Solo Libres):</label>
                <select id="transfer-to-room-id" class="form-control">
                    <!-- Populated dynamically -->
                </select>
            </div>

            <div class="form-group">
                <label for="transfer-reason">Motivo del Cambio:</label>
                <input type="text" id="transfer-reason" class="form-control" required placeholder="Ej: Aire acondicionado averiado">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-warning">🔄 Confirmar Transferencia</button>
                <button type="button" class="btn btn-light" onclick="closeModal('modal-transfer')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

