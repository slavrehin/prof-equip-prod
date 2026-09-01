import { FormValidator } from '@/shared/lib';

const feedbackForm = document.querySelector('.feedback-form');

if (feedbackForm) {
	new FormValidator(feedbackForm);
}
