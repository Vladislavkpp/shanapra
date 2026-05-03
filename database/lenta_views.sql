CREATE TABLE IF NOT EXISTS lenta_views (
    idx INT NOT NULL AUTO_INCREMENT, -- // Унікальний ідентифікатор перегляду
    lenta_id INT NOT NULL, -- // ID публікації (lenta.idx)
    user_id INT DEFAULT NULL, -- // ID користувача (users.idx) або NULL для гостей
    viewer_ip VARCHAR(45) DEFAULT NULL, -- // IP адреса глядача (для гостей/аналітики)
    user_agent VARCHAR(255) DEFAULT NULL, -- // User-Agent браузера
    view_date DATE NOT NULL, -- // Дата перегляду (для дедуплікації)
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- // Дата/час перегляду
    PRIMARY KEY (idx),
    UNIQUE KEY uniq_lenta_user_date (lenta_id, user_id, view_date),
    KEY idx_lenta (lenta_id),
    KEY idx_user (user_id),
    KEY idx_view_date (view_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
