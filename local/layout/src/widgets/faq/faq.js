import { initAccordion } from '@/shared/lib';

window.addEventListener('load', () => {
	const faq = document.querySelector('.faq');

	if (faq) {
		setTimeout(() => {
			initAccordion(faq);
		}, 300);
	}
});
