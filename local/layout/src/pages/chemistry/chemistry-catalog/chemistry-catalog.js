import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

new Swiper('.chemistry-catalog-swiper ', {
	modules: [Navigation, Pagination],
	slidesPerView: 4,
	spaceBetween: 30,
	navigation: {
		prevEl: '.chemistry__prev',
		nextEl: '.chemistry__next'
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
		},
		1440: {
			slidesPerView: 4
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
