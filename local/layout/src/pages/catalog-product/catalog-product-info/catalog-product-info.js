import { initTabs } from '@/shared/lib';

const catalogProductInfoTabs = document.querySelector(
	'.catalog-product-info .catalog-product-info__inner'
);

if (catalogProductInfoTabs) {
	initTabs(catalogProductInfoTabs);
}
