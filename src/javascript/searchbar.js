document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchbar');
    const productCards = document.querySelectorAll('.product-card');

    if (searchInput && productCards.length > 0) {
        searchInput.addEventListener('input', function () {
            const query = searchInput.value.toLowerCase().trim();

            productCards.forEach(card => {
                const type = card.querySelector('h3').textContent.toLowerCase();
                const id = card.querySelector('p strong').nextSibling.textContent.toLowerCase();
                const isVisible = type.includes(query) || id.includes(query);
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
    }
});