<?php declare(strict_types=1); ?>
<div id="modal-checkin" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('modal-checkin')">&times;</span>
        <h3>🏨 Hospedar Habitación (Check-In Express)</h3>
        <form id="form-checkin" onsubmit="handleCheckinSubmit(event)">
            <input type="hidden" id="checkin-room-id" name="room_id">
            
            <div class="form-group">
                <label>Número de Habitación:</label>
                <input type="text" id="checkin-room-number" readonly class="form-control">
            </div>

            <div class="form-group">
                <label>Documento Huésped (DNI / RUC):</label>
                <div class="input-group">
                    <input type="text" id="checkin-doc-number" class="form-control" placeholder="Ej: 72819283">
                    <button type="button" class="btn btn-secondary" onclick="lookupGuestDocument()">🔍 Buscar (JSONPE)</button>
                </div>
            </div>

            <div class="form-group">
                <label>Nombre Completo / Razón Social:</label>
                <input type="text" id="checkin-guest-name" class="form-control" placeholder="Nombre de huésped">
            </div>

            <div class="form-group">
                <label>Tarifa:</label>
                <select id="checkin-tariff-id" class="form-control">
                    <option value="1">12 Horas (S/ 45.00)</option>
                    <option value="2">24 Horas / Día Completo (S/ 80.00)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Precio Aplicado (S/):</label>
                <input type="number" step="0.50" id="checkin-applied-price" class="form-control" value="45.00">
            </div>

            <div class="form-group">
                <label>Motivo de Descuento (Si aplica):</label>
                <input type="text" id="checkin-discount-reason" class="form-control" placeholder="Opcional">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">✅ Registrar Check-In</button>
                <button type="button" class="btn btn-light" onclick="closeModal('modal-checkin')">Cancelar</button>
            </div>
        </form>
    </div>
</div>
