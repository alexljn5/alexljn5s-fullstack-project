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