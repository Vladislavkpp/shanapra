// Admin Panel JavaScript

// Кастомные уведомления
function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    const messageEl = notification.querySelector('.notification-message');
    const iconEl = notification.querySelector('.notification-icon');
    
    messageEl.textContent = message;
    
    // Удаляем старые классы
    notification.className = 'notification';
    
    if (type === 'success') {
        notification.classList.add('notification-success');
        iconEl.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    } else {
        notification.classList.add('notification-error');
        iconEl.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    
    notification.classList.add('show');
    
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// Поиск пользователей
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchUsers');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.users-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
    
    // Обработка формы редактирования
    const editForm = document.getElementById('editUserForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveUserData();
        });
    }
    
    // Обработка формы ролей
    const roleForm = document.getElementById('roleForm');
    if (roleForm) {
        roleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveUserRoles();
        });
    }
    
    // Закрытие модальных окон при клике вне их
    window.onclick = function(event) {
        const editModal = document.getElementById('editModal');
        const roleModal = document.getElementById('roleModal');
        if (event.target === editModal) {
            closeEditModal();
        }
        if (event.target === roleModal) {
            closeRoleModal();
        }
    }

    runAdminNotifyUI();
});

// Инициализация кастомного селекта
function initCustomSelect() {
    const select = document.getElementById('edit_activ');
    if (!select) return;
    if (!select.id) {
        select.id = 'admin-select-' + Math.random().toString(36).slice(2, 9);
    }
    
    // Проверяем, не создан ли уже кастомный селект
    const existingWrapper = select.parentNode.querySelector('.custom-select-wrapper');
    if (existingWrapper) {
        // Обновляем значение триггера если селект уже создан
        const trigger = existingWrapper.querySelector('.custom-select-trigger');
        if (trigger) {
            const selectedOption = select.options[select.selectedIndex];
            trigger.textContent = selectedOption ? selectedOption.textContent : 'Виберіть';
        }
        return;
    }
    
    // Создаем обертку
    const wrapper = document.createElement('div');
    wrapper.className = 'custom-select-wrapper';
    
    // Создаем триггер
    const trigger = document.createElement('div');
    trigger.className = 'custom-select-trigger';
    
    // Создаем контейнер опций
        const optionsBox = document.createElement('div');
        optionsBox.className = 'custom-options';
        wrapper.dataset.selectId = select.id;
    
    wrapper.appendChild(trigger);
    wrapper.appendChild(optionsBox);
    
    // Скрываем оригинальный селект
    select.style.display = 'none';
    select.parentNode.insertBefore(wrapper, select.nextSibling);
    
    // Функция обновления триггера
    function updateTrigger() {
        const selectedOption = select.options[select.selectedIndex];
        trigger.textContent = selectedOption ? selectedOption.textContent : 'Виберіть';
    }
    
    // Инициализация опций
    function initOptions() {
        optionsBox.innerHTML = '';
        Array.from(select.options).forEach(option => {
            const span = document.createElement('span');
            span.className = 'custom-option';
            span.textContent = option.textContent;
            span.dataset.value = option.value;
            const isDisabledOption = option.disabled || isPlaceholderOption(option);

            if (option.selected) {
                span.classList.add('selected');
            }

            if (isDisabledOption) {
                span.classList.add('disabled');
            } else {
                span.addEventListener('click', function() {
                select.value = option.value;
                updateTrigger();
                wrapper.classList.remove('open');
                optionsBox.style.display = 'none';
                
                // Обновляем выделение
                optionsBox.querySelectorAll('.custom-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                span.classList.add('selected');
                });
            }
            
            optionsBox.appendChild(span);
        });
    }
    
    // Открытие/закрытие
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = wrapper.classList.contains('open');
        
        // Закрываем все другие селекты
        document.querySelectorAll('.custom-select-wrapper').forEach(w => {
            if (w !== wrapper) {
                w.classList.remove('open');
                w.querySelector('.custom-options').style.display = 'none';
            }
        });
        
        wrapper.classList.toggle('open');
        optionsBox.style.display = isOpen ? 'none' : 'flex';
    });
    
    // Закрытие при клике вне
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            wrapper.classList.remove('open');
            optionsBox.style.display = 'none';
        }
    });
    
    // Инициализация
    initOptions();
    updateTrigger();
    
    // Обновление при изменении селекта
    select.addEventListener('change', updateTrigger);
}

function isPlaceholderOption(option) {
    if (!option) return false;
    const value = String(option.value ?? '');
    const text = String(option.textContent ?? '').trim().toLowerCase();
    if (value === '') return true;
    if (value === '0' && /обер/i.test(text)) return true;
    return false;
}

function initAdminNotifyUI() {
    const forms = document.querySelectorAll('.admin-notify-form');
    if (!forms.length) return;

    forms.forEach(form => {
        const selects = form.querySelectorAll('select');
        selects.forEach(select => initAdminCustomSelect(select));

        const targetTypeSelect = form.querySelector('select[name="target_type"]');
        if (targetTypeSelect) {
            const roleField = form.querySelector('[data-target-field="role"]');
            const userIdsField = form.querySelector('[data-target-field="user_ids"]');

            const updateTargets = () => {
                const value = targetTypeSelect.value;
                if (roleField) {
                    roleField.classList.toggle('is-hidden', value !== 'role');
                }
                if (userIdsField) {
                    userIdsField.classList.toggle('is-hidden', value !== 'user_ids');
                }
            };

            targetTypeSelect.addEventListener('change', updateTargets);
            updateTargets();
        }
    });
}

function initAdminCustomSelect(select) {
    if (!select) return;
    if (!select.id) {
        select.id = 'admin-select-' + Math.random().toString(36).slice(2, 9);
    }
    const existingWrapper = select.parentNode.querySelector('.custom-select-wrapper');
    if (existingWrapper) {
        const trigger = existingWrapper.querySelector('.custom-select-trigger');
        if (trigger) {
            const selectedOption = select.options[select.selectedIndex];
            trigger.textContent = selectedOption ? selectedOption.textContent : 'Оберіть';
        }
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'custom-select-wrapper';
    wrapper.dataset.selectId = select.id;

    const trigger = document.createElement('div');
    trigger.className = 'custom-select-trigger';

    const optionsBox = document.createElement('div');
    optionsBox.className = 'custom-options';

    wrapper.appendChild(trigger);
    wrapper.appendChild(optionsBox);

    select.style.display = 'none';
    select.parentNode.insertBefore(wrapper, select.nextSibling);

    function updateTrigger() {
        const selectedOption = select.options[select.selectedIndex];
        trigger.textContent = selectedOption ? selectedOption.textContent : 'Оберіть';
    }

    function initOptions() {
        optionsBox.innerHTML = '';
        Array.from(select.options).forEach(option => {
            const span = document.createElement('span');
            span.className = 'custom-option';
            span.textContent = option.textContent;
            span.dataset.value = option.value;
            const isDisabledOption = option.disabled || isPlaceholderOption(option);

            if (option.selected) {
                span.classList.add('is-selected');
            }

            if (isDisabledOption) {
                span.classList.add('disabled');
            } else {
                span.addEventListener('click', function(e) {
                    e.preventDefault();
                    select.value = option.value;
                    updateTrigger();
                    wrapper.classList.remove('open');
                    optionsBox.style.display = 'none';

                    optionsBox.querySelectorAll('.custom-option').forEach(opt => {
                        opt.classList.remove('is-selected');
                    });
                    span.classList.add('is-selected');
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            optionsBox.appendChild(span);
        });
    }

    trigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const isOpen = wrapper.classList.contains('open');
        document.querySelectorAll('.admin-notify-form .custom-select-wrapper').forEach(w => {
            if (w !== wrapper) {
                w.classList.remove('open');
                const box = w.querySelector('.custom-options');
                if (box) box.style.display = 'none';
            }
        });
        wrapper.classList.toggle('open');
        optionsBox.style.display = isOpen ? 'none' : 'flex';
    });

    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            wrapper.classList.remove('open');
            optionsBox.style.display = 'none';
        }
    });

    initOptions();
    updateTrigger();
    select.addEventListener('change', updateTrigger);
}

let adminNotifyInitDone = false;
function runAdminNotifyUI() {
    if (adminNotifyInitDone) return;
    adminNotifyInitDone = true;
    initAdminNotifyUI();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runAdminNotifyUI);
} else {
    runAdminNotifyUI();
}

// Открытие модального окна редактирования
function openEditModal(userId) {
    fetch(`/admin-get-user.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('edit_user_id').value = user.idx;
                
                // Устанавливаем значения с placeholder для правильной работы label
                const fnameInput = document.getElementById('edit_fname');
                fnameInput.value = user.fname || '';
                fnameInput.placeholder = user.fname ? ' ' : ' ';
                
                const lnameInput = document.getElementById('edit_lname');
                lnameInput.value = user.lname || '';
                lnameInput.placeholder = user.lname ? ' ' : ' ';
                
                const telInput = document.getElementById('edit_tel');
                telInput.value = user.tel || '';
                telInput.placeholder = user.tel ? ' ' : ' ';
                
                const emailInput = document.getElementById('edit_email');
                emailInput.value = user.email || '';
                emailInput.placeholder = user.email ? ' ' : ' ';
                
                const mestoInput = document.getElementById('edit_mesto');
                mestoInput.value = user.mesto || '';
                mestoInput.placeholder = user.mesto ? ' ' : ' ';
                
                const activSelect = document.getElementById('edit_activ');
                activSelect.value = user.activ || '0';
                
                const restInput = document.getElementById('edit_rest');
                restInput.value = user.rest || '';
                restInput.placeholder = user.rest ? ' ' : ' ';
                
                const rateInput = document.getElementById('edit_rate');
                rateInput.value = user.rate || '';
                rateInput.placeholder = user.rate ? ' ' : ' ';
                
                const cashInput = document.getElementById('edit_cash');
                cashInput.value = user.cash || '';
                cashInput.placeholder = user.cash ? ' ' : ' ';
                
                document.getElementById('editModal').style.display = 'block';
                
                // Инициализируем кастомный селект после открытия модального окна
                setTimeout(initCustomSelect, 100);
            } else {
                showNotification('Помилка завантаження даних користувача', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Помилка завантаження даних користувача', 'error');
        });
}

// Закрытие модального окна редактирования
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Сохранение данных пользователя
function saveUserData() {
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);
    
    fetch('/admin-update-user.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Дані користувача успішно оновлено!', 'success');
                closeEditModal();
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                showNotification('Помилка оновлення даних: ' + (data.message || 'Невідома помилка'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Помилка оновлення даних користувача', 'error');
        });
}

// Открытие модального окна ролей
function openRoleModal(userId, currentStatus) {
    document.getElementById('role_user_id').value = userId;
    
    // Сброс всех чекбоксов
    const checkboxes = document.querySelectorAll('.role-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
    
    // Установка текущих ролей
    checkboxes.forEach(cb => {
        const roleValue = parseInt(cb.value);
        if ((currentStatus & roleValue) === roleValue) {
            cb.checked = true;
        }
    });
    
    document.getElementById('roleModal').style.display = 'block';
}

// Закрытие модального окна ролей
function closeRoleModal() {
    document.getElementById('roleModal').style.display = 'none';
}

// Сохранение ролей пользователя
function saveUserRoles() {
    const userId = document.getElementById('role_user_id').value;
    const checkboxes = document.querySelectorAll('.role-checkbox:checked');
    let newStatus = 0;
    
    checkboxes.forEach(cb => {
        newStatus |= parseInt(cb.value);
    });
    
    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('status', newStatus);
    
    fetch('/update_user_status.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Ролі користувача успішно оновлено!', 'success');
                closeRoleModal();
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                showNotification('Помилка оновлення ролей: ' + (data.message || 'Невідома помилка'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Помилка оновлення ролей користувача', 'error');
        });
}
