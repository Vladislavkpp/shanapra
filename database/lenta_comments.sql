CREATE TABLE IF NOT EXISTS lenta_comments (
    idx INT NOT NULL AUTO_INCREMENT, -- // Унікальний ідентифікатор коментаря
    lenta_id INT NOT NULL, -- // ID публікації (lenta.idx)
    user_id INT NOT NULL, -- // ID автора коментаря (users.idx)
    parent_id INT DEFAULT NULL, -- // ID батьківського коментаря (для гілок)
    comment_text TEXT NOT NULL, -- // Текст коментаря
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- // Дата/час створення
    updated_at DATETIME DEFAULT NULL, -- // Дата/час редагування
    is_deleted TINYINT(1) NOT NULL DEFAULT 0, -- // М’яке видалення (1 = видалено)
    PRIMARY KEY (idx),
    KEY idx_lenta (lenta_id),
    KEY idx_user (user_id),
    KEY idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
