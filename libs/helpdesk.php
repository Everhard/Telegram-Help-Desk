<?php

class Scenario {
    public function __construct($message, $user) {
        
    }
}

class Message {

    /**
     * Parsing update telegram object.
     *
     * @param string     $input         Raw input data.
     *
     */
    public function __construct($input) {
        
        $this->update = json_decode($input, true);
        $this->sender_id = $this->update['message']['from']['id'];
        $this->sender_first_name = $this->update['message']['from']['first_name'];
        $this->sender_last_name = $this->update['message']['from']['last_name'];
        $this->text = $this->update['message']['text'];
        
    }
    
    public function getSenderId() {
        return $this->sender_id;
    }
    
    public function getSenderFirstName() {
        return $this->sender_first_name;
    }
    
    public function getSenderLastName() {
        return $this->sender_last_name;
    }
    
    public function getText() {
        return $this->text;
    }


    private $update;
    private $sender_id;
    private $sender_first_name;
    private $sender_last_name;
    private $text;
}

class History {
    public function __construct($bot) {
        $this->DBH = DB::getInstance();
        
        $this->bot = $bot;
        
        $stmt = $this->DBH->prepare("SELECT * FROM history WHERE bot_id = :bot_id");
        $stmt->bindParam(':bot_id', $this->bot->getId());
        $stmt->execute();
        
        $this->history = $stmt->fetchAll();
    }
    
    public function writeRequest($user) {
        
        /*
         * If it is help desk center bot then skip.
         */
        if ($this->bot->getId() == 1) {
            return;
        }
        
        $stmt = $this->DBH->prepare("SELECT * FROM history WHERE client_telegram_id = :client_telegram_id AND bot_id = :bot_id");
        $stmt->bindParam(':client_telegram_id', $user->getUserTelegramId());
        $stmt->bindParam(':bot_id', $this->bot->getId());
        $stmt->execute();
        
        /*
         * If we already have a client record then skip.
         */
        if ($stmt->fetch()) {
            return;
        }
        
        $stmt = $this->DBH->prepare("INSERT INTO history (client_name, client_telegram_id, bot_id) VALUES (:client_name, :client_telegram_id, :bot_id)");
        
        $stmt->bindParam(':client_name', $user->getCommandFullName());
        $stmt->bindParam(':client_telegram_id', $user->getUserTelegramId());
        $stmt->bindParam(':bot_id', $this->bot->getId());
        $stmt->execute();
        
        $this->history[] = array(
            "client_name" => $user->getCommandFullName(),
            "client_telegram_id" => $user->getUserTelegramId(),
            "bot_id" =>  $this->bot->getId(),
        );
    }

    private $DBH;
    private $bot;
    private $history;
}

class Bot {
    public function __construct() {
        $this->DBH = DB::getInstance();
        
        $this->id = (isset($_GET['id'])) ? $_GET['id'] : 1;

        $stmt = $this->DBH->prepare("SELECT * FROM bots WHERE id = :id");
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        if ($bot_array = $stmt->fetch()) {
            
            $this->manager_telegram_id = $bot_array['manager_id'];
            $this->token = $bot_array['token'];
            $this->telegram = new TelegramBot($this->token);

        } else {
            throw new Exception("Unknow bot ID!");
        }
    }
    
    public function getManager() {
        return new User($this->getManagerTelegramId());
    }
    
    public function getId() {
        return $this->id;
    }
    
    public function getManagerTelegramId() {
        return $this->manager_telegram_id;
    }
    
    public function getTelegram() {
        return $this->telegram;
    }
    
    public function isMain() {
        return ($this->id == 1) ? true : false;
    }
    
    private $DBH;
    private $id;
    private $manager_telegram_id;
    private $token;
    private $telegram;
}

class User {
    public function __construct($user_data) {
        $this->DBH = DB::getInstance();
        
        if (is_array($user_data)) {
            $this->first_name = $user_data['first-name'];
            $this->last_name = $user_data['last-name'];
            $this->user_telegram_id = $user_data['user-telegram-id'];
        } else {
            $this->user_telegram_id = $user_data;
        }
        
        if (!$this->doesUserExist()) {
            if (is_array($user_data)) {
                $this->storeNewUser();
            }
            else {
                throw new Exception("Unknow user telegram ID!");
            }
        }
    }
    
    public function sendMessage($message, $bot) {
        $bot->getTelegram()->sendMessage($this->user_telegram_id, $message);
    }
    
    public function isClient($bot) {
        return !$this->isManager($bot);
    }
    
    public function isManager($bot) {
        return ($this->is_manager && $bot->getManagerTelegramId() == $this->getUserTelegramId()) ? true : false;
    }
    
    public function isAdmin() {
        return ($this->is_admin) ? true : false;
    }
    
    public function getUserTelegramId() {
        return $this->user_telegram_id;
    }
    
    public function getFullName() {
        return $this->getFirstName()." ".$this->getLastName();
    }

    public function getCommandFullName() {
        $command_full_name = $this->getFirstName()."_".$this->getLastName();
        return str_replace(" ", "_", $command_full_name);
    }

    public function getFirstName() {
        return $this->first_name;
    }

    public function getLastName() {
        return $this->last_name;
    }
    
    public function hasActiveScenario() {
        return ($this->scenario != "no") ? true : false;
    }
    
    public function getScenario() {
        return $this->scenario;
    }
    
    public function setScenario($scenario) {
        $this->scenario = $scenario;
        
        /*
         * Save to DB:
         */
        $stmt = $this->DBH->prepare("UPDATE users SET scenario = :scenario WHERE id = :user_id");
        $stmt->bindParam(':scenario', $this->scenario);
        $stmt->bindParam(':user_id', $this->id);
        $stmt->execute();
    }
    
    public function setScenarioDone() {
        $this->setScenario("no");
    }
    
    public function makeAdmin() {
        $this->DBH->exec("UPDATE users SET is_admin = 0");
        $stmt = $this->DBH->prepare("UPDATE users SET is_admin = 1 WHERE id = :user_id");
        $stmt->bindParam(':user_id', $this->id);
        $stmt->execute();
    }

    private function doesUserExist() {
        $stmt = $this->DBH->prepare("SELECT * FROM users WHERE user_telegram_id = :user_telegram_id");
        $stmt->bindParam(':user_telegram_id', $this->user_telegram_id);
        $stmt->execute();
        
        if ($user_array = $stmt->fetch()) {
            $this->id = $user_array['id'];
            $this->first_name = $user_array['first_name'];
            $this->last_name = $user_array['last_name'];
            $this->user_telegram_id = $user_array['user_telegram_id'];
            $this->is_manager = $user_array['is_manager'];
            $this->is_admin = $user_array['is_admin'];
            $this->scenario = $user_array['scenario'];
            return true;
        }
        
        return false;
    }
    
    private function storeNewUser() {
        
        $this->is_manager = 0;
        $this->is_admin = 0;
        $this->scenario = 'no';
        
        $stmt = $this->DBH->prepare("INSERT INTO users (first_name, last_name, user_telegram_id, is_manager, is_admin, scenario) VALUES (:first_name, :last_name, :user_telegram_id, :is_manager, :is_admin, :scenario)");
        
        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':user_telegram_id', $this->user_telegram_id);
        $stmt->bindParam(':is_manager', $this->is_manager);
        $stmt->bindParam(':is_admin', $this->is_admin);
        $stmt->bindParam(':scenario', $this->scenario);
        
        $stmt->execute();

        $this->id = $this->DBH->lastInsertId();
    }
    
    private $DBH;
    private $id;
    private $first_name;
    private $last_name;
    private $user_telegram_id;
    private $is_manager;
    private $is_admin;
    private $scenario;
}

class DB {
    public static function getInstance() {
        if (isset(self::$DBH)) {
            return self::$DBH;
        }
        
        try {
            self::$DBH = new PDO("sqlite:./libs/db.sqlite");
        }
        catch (PDOException $e) {
            exit("Database error: " . $e->getMessage());
        }
        
        return self::$DBH;
    }
    
    private static $DBH;
    
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}
}