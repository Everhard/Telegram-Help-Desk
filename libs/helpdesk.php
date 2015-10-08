<?php

define("MAIN_BOT", 1);

class Message {

    /**
     * Parsing update telegram object.
     *
     * @param string     $input         Raw input data.
     *
     */
    public function __construct($input) {
        
        $this->update_object = json_decode($input, true);
        
        $this->sender_id            = $this->update_object['message']['from']['id'];
        $this->sender_first_name    = $this->update_object['message']['from']['first_name'];
        $this->sender_last_name     = $this->update_object['message']['from']['last_name'];
        $this->sender_text          = $this->update_object['message']['text'];
        
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
    
    public function getSenderText() {
        return $this->sender_text;
    }


    private $update_object;
    private $sender_id;
    private $sender_first_name;
    private $sender_last_name;
    private $sender_text;
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
        if ($this->bot->getId() == MAIN_BOT) {
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
        
        $stmt = $this->DBH->prepare("INSERT INTO history (client_name, client_telegram_id, bot_id, in_process) VALUES (:client_name, :client_telegram_id, :bot_id, '0')");
        
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
    
    public function getHistoryByName($name) {
        $stmt = $this->DBH->prepare("SELECT * FROM history WHERE client_name = :client_name AND bot_id = :bot_id");
        $stmt->bindParam(':client_name', $name);
        $stmt->bindParam(':bot_id', $this->bot->getId());
        $stmt->execute();
        
        if ($history = $stmt->fetch()) {
            return new HistoryRecord($history);
        }
        
        return false;
    }
    
    public function getHistoryByInProcess() {
        $stmt = $this->DBH->prepare("SELECT * FROM history WHERE in_process = 1 AND bot_id = :bot_id");
        $stmt->bindParam(':bot_id', $this->bot->getId());
        $stmt->execute();
        
        if ($history = $stmt->fetch()) {
            return new HistoryRecord($history);
        }
        
        return false;
    }

    public function getArrayOfNames() {
        $names = array();
        foreach ($this->history as $history) {
            $names[] = "/".$history['client_name'];
        }
        return $names;
    }

    private $DBH;
    private $bot;
    private $history;
}

class HistoryRecord {
    
    public function __construct($row) {
        $this->DBH = DB::getInstance();
        
        $this->id = $row['id'];
        $this->client_name = $row['client_name'];
        $this->client_telegram_id = $row['client_telegram_id'];
        $this->bot_id = $row['bot_id'];
        $this->in_process = $row['in_process'];
    }

    public function makeStatusInProcess() {
        $stmt = $this->DBH->prepare("UPDATE history SET in_process = 1 WHERE id = :id");
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    public function getUser() {
        return new User($this->client_telegram_id);
    }
    
    public function delete() {
        $stmt = $this->DBH->prepare("DELETE FROM history WHERE id = :id");
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    private $DBH;
    private $id;
    private $client_name;
    private $client_telegram_id;
    private $bot_id;
    private $in_process;
}

class Bot {
    public function __construct($id = 0) {
        $this->DBH = DB::getInstance();
        
        if ($id == 0) {
            $this->id = (isset($_GET['id'])) ? $_GET['id'] : MAIN_BOT;
        }
        else {
            $this->id = $id;
        }

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
        return ($this->id == MAIN_BOT) ? true : false;
    }
    
    private $DBH;
    private $id;
    private $manager_telegram_id;
    private $token;
    private $telegram;
}

class Scenario {
    
    public function __construct($user_telegram_id, $bot_id) {
        $this->DBH = DB::getInstance();
        
        $this->user_telegram_id = $user_telegram_id;
        $this->bot_id = $bot_id;
        
        $stmt = $this->DBH->prepare("SELECT * FROM scenarios WHERE user_telegram_id = :user_telegram_id AND bot_id = :bot_id");
        $stmt->bindParam(':user_telegram_id', $this->user_telegram_id);
        $stmt->bindParam(':bot_id', $this->bot_id);
        $stmt->execute();
        
        if ($scenario_array = $stmt->fetch()) {
            
            $this->id = $scenario_array['id'];
            $this->user_telegram_id = $scenario_array['user_telegram_id'];
            $this->bot_id = $scenario_array['bot_id'];
            $this->scenario = $scenario_array['scenario'];
            $this->does_exist = true;
            
        } else {
            
            $this->does_exist = false;
            
        }
        
    }
    
    public function doesExist() {
        return $this->does_exist;
    }
    
    public function getScenario() {
        return $this->scenario;
    }
    
    public function setScenario($scenario_text) {
        $this->deleteScenario();
        $stmt = $this->DBH->prepare("INSERT INTO scenarios (user_telegram_id, bot_id, scenario) VALUES (:user_telegram_id, :bot_id, :scenario)");
        $stmt->bindParam(':user_telegram_id', $this->user_telegram_id);
        $stmt->bindParam(':bot_id', $this->bot_id);
        $stmt->bindParam(':scenario', $scenario_text);
        $stmt->execute();
    }
    
    public function deleteScenario() {
        $stmt = $this->DBH->prepare("DELETE FROM scenarios WHERE user_telegram_id = :user_telegram_id AND bot_id = :bot_id");
        $stmt->bindParam(':user_telegram_id', $this->user_telegram_id);
        $stmt->bindParam(':bot_id', $this->bot_id);
        $stmt->execute();
    }

    private $DBH;
    private $id;
    private $user_telegram_id;
    private $bot_id;
    private $scenario;
    private $does_exist;
}

class User {
    public function __construct($user_telegram_id, $first_name = false, $last_name = false) {
        
        $this->DBH = DB::getInstance();
        
        $this->user_telegram_id = $user_telegram_id;
        
        if (!$first_name && !$last_name) {
            if ($this->doesExist()) {
                return;
            }
        }
        
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        
        
        if (!$this->doesExist()) {
            $this->storeNewUser();
        }
    }
    
    public function sendMessage($message, $bot, $keyboard = false) {
        
        if ($keyboard) {
            $keyboard_string = json_encode(array("keyboard"=>array($keyboard), "resize_keyboard" => true, "one_time_keyboard" => true));
            $bot->getTelegram()->sendMessage($this->user_telegram_id, $message, null, false, null, $keyboard_string);
        }
        else {
            $bot->getTelegram()->sendMessage($this->user_telegram_id, $message);
        }
        
    }
    
    public function isClient($bot) {
        return !$this->isManager($bot);
    }
    
    public function isManager($bot = false) {
        if ($bot) {
            return ($this->is_manager && $bot->getManagerTelegramId() == $this->getUserTelegramId()) ? true : false;
        } else {
            return ($this->is_manager) ? true : false;
        }
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
        $command_full_name = $this->getFirstName()."-".$this->getLastName();
        return str_replace(" ", "-", $command_full_name);
    }

    public function getFirstName() {
        return $this->first_name;
    }

    public function getLastName() {
        return $this->last_name;
    }
    
    public function hasActiveScenario($bot) {
        $scenario = new Scenario($this->user_telegram_id, $bot->getId());
        return $scenario->doesExist();
    }
    
    public function getScenario($bot) {
        $scenario = new Scenario($this->user_telegram_id, $bot->getId());
        return $scenario->getScenario();
    }
    
    public function setScenario($scenario_text, $bot) {
        $scenario = new Scenario($this->user_telegram_id, $bot->getId());
        $scenario->setScenario($scenario_text);
    }
    
    public function setScenarioDone($bot) {
        $scenario = new Scenario($this->user_telegram_id, $bot->getId());
        $scenario->deleteScenario();
    }
    
    public function makeAdmin() {
        $admin = new Administrator();
        $admin->resetManagerRequests();
        
        $this->DBH->exec("UPDATE users SET is_admin = 0");
        $stmt = $this->DBH->prepare("UPDATE users SET is_admin = 1 WHERE id = :user_id");
        $stmt->bindParam(':user_id', $this->id);
        $stmt->execute();
    }
    
    public function makeManager() {
        $stmt = $this->DBH->prepare("UPDATE users SET is_manager = 1 WHERE id = :user_id");
        $stmt->bindParam(':user_id', $this->id);
        $stmt->execute();
    }
    
    public function addNewBot($token) {
        
        global $config;
        
        $stmt = $this->DBH->prepare("INSERT INTO bots (manager_id, token) VALUES (:manager_id, :token)");
        
        $stmt->bindParam(':manager_id', $this->getUserTelegramId());
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        $id = $this->DBH->lastInsertId();
        
        return file_get_contents("https://api.telegram.org/bot$token/setWebhook?url=".$config['gateway-url']."?id=$id");
    }

    protected function doesExist() {
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
            return true;
        }
        
        return false;
    }
    
    protected function storeNewUser() {
        
        $this->is_manager = 0;
        $this->is_admin = 0;
        $this->scenario = false;
        
        $stmt = $this->DBH->prepare("INSERT INTO users (first_name, last_name, user_telegram_id, is_manager, is_admin) VALUES (:first_name, :last_name, :user_telegram_id, :is_manager, :is_admin)");
        
        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':user_telegram_id', $this->user_telegram_id);
        $stmt->bindParam(':is_manager', $this->is_manager);
        $stmt->bindParam(':is_admin', $this->is_admin);
        
        $stmt->execute();

        $this->id = $this->DBH->lastInsertId();
    }
    
    protected $DBH;
    protected $id;
    protected $first_name;
    protected $last_name;
    protected $user_telegram_id;
    protected $is_manager;
    protected $is_admin;
}

class Administrator extends User {
    public function __construct() {
        
        $db = DB::getInstance();
        $stmt = $db->query("SELECT * FROM users WHERE is_admin = 1");
        
        if ($admin = $stmt->fetch()) {
            parent::__construct($admin['user_telegram_id']);
        }
        else {
            throw new Exception("Admin doesn't exist!");
        }
    }
    
    public function hasManagerRequests() {
        $stmt = $this->DBH->query("SELECT COUNT(*) FROM manager_requests WHERE asked = 0"); 
        return ($stmt->fetchColumn() > 0) ? true : false;
    }
    
    public function storeManagerRequest($user) {
        
        $stmt = $this->DBH->prepare("SELECT COUNT(*) FROM manager_requests WHERE user_telegram_id = :user_telegram_id");
        $stmt->bindParam(':user_telegram_id', $user->getUserTelegramId());
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            $request_time = time();
        
            $stmt = $this->DBH->prepare("INSERT INTO manager_requests (user_telegram_id, request_time, asked) VALUES (:user_telegram_id, :request_time, 0)");
            $stmt->bindParam(':user_telegram_id', $user->getUserTelegramId());
            $stmt->bindParam(':request_time', $request_time);
            $stmt->execute();
        }
    }
    
    public function resetManagerRequests() {
        $this->DBH->query("DELETE FROM manager_requests");
        $this->DBH->query("DELETE FROM scenarios WHERE scenario='manager-pending-decision'");
        
    }


    public function makeManagerRequestAsk() {
        $stmt = $this->DBH->query("SELECT COUNT(*) FROM manager_requests WHERE asked = 1");
        if ($stmt->fetchColumn() == 0) {
            $stmt = $this->DBH->query("SELECT * FROM manager_requests WHERE asked = 0");
            if ($request = $stmt->fetch()) {
                $stmt = $this->DBH->prepare("UPDATE manager_requests SET asked = 1 WHERE id = :id");
                $stmt->bindParam(':id', $request['id']);
                $stmt->execute();
                
                $user = new User($request['user_telegram_id']);
                $this->sendMessage("Пользователь ".$user->getFullName()." хочет стать менеджером!", array("Одобрить", "Отклонить"));
                $this->setScenario("manager-pending-decision", new Bot(MAIN_BOT));
            }
        }
    }
    
    public function makeManagerApprove() {
        $stmt = $this->DBH->query("SELECT * FROM manager_requests WHERE asked = 1");
        if ($request = $stmt->fetch()) {
            $user = new User($request['user_telegram_id']);
            $user->makeManager();
            
            $bot = new Bot(MAIN_BOT);
            $user->sendMessage("Администратор одобрил Ваш запрос на получение прав менеджера!", $bot);
            
            $stmt = $this->DBH->prepare("DELETE FROM manager_requests WHERE id = :id");
            $stmt->bindParam(':id', $request['id']);
            $stmt->execute();
        }
    }
    
    public function makeManagerDecline() {
        $stmt = $this->DBH->query("SELECT * FROM manager_requests WHERE asked = 1");
        if ($request = $stmt->fetch()) {
            $user = new User($request['user_telegram_id']);
            
            $bot = new Bot(MAIN_BOT);
            $user->sendMessage("Администратор отклонил Ваш запрос на получение прав менеджера!", $bot);
            
            $stmt = $this->DBH->prepare("DELETE FROM manager_requests WHERE id = :id");
            $stmt->bindParam(':id', $request['id']);
            $stmt->execute();
        }
    }
    
    public function sendMessage($message, $keyboard = false) {
        $bot = new Bot(MAIN_BOT);
        
        if ($keyboard) {
            $keyboard_string = json_encode(array("keyboard"=>array($keyboard), "resize_keyboard" => true, "one_time_keyboard" => true));
            $bot->getTelegram()->sendMessage($this->user_telegram_id, $message, null, false, null, $keyboard_string);
        }
        else {
            $bot->getTelegram()->sendMessage($this->user_telegram_id, $message);
        }
        
    }
}

/**
 * Database singleton class.
 */
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