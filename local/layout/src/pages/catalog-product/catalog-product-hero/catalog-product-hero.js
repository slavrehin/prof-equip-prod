import Swiper from 'swiper';
import { Thumbs } from 'swiper/modules';

document.querySelectorAll('.zoom-wrapper').forEach(wrapper => {
	const img = wrapper.querySelector('picture img') || wrapper.querySelector('img');

	if (!img) return;

	wrapper.addEventListener('mousemove', e => {
		const rect = wrapper.getBoundingClientRect();
		const x = ((e.clientX - rect.left) / rect.width) * 100;
		const y = ((e.clientY - rect.top) / rect.height) * 100;

		img.style.transformOrigin = `${x}% ${y}%`;
	});

	wrapper.addEventListener('mouseleave', () => {
		img.style.transformOrigin = 'center center';
	});
});

const catalogProductHeroSwiperWrapper = document.querySelector(
	'.catalog-product-hero__swiper-wrapper '
);

if (catalogProductHeroSwiperWrapper) {
	const catalogProductHeroSwiperThumbs = catalogProductHeroSwiperWrapper.querySelector(
		'.catalog-product-hero__swiper-thumbs'
	);
	const catalogProductHeroSwiper = catalogProductHeroSwiperWrapper.querySelector(
		'.catalog-product-hero__swiper'
	);
	if (catalogProductHeroSwiperThumbs && catalogProductHeroSwiper) {
		const swiperThumbs = new Swiper(catalogProductHeroSwiperThumbs, {
			modules: [Thumbs],
			spaceBetween: 10,
			slidesPerView: 3,
			freeMode: true,
			watchSlidesProgress: true
		});
		const swiper = new Swiper(catalogProductHeroSwiper, {
			modules: [Thumbs],
			spaceBetween: 10,

			thumbs: {
				swiper: swiperThumbs
			}
		});
	}
}
