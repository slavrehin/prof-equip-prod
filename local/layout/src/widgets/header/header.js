const header = document.querySelector('.header');

if (header) {
	const burgerBtn = header.querySelector('.burger__btn');
	const directionBtn = header.querySelector('.direction__btn');
	const servicesBtn = header.querySelector('.services__btn');
	const searchBtn = header.querySelector('.search__btn');
	const closeSearchBtn = header.querySelector('.close-search__btn');
	const searchBlock = header.querySelector('.search-block');
	burgerBtn.addEventListener('click', () => {
		header.classList.remove('direction-open');
		header.classList.remove('services-open');
		directionBtn.classList.remove('active');
		servicesBtn.classList.remove('active');
		if (burgerBtn.classList.contains('active')) {
			header.classList.remove('catalog-open');
			burgerBtn.classList.remove('active');
		} else {
			header.classList.add('catalog-open');
			burgerBtn.classList.add('active');
		}
	});
	directionBtn.addEventListener('click', () => {
		header.classList.remove('catalog-open');
		header.classList.remove('services-open');
		burgerBtn.classList.remove('active');
		servicesBtn.classList.remove('active');
		if (directionBtn.classList.contains('active')) {
			header.classList.remove('direction-open');
			directionBtn.classList.remove('active');
		} else {
			header.classList.add('direction-open');
			directionBtn.classList.add('active');
		}
	});
	servicesBtn.addEventListener('click', () => {
		header.classList.remove('catalog-open');
		header.classList.remove('direction-open');
		burgerBtn.classList.remove('active');
		directionBtn.classList.remove('active');
		if (servicesBtn.classList.contains('active')) {
			header.classList.remove('services-open');
			servicesBtn.classList.remove('active');
		} else {
			header.classList.add('services-open');
			servicesBtn.classList.add('active');
		}
	});
	window.addEventListener('scroll', () => {
		if (window.scrollY > 0) {
			header.classList.add('active');
		} else {
			header.classList.remove('active');
		}
	});

	searchBtn.addEventListener('click', () => {
		header.classList.add('open-search');
		searchBtn.classList.add('hidden');
		closeSearchBtn.classList.remove('hidden');
		setTimeout(() => {
			searchBlock.classList.add('active');
		}, 300);
	});
	closeSearchBtn.addEventListener('click', () => {
		searchBlock.classList.remove('active');
		setTimeout(() => {
			header.classList.remove('open-search');
			searchBtn.classList.remove('hidden');
			closeSearchBtn.classList.add('hidden');
		}, 300);
	});
}
