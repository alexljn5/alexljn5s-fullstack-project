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