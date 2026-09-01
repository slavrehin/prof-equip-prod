import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

const reviewsSwiper = new Swiper('.reviews-swiper', {
	modules: [Navigation, Pagination],
	slidesPerView: 1,
	spaceBetween: 30,
	navigation: {
		prevEl: '.reviews__prev',
		nextEl: '.reviews__next'
	},
	pagination: {
		el: '.pagination',
		clickable: true
	},
	loop: true,
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
