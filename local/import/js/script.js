document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('importForm');
    const importBtn = document.getElementById('importBtn');
    const openXmlBtn = document.getElementById('openXmlBtn');
    const statusMessage = document.getElementById('statusMessage');
    const progressBar = document.getElementById('progressBar');
    const progressFill = document.getElementById('progressFill');
    const sectionSelect = document.getElementById('sectionSelect');
    const tabButtons = document.querySelectorAll('.import-tab-button');
    const tabContents = document.querySelectorAll('.import-tab-content');
    const xmlImportButtons = document.querySelectorAll('.xml-import-btn');
    const xmlImportSimpleButtons = document.querySelectorAll('.xml-import-simple-btn');
    let isProductImportRunning = false;
    
    // Переключение вкладок
    if (tabButtons.length > 0 && tabContents.length > 0) {
        tabButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-tab');

                tabButtons.forEach(function(btn) {
                    btn.classList.remove('active');
                });
                tabContents.forEach(function(content) {
                    content.classList.remove('active');
                });

                this.classList.add('active');
                const target = document.getElementById(targetId);
                if (target) {
                    target.classList.add('active');
                }
            });
        });
    }

    // Включаем кнопку при выборе раздела
    if (sectionSelect) {
        sectionSelect.addEventListener('change', function() {
            importBtn.disabled = !this.value;
        });
    }
    
    // Обработка отправки формы (импорт ссылок в XML)
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!sectionSelect || !sectionSelect.value) {
                showMessage('Пожалуйста, выберите раздел', 'error');
                return;
            }
            
            const selectedOption = sectionSelect.options[sectionSelect.selectedIndex];
            const sectionId = sectionSelect.value;
            const sectionCode = selectedOption.getAttribute('data-code');
            const sectionName = selectedOption.getAttribute('data-name');
            
            importBtn.disabled = true;
            showMessage('Начало импорта ссылок...', 'info');
            progressBar.style.display = 'block';
            updateProgress(0);
            
            const formData = new FormData();
            formData.append('action', 'import');
            formData.append('section_id', sectionId);
            formData.append('section_code', sectionCode);
            formData.append('section_name', sectionName);
            
            fetch('/local/import/ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    updateProgress(100);
                    if (data.file_path) {
                        openXmlBtn.href = data.file_path;
                        openXmlBtn.classList.remove('disabled');
                        openXmlBtn.classList.add('active');
                    }
                } else {
                    showMessage(data.message || 'Ошибка при импорте', 'error');
                    importBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Ошибка при выполнении запроса: ' + error.message, 'error');
                importBtn.disabled = false;
            });
        });
    }

    // Импорт товаров по XML (пакетами по 10 штук)
    if (xmlImportButtons.length > 0) {
        xmlImportButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                if (isProductImportRunning) {
                    return;
                }

                const sectionId = this.getAttribute('data-section-id');
                if (!sectionId) {
                    showMessage('Не указан раздел для импорта товаров', 'error');
                    return;
                }

                isProductImportRunning = true;
                setAllImportButtonsDisabled(true);

                showMessage('Начало импорта товаров для раздела ID ' + sectionId + '...', 'info');
                progressBar.style.display = 'block';
                updateProgress(0);

                processProductBatch(sectionId, 0);
            });
        });
    }

    // Упрощенный импорт товаров по XML (создаем только новые, существующим обновляем только привязку)
    if (xmlImportSimpleButtons.length > 0) {
        xmlImportSimpleButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                if (isProductImportRunning) {
                    return;
                }

                const sectionId = this.getAttribute('data-section-id');
                if (!sectionId) {
                    showMessage('Не указан раздел для упрощенного импорта товаров', 'error');
                    return;
                }

                isProductImportRunning = true;
                setAllImportButtonsDisabled(true);

                showMessage('Начало упрощенного импорта товаров для раздела ID ' + sectionId + '...', 'info');
                progressBar.style.display = 'block';
                updateProgress(0);

                processProductBatchSimple(sectionId, 0);
            });
        });
    }
    
    function showMessage(message, type) {
        statusMessage.textContent = message;
        statusMessage.className = 'status-message ' + type;
        statusMessage.style.display = 'block';
    }
    
    function updateProgress(percent) {
        progressFill.style.width = percent + '%';
        progressFill.textContent = percent + '%';
    }

    function setAllImportButtonsDisabled(disabled) {
        xmlImportButtons.forEach(function(btn) {
            btn.disabled = disabled;
        });
        xmlImportSimpleButtons.forEach(function(btn) {
            btn.disabled = disabled;
        });
        if (importBtn) {
            importBtn.disabled = disabled || !sectionSelect || !sectionSelect.value;
        }
    }

    function processProductBatch(sectionId, offset) {
        const formData = new FormData();
        formData.append('action', 'import_products');
        formData.append('section_id', sectionId);
        formData.append('offset', offset);

        fetch('/local/import/ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showMessage(data.message || 'Ошибка при импорте товаров', 'error');
                isProductImportRunning = false;
                setAllImportButtonsDisabled(false);
                return;
            }

            const total = data.total || 0;
            const processedTotal = data.processed_total || 0;
            const finished = !!data.finished;
            const nextOffset = data.next_offset || 0;

            let percent = 0;
            if (total > 0) {
                percent = Math.round((processedTotal / total) * 100);
            }

            updateProgress(percent);

            const messageText = 'Импорт товаров: обработано ' + processedTotal + ' из ' + total;
            showMessage(messageText, 'info');

            if (finished) {
                showMessage('Импорт товаров завершен. Обработано ' + processedTotal + ' из ' + total, 'success');
                isProductImportRunning = false;
                setAllImportButtonsDisabled(false);
            } else {
                processProductBatch(sectionId, nextOffset);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Ошибка при выполнении запроса: ' + error.message, 'error');
            isProductImportRunning = false;
            setAllImportButtonsDisabled(false);
        });
    }

    function processProductBatchSimple(sectionId, offset) {
        const formData = new FormData();
        formData.append('action', 'import_products_simple');
        formData.append('section_id', sectionId);
        formData.append('offset', offset);

        fetch('/local/import/ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showMessage(data.message || 'Ошибка при упрощенном импорте товаров', 'error');
                isProductImportRunning = false;
                setAllImportButtonsDisabled(false);
                return;
            }

            const total = data.total || 0;
            const processedTotal = data.processed_total || 0;
            const finished = !!data.finished;
            const nextOffset = data.next_offset || 0;

            let percent = 0;
            if (total > 0) {
                percent = Math.round((processedTotal / total) * 100);
            }

            updateProgress(percent);

            const messageText = 'Упрощенный импорт: обработано ' + processedTotal + ' из ' + total;
            showMessage(messageText, 'info');

            if (finished) {
                showMessage('Упрощенный импорт завершен. Обработано ' + processedTotal + ' из ' + total, 'success');
                isProductImportRunning = false;
                setAllImportButtonsDisabled(false);
            } else {
                processProductBatchSimple(sectionId, nextOffset);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Ошибка при выполнении запроса: ' + error.message, 'error');
            isProductImportRunning = false;
            setAllImportButtonsDisabled(false);
        });
    }
});
