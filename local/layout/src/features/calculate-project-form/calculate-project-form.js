import { FormValidator } from '@/shared/lib';

const calculateProjectForm = document.querySelector('.calculate-project-form');

if (calculateProjectForm) {
	new FormValidator(calculateProjectForm);
}
