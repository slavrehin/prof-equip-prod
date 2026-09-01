const catalogViewBtn = document.querySelectorAll('.catalog-view__btn');
const catalogList = document.querySelector('.catalog__list');

if (catalogViewBtn) {
	catalogViewBtn.forEach(btn => {
		btn.addEventListener('click', () => {
			const activeBtn = document.querySelector('.catalog-view__btn.active');
			const viewType = btn.getAttribute('data-view');
			if (activeBtn) {
				activeBtn.classList.remove('active');
			}
			btn.classList.add('active');
			catalogList.classList.remove('lines', 'columns');
			catalogList.classList.add(viewType);
		});
	});
}
