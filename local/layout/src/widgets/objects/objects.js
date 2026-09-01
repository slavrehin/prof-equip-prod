import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';

new Swiper('.objects__swiper', {
	modules: [Navigation],
	slidesPerView: 4,
	spaceBetween: 30,
	navigation: {
		prevEl: '.objects__prev',
		nextEl: '.objects__next'
	},
	breakpoints: {
		0: {
			slidesPerView: 1
		},
		576: {
			slidesPerView: 2
		},
		992: {
			slidesPerView: 3
		},
		1200: {
			slidesPerView: 4
		}
	}
});
