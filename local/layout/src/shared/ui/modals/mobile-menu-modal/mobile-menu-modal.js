import { initAccordion } from '@/shared/lib';

document.addEventListener('modal:mounted-mobile-menu-modal', e => {
	const {
		detail: { modal }
	} = e;

	initAccordion(modal);
});
