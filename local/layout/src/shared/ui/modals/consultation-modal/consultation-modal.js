import { FormValidator } from '@/shared/lib';

document.addEventListener('modal:mounted-consultation-modal', e => {
	const {
		detail: { modal }
	} = e;
	const consultationForm = modal.querySelector('.consultation__form');
	if (consultationForm) {
		new FormValidator(consultationForm);
	}
});
