document.addEventListener('DOMContentLoaded', function () {
    // Find the product-grid
    const productGrid = document.querySelector('.product-grid');
    if (productGrid) {
        // Create scrollable-grid container
        const scrollableGrid = document.createElement('div');
        scrollableGrid.className = 'scrollable-grid';

        // Wrap product-grid in scrollable-grid
        productGrid.parentNode.insertBefore(scrollableGrid, productGrid);
        scrollableGrid.appendChild(productGrid);
    }

    // Existing toggleForm function for add product form
    function toggleForm() {
        const form = document.getElementById('addProductForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
});

document.addEventListener('DOMContentLoaded', function () {
    window.toggleForm = function () {
        const form = document.getElementById('addProductForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    };
});

function toggleForm() {
    const form = document.getElementById('addProductForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleFormFields() {
    const formMode = document.getElementById('form_mode').value;
    const addFields = document.getElementById('add_product_fields');
    const updateFields = document.getElementById('update_stock_fields');

    if (formMode === 'add') {
        addFields.style.display = 'block';
        updateFields.style.display = 'none';
        // Set required attributes for add product fields
        document.getElementById('productid').required = true;
        document.getElementById('type').required = true;
        document.getElementById('manufacturer').required = true;
        document.getElementById('price').required = true;
        document.getElementById('amount_in_stock').required = true;
        document.getElementById('city').required = true;
        document.getElementById('existing_productid').required = false;
        document.getElementById('additional_stock').required = false;
        document.getElementById('city_update').required = false;
    } else {
        addFields.style.display = 'none';
        updateFields.style.display = 'block';
        // Set required attributes for update stock fields
        document.getElementById('productid').required = false;
        document.getElementById('type').required = false;
        document.getElementById('manufacturer').required = false;
        document.getElementById('price').required = false;
        document.getElementById('amount_in_stock').required = false;
        document.getElementById('city').required = false;
        document.getElementById('existing_productid').required = true;
        document.getElementById('additional_stock').required = true;
        document.getElementById('city_update').required = true;
    }
}

function confirmFormSubmission() {
    const formMode = document.getElementById('form_mode').value;
    if (formMode === 'add') {
        const productId = document.getElementById('productid').value;
        return confirm(`Are you sure you want to add product ${productId}?`);
    } else {
        const productId = document.getElementById('existing_productid').value;
        const city = document.getElementById('city_update').value;
        return confirm(`Are you sure you want to update stock for product ${productId} in ${city}?`);
    }
}

function confirmDeleteStock(productId, city) {
    return confirm(`Are you sure you want to delete stock for product ${productId} at ${city}? This will remove all stock at this location.`);
}