import { FormValidator } from '@/shared/lib';

document.addEventListener('modal:mounted-cost-modal', e => {
	const {
		detail: { modal }
	} = e;
	const consultationForm = modal.querySelector('.cost__form');
	if (consultationForm) {
		new FormValidator(consultationForm);
	}
});
