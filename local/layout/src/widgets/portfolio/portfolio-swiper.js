import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

const portfolioSwiperWrapper = document.querySelectorAll('.portfolio');

if (portfolioSwiperWrapper) {
	portfolioSwiperWrapper.forEach(wrapper => {
		const navigation = wrapper.querySelector('.navigation');
		const navigationPrev = navigation.querySelector('.portfolio__prev');
		const navigationNext = navigation.querySelector('.portfolio__next');
		const imageHeight = wrapper.querySelector('.swiper-slide .image-wrapper');
		const swiperObj = wrapper.querySelector('.portfolio-swiper');
		const portfolioSwiper = new Swiper(swiperObj, {
			modules: [Navigation],
			slidesPerView: 4,
			spaceBetween: 30,
			navigation: {
				prevEl: navigationPrev,
				nextEl: navigationNext
			},
			loop: true,
			on: {
				init: swiper => {
					if (window.innerWidth >= 768) {
						updateVisibleSlides(swiper);
					}
					setTimeout(() => {
						navigation.style.height = `${imageHeight.offsetHeight}px`;
					}, 500);
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
					navigation.style.height = `${imageHeight.offsetHeight}px`;
				}
			},
			breakpoints: {
				0: {
					slidesPerView: 1,
					spaceBetween: 30
				},
				768: {
					slidesPerView: 3,
					spaceBetween: 30
				},
				1441: {
					slidesPerView: 4
				}
			}
		});
	});
}
