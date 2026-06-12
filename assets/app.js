import './stimulus_bootstrap.js';
import '@hotwired/turbo';
import './controllers/csrf_protection_controller.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// Show loading indicator on buttons/links/forms to give immediate feedback
function setLoading(el){
	if(!el || el.classList.contains('is-loading')) return;
	el.classList.add('is-loading');
	try{ el.disabled = true; }catch(e){}
}

// On form submit, find the submit button and set loading
document.addEventListener('submit', function(e){
	const form = e.target;
	if(!(form instanceof HTMLFormElement)) return;
	const btn = form.querySelector('button[type="submit"], input[type="submit"]');
	if(!btn) return;

	const activeElement = document.activeElement;
	if (activeElement instanceof HTMLButtonElement || activeElement instanceof HTMLInputElement) {
		setLoading(activeElement);
	} else {
		setLoading(btn);
	}
});


