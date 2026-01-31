// Entrée principale pour Webpack Encore
import './product-form.js';
import '../styles/app.tailwind.css';

// Expose Alpine if needed (Alpine is loaded via importmap in templates, keep compatibility)
if (typeof window !== 'undefined') {
    // nothing to expose here; factories are exported from product-form
}
