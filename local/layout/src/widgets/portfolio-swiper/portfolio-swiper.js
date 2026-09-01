import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

const portfolioSwiperWrapper = document.querySelector('.portfolio-swiper-wrapper ');
if (portfolioSwiperWrapper) {
	const swiperObj = portfolioSwiperWrapper.querySelector('.portfolio-swiper');
	new Swiper(swiperObj, {
		modules: [Navigation, Pagination],
		slidesPerView: 5,
		spaceBetween: 10,
		navigation: {
			prevEl: '.portfolio__prev',
			nextEl: '.portfolio__next'
		},
		breakpoints: {
			0: {
				slidesPerView: 1
			},
			576: {
				slidesPerView: 3
			},
			767: {
				slidesPerView: 4
			},
			992: {
				slidesPerView: 5
			}
		}
	});
}
