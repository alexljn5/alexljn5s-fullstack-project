// Wait for the webpage to fully load before running the code
document.addEventListener('DOMContentLoaded', function () {
    // Get the search bar input element by its ID ('searchbar')
    const searchInput = document.getElementById('searchbar');
    // Get all elements with the class 'product-card' (the product cards to filter)
    const productCards = document.querySelectorAll('.product-card');

    // Check if the search bar exists and there are product cards to filter
    if (searchInput && productCards.length > 0) {
        // Optimization: Pre-cache lowercase type and ID for each card to avoid repeated DOM queries
        const cardData = Array.from(productCards).map(card => {
            const typeElem = card.querySelector('h3');
            const idElem = card.querySelector('p strong')?.nextSibling; // Optional chaining for safety
            return {
                card: card,
                type: typeElem ? typeElem.textContent.toLowerCase() : '',
                id: idElem ? idElem.textContent.toLowerCase() : ''
            };
        });

        // Flag to track if the search bar has been focused
        let isSearchActive = false;

        // Activate search only when the search bar is focused (clicked or tabbed into)
        searchInput.addEventListener('focus', function () {
            isSearchActive = true;
        });

        // Optional: Deactivate search when the user clicks away (loses focus)
        searchInput.addEventListener('blur', function () {
            isSearchActive = false;
        });

        // Listen for input events (typing, pasting, etc.) in the search bar
        searchInput.addEventListener('input', function () {
            // Only process the search if the search bar is focused
            if (isSearchActive) {
                // Get the search text, convert to lowercase, and remove extra spaces
                const query = searchInput.value.toLowerCase().trim();

                // Loop through cached card data (faster than querying DOM each time)
                cardData.forEach(data => {
                    // Check if the search query is in the pre-cached type or ID
                    const isVisible = data.type.includes(query) || data.id.includes(query);
                    // Show the card (display: block) if it matches, hide it (display: none) if it doesn't
                    data.card.style.display = isVisible ? 'block' : 'none';
                });
            }
        });
    }
});
