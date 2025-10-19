<?php

class Chats
{
    private $dblink;

    public function __construct($dblink)
    {
        $this->dblink = $dblink;
    }

    // Создать новый чат или вернуть существующий
    public function createChat($user_one, $user_two, $type = 1) // 1 - персональний, 2 - робочий
    {
        $user_one = (int)$user_one;
        $user_two = (int)$user_two;
        $type = (int)$type;

        // Проверка существует ли уже такой чат
        $sql = "SELECT idx FROM chats 
                WHERE ((user_one = $user_one AND user_two = $user_two) 
                OR (user_one = $user_two AND user_two = $user_one))
                AND type = $type 
                LIMIT 1";

        $res = mysqli_query($this->dblink, $sql);

        if ($res && $r = mysqli_fetch_assoc($res)) {
            return $r['idx'];
        }

        $sql = "INSERT INTO chats (user_one, user_two, type, idtadd)
                VALUES ($user_one, $user_two, $type, NOW())";
        mysqli_query($this->dblink, $sql);

        return mysqli_insert_id($this->dblink);
    }

    // Получить все чаты пользователя
    public function getUserChats($user_id)
    {
        $user_id = (int)$user_id;
        $out = [];

        $sql = "SELECT * FROM chats 
                WHERE user_one = $user_id OR user_two = $user_id 
                ORDER BY idtadd DESC";

        $res = mysqli_query($this->dblink, $sql);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $r['last_message'] = $this->getLastMessage($r['idx']);
                $out[] = $r;
            }
        }

        return $out;
    }

    // Получить чат по айди
    public function getChatById($chat_idx)
    {
        $chat_idx = (int)$chat_idx;
        $sql = "SELECT * FROM chats WHERE idx = $chat_idx LIMIT 1";
        $res = mysqli_query($this->dblink, $sql);

        if ($res && $r = mysqli_fetch_assoc($res)) {
            return $r;
        }
        return null;
    }

    // Добавить сообщение
    public function addMessage($chat_idx, $sender_idx, $message)
    {
        $chat_idx = (int)$chat_idx;
        $sender_idx = (int)$sender_idx;
        $message = trim(mysqli_real_escape_string($this->dblink, $message));

        if ($message === '') {
            return false;
        }

        $sql = "INSERT INTO chatsmsg (chat_idx, sender_idx, message, idtadd)
                VALUES ($chat_idx, $sender_idx, '$message', NOW())";

        return mysqli_query($this->dblink, $sql);
    }

    // Получить все сообщения чата
    public function getMessages($chat_idx)
    {
        $chat_idx = (int)$chat_idx;
        $out = [];

        $sql = "SELECT * FROM chatsmsg 
            WHERE chat_idx = $chat_idx 
            ORDER BY idtadd ASC";

        $res = mysqli_query($this->dblink, $sql);
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

    // Получить последнее сообщение
    public function getLastMessage($chat_idx)
    {
        $chat_idx = (int)$chat_idx;

        $sql = "SELECT message, sender_idx, idtadd 
                FROM chatsmsg 
                WHERE chat_idx = $chat_idx 
                ORDER BY idtadd DESC 
                LIMIT 1";

        $res = mysqli_query($this->dblink, $sql);
        if ($res && $r = mysqli_fetch_assoc($res)) {
            return $r;
        }
        return null;
    }

    // getNewMessages
    public function getNewMessages($chat_idx, $last_msg_id)
    {
        $chat_idx = (int)$chat_idx;
        $last_msg_id = (int)$last_msg_id;
        $out = [];

        $sql = "SELECT * FROM chatsmsg 
            WHERE chat_idx = $chat_idx AND idx > $last_msg_id 
            ORDER BY idtadd ASC";

        $res = mysqli_query($this->dblink, $sql);
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

    // Пометить сообщения как прочитанные
    public function markAsRead($chat_idx, $user_id)
    {
        $chat_idx = (int)$chat_idx;
        $user_id = (int)$user_id;

        $sql = "UPDATE chatsmsg 
                SET is_read = 1 
                WHERE chat_idx = $chat_idx 
                AND sender_idx != $user_id";
        mysqli_query($this->dblink, $sql);
    }
}
