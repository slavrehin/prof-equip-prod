const STORAGE_KEY = 'sale-banner-closed';

const banner = document.querySelector('.sale-banner');

if (banner && !sessionStorage.getItem(STORAGE_KEY)) {
	const closeBanner = () => {
		banner.classList.remove('active');
		sessionStorage.setItem(STORAGE_KEY, 'true');
	};

	setTimeout(() => {
		if (sessionStorage.getItem(STORAGE_KEY)) return;
		banner.classList.add('active');
	}, 15000);

	banner.addEventListener('click', e => {
		if (e.target === banner) closeBanner();
	});

	banner.querySelector('.sale-banner__close').addEventListener('click', closeBanner);
}
