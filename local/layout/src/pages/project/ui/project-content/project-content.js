import Swiper from 'swiper';
import { Navigation, Pagination, Thumbs } from 'swiper/modules';

import { initAccordion } from '@/shared/lib';

window.addEventListener('load', () => {
	const projectContent = document.querySelector('.project-content');

	if (projectContent) {
		setTimeout(() => {
			initAccordion(projectContent);
		}, 300);
	}
});

const projectContentSwiperWrapper = document.querySelector('.project-content__swiper-wrapper');

if (projectContentSwiperWrapper) {
	const projectContentSwiperThumbs = projectContentSwiperWrapper.querySelector(
		'.project-content__swiper-thumbs'
	);
	const projectContentSwiper = projectContentSwiperWrapper.querySelector(
		'.project-content__swiper'
	);

	if (projectContentSwiperThumbs && projectContentSwiper) {
		const swiperThumbs = new Swiper(projectContentSwiperThumbs, {
			modules: [Thumbs],
			spaceBetween: 10,
			slidesPerView: 5,
			freeMode: true,
			watchSlidesProgress: true
		});

		new Swiper(projectContentSwiper, {
			modules: [Navigation, Thumbs],
			spaceBetween: 10,
			navigation: {
				prevEl: '.swiper-prev',
				nextEl: '.swiper-next'
			},
			thumbs: {
				swiper: swiperThumbs
			}
		});
	}
}

const projectContentBrandsList = document.querySelector('.project-content__brands-list');

if (projectContentBrandsList) {
	const projectContentBrandsTrack = projectContentBrandsList.querySelector(
		'.project-content__brands-track'
	);

	if (projectContentBrandsTrack) {
		const items = Array.from(projectContentBrandsTrack.children);

		if (items.length < 3) {
			projectContentBrandsList.classList.add('is-static', 'container');
		} else {
			items.forEach(item => {
				const clone = item.cloneNode(true);
				clone.setAttribute('aria-hidden', 'true');
				projectContentBrandsTrack.appendChild(clone);
			});
			items.forEach(item => {
				const clone = item.cloneNode(true);
				clone.setAttribute('aria-hidden', 'true');
				projectContentBrandsTrack.appendChild(clone);
			});

			const firstClone = projectContentBrandsTrack.children[items.length];

			const updateMarqueeDistance = () => {
				const distance = firstClone.offsetLeft - items[0].offsetLeft;

				if (distance > 0) {
					projectContentBrandsTrack.style.setProperty('--brands-marquee-distance', `${distance}px`);
					console.log(distance);
				}
			};

			const images = projectContentBrandsTrack.querySelectorAll('img');
			let loadedCount = 0;

			images.forEach(image => {
				if (image.complete) {
					loadedCount += 1;
				} else {
					image.addEventListener('load', () => {
						loadedCount += 1;
						if (loadedCount === images.length) updateMarqueeDistance();
					});
				}
			});

			if (loadedCount === images.length) updateMarqueeDistance();

			window.addEventListener('resize', updateMarqueeDistance);
		}
	}
}

const projectContentProductsSwiper = document.querySelector('.project-content__products-swiper');

if (projectContentProductsSwiper) {
	new Swiper(projectContentProductsSwiper, {
		modules: [Navigation, Pagination],
		spaceBetween: 20,
		pagination: {
			el: '.pagination',
			clickable: true
		},
		navigation: {
			prevEl: '.swiper-prev',
			nextEl: '.swiper-next'
		},
		breakpoints: {
			0: {
				slidesPerView: 2
			},
			576: {
				slidesPerView: 2
			},
			992: {
				slidesPerView: 4
			}
		}
	});
}
