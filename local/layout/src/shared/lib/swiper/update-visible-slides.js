export const updateVisibleSlides = (swiperInstance) => {
  swiperInstance.slides.forEach((slide) => {
    slide.classList.remove("visible-slide");
  });

  const firstVisibleIndex = swiperInstance.activeIndex;
  const lastVisibleIndex =
    firstVisibleIndex +
    Math.ceil(
      swiperInstance.width /
        (swiperInstance.slidesSizesGrid[0] + swiperInstance.params.spaceBetween)
    ) -
    1;

  for (let i = firstVisibleIndex; i <= lastVisibleIndex; i++) {
    if (swiperInstance.slides[i]) {
      swiperInstance.slides[i].classList.add("visible-slide");
    }
  }
};
