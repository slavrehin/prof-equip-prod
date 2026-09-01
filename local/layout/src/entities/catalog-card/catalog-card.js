const catalogCardList = document.querySelectorAll('.catalog-card');

if (catalogCardList) {
	catalogCardList.forEach(card => {
		const costBtn = card.querySelector('.cost__btn');
		costBtn.addEventListener('click', e => {
			e.preventDefault();
		});
	});
}
