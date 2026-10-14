<?php declare(strict_types=1); ?>
<div id="modal-checkout" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('modal-checkout')">&times;</span>
        <h3>🔑 Checkout y Cobro de Habitación</h3>
        <form id="form-checkout" onsubmit="handleCheckoutSubmit(event)">
            <input type="hidden" id="checkout-room-id" name="room_id">
            
            <div class="form-group">
                <label>Habitación:</label>
                <input type="text" id="checkout-room-number" readonly class="form-control">
            </div>

            <div class="form-group">
                <label>Total Pendiente a Cobrar (S/):</label>
                <input type="text" id="checkout-pending-balance" readonly class="form-control highlight-balance">
            </div>

            <div class="form-group">
                <label>Comprobante de Pago:</label>
                <select id="checkout-doc-type" class="form-control">
                    <option value="BOLETA">Boleta Electrónica (B001)</option>
                    <option value="FACTURA">Factura Electrónica (F001)</option>
                    <option value="NOTA_VENTA">Nota de Venta / Ticket (NV01)</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">🧾 Cobrar y Hacer Checkout</button>
                <button type="button" class="btn btn-light" onclick="closeModal('modal-checkout')">Cancelar</button>
            </div>
        </form>
    </div>
</div>
