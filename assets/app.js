import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import 'trix';
import 'trix/dist/trix.min.css';
import './js/productPage.js';

const initTrixEditors = () => {
    document.querySelectorAll('textarea[data-trix=\"true\"]').forEach((textarea, index) => {
        if (textarea.dataset.trixInitialized === 'true') {
            return;
        }

        const baseId = textarea.id || `trix-input-${index}`;
        const inputId = `${baseId}-input`;
        const editorId = `${baseId}-trix`;
        textarea.id = `${baseId}-source`;
        textarea.dataset.trixInitialized = 'true';
        textarea.classList.add('trix-hidden');
        textarea.setAttribute('aria-hidden', 'true');
        textarea.hidden = true;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.id = inputId;
        input.name = textarea.name;
        input.value = textarea.value;
        textarea.removeAttribute('name');

        const editor = document.createElement('trix-editor');
        editor.setAttribute('input', inputId);
        editor.id = editorId;
        if (textarea.placeholder) {
            editor.setAttribute('placeholder', textarea.placeholder);
        }

        textarea.insertAdjacentElement('afterend', input);
        input.insertAdjacentElement('afterend', editor);

        const label = document.querySelector(`label[for="${inputId}"]`);
        if (label) {
            label.setAttribute('for', editorId);
        }
    });
};

document.addEventListener('DOMContentLoaded', initTrixEditors);
document.addEventListener('turbo:load', initTrixEditors);
document.addEventListener('turbo:render', initTrixEditors);

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
