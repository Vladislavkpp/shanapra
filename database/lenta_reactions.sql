CREATE TABLE IF NOT EXISTS lenta_reactions (
    idx INT NOT NULL AUTO_INCREMENT, -- // Унікальний ідентифікатор реакції
    lenta_id INT NOT NULL, -- // ID публікації (lenta.idx)
    user_id INT NOT NULL, -- // ID користувача, який поставив реакцію (users.idx)
    reaction_type VARCHAR(32) NOT NULL DEFAULT 'like', -- // Тип реакції (наприклад: like)
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- // Дата/час створення реакції
    PRIMARY KEY (idx),
    UNIQUE KEY uniq_lenta_user (lenta_id, user_id),
    KEY idx_lenta (lenta_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
