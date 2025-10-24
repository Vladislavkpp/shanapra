<?php

class Chats
{
    private $dblink;

    public function __construct($dblink)
    {
        $this->dblink = $dblink;
    }

    /**
     * Получить сообщения чата
     */
    public function getMessages($chat_idx)
    {
        $chat_idx = (int)$chat_idx;
        $out = [];

        $res = mysqli_query($this->dblink, "SELECT * FROM chatsmsg WHERE chat_idx = $chat_idx ORDER BY idtadd ASC");
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $out[] = [
                    'idx' => (int)$r['idx'],
                    'chat_idx' => (int)$r['chat_idx'],
                    'sender_idx' => (int)$r['sender_idx'],
                    'message' => $r['message'],
                    'idtadd' => $r['idtadd'],
                ];
            }
        }
        return $out;
    }

    /**
     * Получить последнее сообщение
     */
    public function getLastMessage($chat_idx)
    {
        $chat_idx = (int)$chat_idx;
        $res = mysqli_query($this->dblink, "SELECT * FROM chatsmsg WHERE chat_idx = $chat_idx ORDER BY idtadd DESC LIMIT 1");
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    /**
     * Получить новые сообщения
     */
    public function getNewMessages($chat_idx, $last_msg_id)
    {
        $chat_idx = (int)$chat_idx;
        $last_msg_id = (int)$last_msg_id;
        $out = [];

        $res = mysqli_query(
            $this->dblink,
            "SELECT * FROM chatsmsg WHERE chat_idx = $chat_idx AND idx > $last_msg_id ORDER BY idtadd ASC"
        );
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $out[] = [
                    'idx' => (int)$r['idx'],
                    'chat_idx' => (int)$r['chat_idx'],
                    'sender_idx' => (int)$r['sender_idx'],
                    'message' => $r['message'],
                    'idtadd' => $r['idtadd'],
                ];
            }
        }
        return $out;
    }

    /**
     * Получить чаты по типу 1 — личные, 2 — рабочие, 3 — поддержка
     */
    public function getChatsByType($user_id, $type)
    {
        $user_id = (int)$user_id;
        $type = (int)$type;
        $out = [];

        $res = mysqli_query(
            $this->dblink,
            "SELECT * FROM chats 
             WHERE (user_one = $user_id OR user_two = $user_id)
             AND type = $type 
             ORDER BY idtadd DESC"
        );

        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $r['last_message'] = $this->getLastMessage($r['idx']);
                $out[] = $r;
            }
        }
        return $out;
    }

    /**
     * Получить чат по ID
     */
    public function getChatById($chat_idx)
    {
        $chat_idx = (int)$chat_idx;
        $res = mysqli_query($this->dblink, "SELECT * FROM chats WHERE idx = $chat_idx LIMIT 1");
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    /**
     * Получить все чаты пользователя
     */
    public function getUserChats($user_id, $exclude_type = 0)
    {
        $user_id = (int)$user_id;
        $exclude_type = (int)$exclude_type;
        $out = [];

        $sql = "SELECT * FROM chats WHERE (user_one = $user_id OR user_two = $user_id)";
        if ($exclude_type > 0) $sql .= " AND type != $exclude_type";
        $sql .= " ORDER BY idtadd DESC";

        $res = mysqli_query($this->dblink, $sql);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $r['last_message'] = $this->getLastMessage($r['idx']);
                $out[] = $r;
            }
        }
        return $out;
    }

    /**
     * Создать чат между двумя пользователями
     */
    public function createChat($user_one, $user_two, $type = 1)
    {
        $user_one = (int)$user_one;
        $user_two = (int)$user_two;
        $type = (int)$type;

        // Проверка существующего чата
        $sql = "SELECT idx FROM chats 
                WHERE ((user_one = $user_one AND user_two = $user_two) 
                    OR (user_one = $user_two AND user_two = $user_one))
                AND type = $type LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);
        if ($res && $r = mysqli_fetch_assoc($res)) {
            return (int)$r['idx'];
        }

        $sql = "INSERT INTO chats (user_one, user_two, type, idtadd)
                VALUES ($user_one, $user_two, $type, NOW())";
        mysqli_query($this->dblink, $sql);

        return (int)mysqli_insert_id($this->dblink);
    }

    /**
     * Создать гостевой чат (для поддержки)
     */
    public function createGuestChat($guest_id, $support_id = -1, $type = 3)
    {
        $guest_id = (int)$guest_id;
        $support_id = (int)$support_id;
        $type = (int)$type;

        $res = mysqli_query($this->dblink, "SELECT * FROM chats WHERE user_one = $guest_id AND type = $type LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $chat = mysqli_fetch_assoc($res);
            return (int)$chat['idx'];
        }

        $sql = "INSERT INTO chats (user_one, user_two, type, idtadd)
                VALUES ($guest_id, $support_id, $type, NOW())";
        $ok = mysqli_query($this->dblink, $sql);
        if (!$ok) {
            error_log('createGuestChat failed: ' . mysqli_error($this->dblink));
            return 0;
        }

        return (int)mysqli_insert_id($this->dblink);
    }

    /**
     * Получить гостевой чат по пользователю
     */
    public function getGuestChatByUser($guest_id, $type = 3)
    {
        $guest_id = (int)$guest_id;
        $type = (int)$type;
        $res = mysqli_query($this->dblink, "SELECT * FROM chats WHERE user_one = $guest_id AND type = $type LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            return mysqli_fetch_assoc($res);
        }
        return null;
    }


    /**
     * Получить чат поддержки для авторизованного пользователя
     */
    public function getUserSupportChat($user_id, $type = 3)
    {
        $user_id = (int)$user_id;
        $type = (int)$type;

        $res = mysqli_query(
            $this->dblink,
            "SELECT * FROM chats WHERE user_one = $user_id AND type = $type LIMIT 1"
        );

        if ($res && mysqli_num_rows($res) > 0) {
            return mysqli_fetch_assoc($res);
        }
        return null;
    }

    /**
     * Создать чат поддержки для авторизованного пользователя
     */
    public function createSupportChat($user_id, $type = 3)
    {
        $user_id = (int)$user_id;
        $type = (int)$type;
        $support_id = -1;

        $existing = $this->getUserSupportChat($user_id, $type);
        if ($existing) {
            return (int)$existing['idx'];
        }

        $sql = "INSERT INTO chats (user_one, user_two, type, idtadd)
                VALUES ($user_id, $support_id, $type, NOW())";
        mysqli_query($this->dblink, $sql);

        return (int)mysqli_insert_id($this->dblink);
    }

    public function getSupportChatsList()
    {
        $out = [];
        $res = mysqli_query(
            $this->dblink,
            "SELECT * FROM chats 
         WHERE type = 3 
         AND user_two = -1 
         ORDER BY idtadd DESC"
        );

        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $r['last_message'] = $this->getLastMessage($r['idx']);
                $out[] = $r;
            }
        }
        return $out;
    }


    /**
     * Добавить сообщение в чат
     */
    public function addMessage($chat_idx, $sender_idx, $message, $email = null)
    {
        $chat_idx = (int)$chat_idx;
        $sender_idx = (int)$sender_idx;
        $message = mysqli_real_escape_string($this->dblink, $message);
        $email = $email ? mysqli_real_escape_string($this->dblink, $email) : '';

        return mysqli_query(
            $this->dblink,
            "INSERT INTO chatsmsg (chat_idx, sender_idx, message, idtadd)
             VALUES ($chat_idx, $sender_idx, '$message', NOW())"
        );
    }
}
