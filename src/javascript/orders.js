function toggleForm() {
    const form = document.getElementById('addOrderForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function addProduct() {
    const productList = document.getElementById('product-list');
    const entry = productList.firstElementChild.cloneNode(true);
    entry.querySelector('select').value = '';
    entry.querySelector('input[name="quantities[]"]').value = '';
    entry.querySelector('input[name="purchase_prices[]"]').value = '';
    entry.querySelector('input[name="sale_prices[]"]').value = '';
    productList.appendChild(entry);
}

function removeProduct(button) {
    const entries = document.querySelectorAll('.product-entry');
    if (entries.length > 1) {
        button.parentElement.remove();
    }
}


function toggleFormFields() {
    const formMode = document.getElementById('form_mode').value;
    const updateFields = document.getElementById('update_order_fields');
    const orderFields = document.getElementById('order_fields');
    const orderDetailsDiv = document.getElementById('order_details');

    if (formMode === 'add') {
        updateFields.style.display = 'none';
        orderFields.style.display = 'block';
        orderDetailsDiv.style.display = 'none';
        document.getElementById('existing_order_id').required = false;
        document.getElementById('order_date').required = true;
        document.getElementById('city').required = true;
        document.getElementById('existing_order_id').value = '';
        document.getElementById('order_date').value = '';
        document.getElementById('delivery_date').value = '';
        document.getElementById('order_notes').value = '';
        document.getElementById('city').value = '';
    } else {
        updateFields.style.display = 'block';
        orderFields.style.display = 'block';
        document.getElementById('existing_order_id').required = true;
        document.getElementById('order_date').required = true;
        document.getElementById('city').required = true;
        displayOrderDetails();
    }
}

function displayOrderDetails() {
    const orderId = document.getElementById('existing_order_id').value;
    const orderDetailsDiv = document.getElementById('order_details');
    const orderDateInput = document.getElementById('order_date');
    const deliveryDateInput = document.getElementById('delivery_date');
    const orderNotesInput = document.getElementById('order_notes');
    const cityInput = document.getElementById('city');

    if (orderId && orderDetails[orderId]) {
        const details = orderDetails[orderId];
        orderDetailsDiv.style.display = 'block';
        orderDetailsDiv.innerHTML = `
                    <div class="product-card">
                        <h3>Order #${orderId}</h3>
                        <p><strong>Date:</strong> ${details.order_date}</p>
                        <p><strong>Delivery:</strong> ${details.delivery_date}</p>
                        <p><strong>Notes:</strong> ${details.order_notes}</p>
                        <p><strong>Quantity:</strong> ${details.order_quantity}</p>
                        <p><strong>City:</strong> ${details.city}</p>
                        <p><strong>Products:</strong> ${details.products}</p>
                        <p><strong>Value:</strong> $${details.order_value}</p>
                        <p><strong>Status:</strong> ${details.delivery_status}</p>
                    </div>
                `;
        orderDateInput.value = details.order_date;
        deliveryDateInput.value = details.delivery_date === 'Not set' ? '' : details.delivery_date;
        orderNotesInput.value = details.order_notes === 'None' ? '' : details.order_notes;
        cityInput.value = details.city === 'Unknown' ? '' : details.city;
    } else {
        orderDetailsDiv.style.display = 'none';
        orderDetailsDiv.innerHTML = '';
        orderDateInput.value = '';
        deliveryDateInput.value = '';
        orderNotesInput.value = '';
        cityInput.value = '';
    }
}

function confirmOrderSubmission() {
    const formMode = document.getElementById('form_mode').value;
    const city = document.getElementById('city').value;
    if (formMode === 'add') {
        return confirm(`Are you sure you want to add this order for ${city}?`);
    } else {
        const orderId = document.getElementById('existing_order_id').value;
        const isDelivered = orderDetails[orderId]?.delivery_status === 'Delivered';
        const message = isDelivered
            ? `Are you sure you want to update order #${orderId} for ${city}? This will reverse and re-apply stock changes for this delivered order.`
            : `Are you sure you want to update order #${orderId} for ${city}?`;
        return confirm(message);
    }
}

function confirmDelivery(orderId) {
    return confirm(`Are you sure you want to confirm delivery for order #${orderId}?`);
}

function confirmRemoveOrder(orderId, isDelivered) {
    const message = isDelivered
        ? `Are you sure you want to delete order #${orderId}? This will reverse stock changes made during delivery. This action cannot be undone.`
        : `Are you sure you want to delete order #${orderId}? This action cannot be undone.`;
    return confirm(message);
}