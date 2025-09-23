// Wait for the webpage to fully load before running the code
document.addEventListener('DOMContentLoaded', function () {
    // Get the search bar input element by its ID ('searchbar')
    const searchInput = document.getElementById('searchbar');
    // Get all elements with the class 'product-card' (the product cards to filter)
    const productCards = document.querySelectorAll('.product-card');

    // Check if the search bar exists and there are product cards to filter
    if (searchInput && productCards.length > 0) {
        // Listen for 'input' events (typing, pasting, etc.) in the search bar
        searchInput.addEventListener('input', function () {
            // Get the search text, convert it to lowercase, and remove extra spaces
            const query = searchInput.value.toLowerCase().trim();

            // Loop through each product card
            productCards.forEach(card => {
                // Get the product type from the <h3> tag in the card, convert to lowercase
                const type = card.querySelector('h3').textContent.toLowerCase();
                // Get the product ID from the text after a <strong> tag in a <p>, convert to lowercase
                const id = card.querySelector('p strong').nextSibling.textContent.toLowerCase();
                // Check if the search query is in the type or ID
                const isVisible = type.includes(query) || id.includes(query);
                // Show the card (display: block) if it matches, hide it (display: none) if it doesn't
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
    }
});
