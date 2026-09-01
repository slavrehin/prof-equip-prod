import { dispatch } from '@/shared/lib';

const header = document.querySelector('.header');
const originalOverflow = document.body.style.overflow;
const scrollBarWidth = window.innerWidth - document.body.offsetWidth;

export const closeModal = (modal, modalName) => {
	if (!modal) return;

	modal.classList.remove('active');

	dispatch({
		el: document,
		name: 'modal:close',
		detail: { modal }
	});

	if (modalName) {
		dispatch({
			el: document,
			name: `modal:close-${modalName}`,
			detail: { modal }
		});
	}

	setTimeout(() => {
		document.body.style.overflow = originalOverflow;
		document.body.style.paddingRight = '0px';

		if (header) {
			header.style.paddingRight = '0px';
			header.classList.remove('modal-open');
		}
	}, 300);
};

const openModal = (modal, modalName) => {
	if (!modal) return;

	document.body.style.overflow = 'hidden';
	document.body.style.paddingRight = `${scrollBarWidth}px`;

	if (header) {
		header.style.paddingRight = `${scrollBarWidth}px`;
		header.classList.add('modal-open');
	}

	modal.classList.add('active');

	dispatch({
		el: document,
		name: 'modal:open',
		detail: { modal }
	});

	if (modalName) {
		dispatch({
			el: document,
			name: `modal:open-${modalName}`,
			detail: { modal }
		});
	}
};

export const loadModal = async url => {
	const modalsBlock = document.querySelector('.modals');
	if (!modalsBlock) return;

	const modalName = url.split('/').pop().replace('.html', '');

	const existingModal = modalsBlock.querySelector(`.modal[data-modal="${modalName}"]`);

	if (existingModal) {
		openModal(existingModal, modalName);
		return;
	}

	try {
		const response = await fetch(url);
		if (!response.ok) throw new Error('Ошибка загрузки');

		const html = await response.text();
		const wrapper = document.createElement('div');
		wrapper.innerHTML = html;

		const modal = wrapper.firstElementChild;
		if (!modal) throw new Error('Modal not found');

		const modalName = modal.getAttribute('data-modal');
		modalsBlock.appendChild(modal);
		dispatch({
			el: document,
			name: `modal:mounted-${modalName}`,
			detail: { modal }
		});

		setTimeout(() => {
			openModal(modal, modalName);
		}, 10);
	} catch (err) {
		console.error(err);
	}
};

const bindOpenButtons = () => {
	document.querySelectorAll('[data-modal-load]').forEach(btn => {
		const url = btn.getAttribute('data-modal-load');
		if (!url) return;

		btn.addEventListener('click', () => loadModal(url));
	});

	document.querySelectorAll('[data-modal-target]').forEach(btn => {
		const modalName = btn.getAttribute('data-modal-target');
		if (!modalName) return;

		btn.addEventListener('click', () => {
			const modal = document.querySelector(`.modal[data-modal="${modalName}"]`);
			openModal(modal, modalName);
		});
	});
};

const bindModalEvents = () => {
	document.querySelectorAll('.modal').forEach(modal => {
		const modalName = modal.getAttribute('data-modal');

		modal.addEventListener('click', e => {
			if (e.target === modal) {
				closeModal(modal, modalName || undefined);
			}
		});

		modal.querySelectorAll('.close-modal').forEach(btn => {
			btn.addEventListener('click', () => {
				closeModal(modal, modalName || undefined);
			});
		});
	});
};

bindOpenButtons();
document.addEventListener('modal:open', bindModalEvents);
