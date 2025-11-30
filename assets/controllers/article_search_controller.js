import { Controller } from '@hotwired/stimulus';

/**
 * ArticleSearchController
 *
 * Handles article search by number via AJAX.
 * Follows SRP: Only manages search UI and API communication.
 *
 * Usage in Twig:
 * <div data-controller="article-search">
 *   <input data-article-search-target="input" data-action="keydown.enter->article-search#search">
 *   <button data-action="click->article-search#search">Search</button>
 * </div>
 */
export default class extends Controller {
    // Define targets (DOM elements this controller manages)
    static targets = [
        'input',        // Search input field
        'button',       // Search button
        'clearButton',  // Clear/Reset button
        'results',      // Results container
        'loading',      // Loading indicator
        'error'         // Error message container
    ];

    // Define values (configurable parameters)
    static values = {
        url: String     // API endpoint URL
    };

    /**
     * Initialize controller when connected to DOM
     */
    connect() {
        // Set default API URL if not provided
        if (!this.hasUrlValue) {
            this.urlValue = '/api/articles/search-by-number';
        }

        // Add Enter key support to input field
        if (this.hasInputTarget) {
            this.inputTarget.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.search();
                }
            });
        }
    }

    /**
     * Execute search when button clicked or Enter pressed
     */
    async search() {
        // Get article number from input
        const articleNumber = this.inputTarget.value.trim();

        // Validate input
        if (!articleNumber) {
            this.showError('Por favor ingrese un número de artículo');
            return;
        }

        if (parseInt(articleNumber) <= 0) {
            this.showError('El número de artículo debe ser mayor a 0');
            return;
        }

        // Disable button during search
        this.buttonTarget.disabled = true;

        // Show loading, hide other states
        this.hideAll();
        this.loadingTarget.classList.remove('hidden');

        try {
            // Call API endpoint
            const response = await fetch(
                `${this.urlValue}?number=${encodeURIComponent(articleNumber)}`,
                {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                }
            );

            // Parse JSON response
            const data = await response.json();

            // Handle HTTP errors
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
            }

            // Display results
            this.displayResults(data);

            // Show clear button after successful search
            this.showClearButton();

        } catch (error) {
            // Handle network or parsing errors
            console.error('Search error:', error);
            this.showError(
                error.message || 'Error al buscar el artículo. Por favor intente nuevamente.'
            );

            // Show clear button even on error (so user can clear error state)
            this.showClearButton();
        } finally {
            // Re-enable button
            this.buttonTarget.disabled = false;
            this.loadingTarget.classList.add('hidden');
        }
    }

    /**
     * Display search results in the UI
     * @param {Object} data - API response data
     */
    displayResults(data) {
        this.hideAll();

        const { count, articles } = data;

        // No results found
        if (count === 0) {
            this.resultsTarget.innerHTML = `
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="text-yellow-800 font-medium">
                            No se encontró ningún artículo con ese número.
                        </p>
                    </div>
                </div>
            `;
        } else {
            // Build HTML for results
            const articlesHtml = articles.map(article => `
                <article class="border-2 border-blue-200 rounded-lg p-4 sm:p-6 bg-white hover:shadow-lg transition-shadow duration-200">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                        <h3 class="text-blue-900 text-lg sm:text-xl font-bold">
                            Artículo #${this.escapeHtml(article.articleNumber)}
                        </h3>
                        <span class="inline-block bg-green-600 text-white px-3 py-1 rounded-md text-xs sm:text-sm font-bold self-start">
                            ${this.escapeHtml(article.status)}
                        </span>
                    </div>

                    ${article.title ? `
                        <h4 class="text-gray-900 font-bold mb-3 text-base sm:text-lg">
                            ${this.escapeHtml(article.title)}
                        </h4>
                    ` : ''}

                    ${article.chapter ? `
                        <div class="bg-blue-50 px-3 py-2 rounded-md mb-4 inline-block">
                            <span class="text-blue-800 font-semibold text-sm">
                                ${this.escapeHtml(article.chapter)}
                            </span>
                        </div>
                    ` : ''}

                    <div class="text-gray-800 leading-relaxed text-sm sm:text-base">
                        ${this.escapeHtml(article.content)}
                    </div>
                </article>
            `).join('');

            this.resultsTarget.innerHTML = `
                <div class="mb-4">
                    <p class="text-gray-800 font-bold text-base sm:text-lg">
                        ${count === 1 ? 'Se encontró 1 artículo' : `Se encontraron ${count} artículos`}
                    </p>
                </div>
                <div class="space-y-4">
                    ${articlesHtml}
                </div>
            `;
        }

        this.resultsTarget.classList.remove('hidden');
    }

    /**
     * Show error message
     * @param {string} message - Error message to display
     */
    showError(message) {
        this.hideAll();
        this.errorTarget.querySelector('p').textContent = message;
        this.errorTarget.classList.remove('hidden');
    }

    /**
     * Hide all result containers
     */
    hideAll() {
        this.resultsTarget.classList.add('hidden');
        this.errorTarget.classList.add('hidden');
        this.loadingTarget.classList.add('hidden');
    }

    /**
     * Escape HTML to prevent XSS attacks
     * Security: Always escape user-provided or API data before inserting into DOM
     *
     * @param {string} text - Text to escape
     * @returns {string} Escaped HTML-safe text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Clear search results and reset form
     * Called when clear/reset button is clicked
     */
    clear() {
        // Clear input field
        this.inputTarget.value = '';

        // Hide all result containers
        this.hideAll();

        // Hide clear button
        this.hideClearButton();

        // Focus back to input for better UX
        this.inputTarget.focus();
    }

    /**
     * Show the clear button
     */
    showClearButton() {
        if (this.hasClearButtonTarget) {
            this.clearButtonTarget.classList.remove('hidden');
        }
    }

    /**
     * Hide the clear button
     */
    hideClearButton() {
        if (this.hasClearButtonTarget) {
            this.clearButtonTarget.classList.add('hidden');
        }
    }
}
