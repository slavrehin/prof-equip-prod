import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';

const furnitureCases = new Swiper('.furniture-cases-swiper', {
	modules: [Navigation],
	slidesPerView: 'auto',
	spaceBetween: 50,

	navigation: {
		prevEl: '.furniture-cases__prev',
		nextEl: '.furniture-cases__next'
	},
	breakpoints: {
		0: {
			slidesPerView: 1
		},
		767: {
			slidesPerView: 'auto'
		}
	}
});
