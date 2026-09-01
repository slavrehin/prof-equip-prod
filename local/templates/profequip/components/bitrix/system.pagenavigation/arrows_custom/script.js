document.addEventListener('DOMContentLoaded', function() {
    // Обработчик кнопки "Загрузить ещё"
    document.addEventListener('click', function(e) {
        if (e.target.closest('.js-load_more')) {
            e.preventDefault();
            loadMoreContent(e.target.closest('.js-load_more'));
        }
    });

    function simulateLoadMoreClick(url) {
        const tempBtn = document.createElement('button');
        tempBtn.className = 'js-load_more';
        tempBtn.setAttribute('data-url', url);
        document.body.appendChild(tempBtn);
        loadMoreContent(tempBtn);
        document.body.removeChild(tempBtn);
    }

    function loadMoreContent(loadMoreBtn) {
        const targetContainer = document.querySelector('.page-item-list-wrap');
        const url = loadMoreBtn.getAttribute('data-url');

        if (!url || !targetContainer) return;

        // Блокируем кнопку на время загрузки
        setButtonLoadingState(loadMoreBtn, true);

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                // Обновляем URL в браузере
                window.history.pushState({}, '', url);

                // Создаем временный контейнер для парсинга HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;

                // Находим новые элементы
                const newElements = tempDiv.querySelectorAll('.page-item');
                
                const newNavigationWrap = tempDiv.querySelector('.navigation-pages-wrap');

                // Определяем, первая ли это страница
                // Ищем PAGEN_ с любым номером (PAGEN_1, PAGEN_2 и т.д.)
                const isFirstPage = !url.includes('PAGEN_') || url.match(/PAGEN_\d+=1$/);

                if (isFirstPage) {
                    // Заменяем все элементы для первой страницы
                    targetContainer.innerHTML = '';
                }

                // Добавляем новые элементы
                newElements.forEach(element => {
                    targetContainer.appendChild(element);
                });
                document.dispatchEvent(new Event("ajax-load"));

                // Удаляем старый navigation-pages-wrap
                const oldNavigationWrap = document.querySelector('.navigation-pages-wrap');
                if (oldNavigationWrap) {
                    oldNavigationWrap.remove();
                }

                // Вставляем новый navigation-pages-wrap (он уже содержит внутри show-more-wrapper)
                if (newNavigationWrap) {
                    targetContainer.after(newNavigationWrap);
                }

                // Определяем тип действия: load_more или пагинация
                const isLoadMoreAction = loadMoreBtn.classList.contains('js-load_more');
                
                if (isLoadMoreAction && !isFirstPage) {
                    scrollToNewElements(targetContainer);
                } else {
                    const scrollTop = isFirstPage ? 0 : targetContainer.offsetTop - 100;
                    window.scrollTo({
                        top: scrollTop,
                        behavior: 'smooth'
                    });
                }
            })
            .catch(error => {
                console.error('Error loading content:', error);
            })
            .finally(() => {
                setButtonLoadingState(loadMoreBtn, false);
            });
    }

    function setButtonLoadingState(button, isLoading) {
        if (isLoading) {
            button.disabled = true;
            button.classList.add('loading');
            
            // Сохраняем оригинальное содержимое
            const originalContent = button.innerHTML;
            button.setAttribute('data-original-content', originalContent);
            
            // Заменяем на индикатор загрузки
            const svg = button.querySelector('svg');
            if (svg) {
                // Сохраняем оригинальный SVG
                button.setAttribute('data-original-svg', svg.outerHTML);
                
                // Клонируем SVG и добавляем текст загрузки
                const newSvg = svg.cloneNode(true);
                button.innerHTML = '';
                button.appendChild(newSvg);
                
                const span = document.createElement('span');
                span.textContent = 'Загрузка...';
                span.style.marginLeft = '10px';
                button.appendChild(span);
            } else {
                button.innerHTML = 'Загрузка...';
            }
        } else {
            button.disabled = false;
            button.classList.remove('loading');
            
            // Восстанавливаем оригинальное содержимое
            const originalContent = button.getAttribute('data-original-content');
            if (originalContent) {
                button.innerHTML = originalContent;
                button.removeAttribute('data-original-content');
                button.removeAttribute('data-original-svg');
            }
        }
    }

    function scrollToNewElements(container) {
        // Получаем все текущие элементы .page-item
        const currentItems = container.querySelectorAll('.page-item');
        
        // Получаем количество элементов ДО загрузки (из data-атрибута или считаем)
        const previousCount = parseInt(container.getAttribute('data-item-count') || '0');
        
        // Если есть новые элементы (загруженные сейчас)
        if (currentItems.length > previousCount && previousCount > 0) {
            // Находим первый новый элемент
            const firstNewItem = currentItems[previousCount];
            
            if (firstNewItem) {
                const elementPosition = firstNewItem.getBoundingClientRect().top + window.pageYOffset;
                const offsetPosition = elementPosition - 200; // Отступ сверху

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        }
        
        // Обновляем счетчик элементов в контейнере
        container.setAttribute('data-item-count', currentItems.length.toString());
    }

    // Обработка кнопки "Загрузить ещё" при нажатии на внутренние элементы
    document.addEventListener('click', function(e) {
        const loadMoreWrapper = e.target.closest('.show-more-wrapper');
        if (loadMoreWrapper) {
            const loadMoreBtn = loadMoreWrapper.querySelector('.js-load_more');
            if (loadMoreBtn && e.target !== loadMoreBtn) {
                e.preventDefault();
                loadMoreBtn.click();
            }
        }
    });

    // Инициализация счетчика элементов при загрузке страницы
    function initItemCounter() {
        const targetContainer = document.querySelector('.page-item-list-wrap');
        if (targetContainer) {
            const initialCount = targetContainer.querySelectorAll('.page-item').length;
            targetContainer.setAttribute('data-item-count', initialCount.toString());
        }
    }

    // Запускаем инициализацию при загрузке
    initItemCounter();
});