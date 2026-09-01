import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

new Swiper('.equipment-portfolio-swiper', {
	modules: [Navigation, Pagination],
	slidesPerView: 5,
	spaceBetween: 10,
	navigation: {
		prevEl: '.equipment-portfolio__prev',
		nextEl: '.equipment-portfolio__next'
	},
	loop: true,
	breakpoints: {
		0: {
			slidesPerView: 1
		},
		576: {
			slidesPerView: 2
		},
		992: {
			slidesPerView: 3
		}
	},
	on: {
		init: swiper => {
			if (window.innerWidth >= 768) {
				updateVisibleSlides(swiper);
			}
		},
		slideChange: swiper => {
			if (window.innerWidth >= 768) {
				updateVisibleSlides(swiper);
			}
		},
		resize: swiper => {
			if (window.innerWidth >= 768) {
				updateVisibleSlides(swiper);
			}
		}
	}
});
