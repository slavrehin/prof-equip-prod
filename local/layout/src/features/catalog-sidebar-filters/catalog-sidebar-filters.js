import { initAccordion } from '@/shared/lib';

window.addEventListener('load', () => {
	const catalogSidebarFilters = document.querySelector('.catalog-sidebar-filters');
	const catalogFeatures = document.querySelector('.catalog__features ');
	const header = document.querySelector('.header');

	const footer = document.querySelector('.footer');

	if (catalogSidebarFilters && catalogFeatures) {
		const filterBtn = catalogFeatures.querySelector('.filter__btn');
		const closeFilters = catalogSidebarFilters.querySelector('.close-filters');
		const checkboxList = catalogSidebarFilters.querySelectorAll('input');
		const acceptBtn = catalogSidebarFilters.querySelector('.accept__btn');
		const filtersList = catalogSidebarFilters.querySelectorAll('.filters__list');
		filterBtn.addEventListener('click', () => {
			if (catalogSidebarFilters.classList.contains('active')) {
				catalogSidebarFilters.classList.remove('active');
			} else {
				catalogSidebarFilters.style.top = `${filterBtn.getBoundingClientRect().top + filterBtn.offsetHeight + 10}px`;
				catalogSidebarFilters.classList.add('active');
			}
		});
		closeFilters.addEventListener('click', () => {
			catalogSidebarFilters.classList.remove('active');
		});
		window.addEventListener('scroll', () => {
			catalogSidebarFilters.style.top = `${filterBtn.getBoundingClientRect().top + filterBtn.offsetHeight + 10}px`;
			if (window.scrollY > header.offsetHeight + 150) {
				filterBtn.classList.add('fixed');
				catalogFeatures.style.height = `${filterBtn.offsetHeight}px`;
			} else {
				filterBtn.classList.remove('fixed');
			}
		});

		checkboxList.forEach(checkbox => {
			checkbox.addEventListener('change', () => {
				const checkedCheckboxList = catalogSidebarFilters.querySelectorAll('input:checked');
				if (checkedCheckboxList.length === 0) {
					acceptBtn.classList.add('hidden');
				} else {
					acceptBtn.classList.remove('hidden');
				}
			});
		});

		if (filtersList) {
			filtersList.forEach(list => {
				const labelList = list.querySelectorAll('.filter-label');

				const replacedLabels = [...labelList].splice(0, 7);
				let maxHeight = 0;
				if (replacedLabels) {
					replacedLabels.forEach(replacedLabel => {
						maxHeight += replacedLabel.offsetHeight + 10;
					});
				}
				if (labelList.length > 7) {
					list.classList.add('scroll');
					list.style.maxHeight = `${maxHeight + 20}px`;
				}
			});
		}
		initAccordion(catalogSidebarFilters);
	}
});
