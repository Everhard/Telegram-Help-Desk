<?php

if ($user->hasActiveScenario($bot)) {

    if ($message->getSenderText() == "/cancel") {
        $user->setScenarioDone($bot);
        $answer = "Текущее действие было отменено!\n";
    }

    else switch($user->getScenario($bot)) {

        case "wait-password-to-become-admin":
            if ($message->getSenderText() == $config['admin-password']) {

                $answer = "Спасибо, ".$user->getFirstName()."! Теперь Вы новый администратор!\n";
                $user->makeAdmin();
                $user->setScenarioDone($bot);
            }
            else {

                $answer = "Вы указали неправильный пароль!\n";
                $answer .= "Попробуйте ещё раз! Но в этот раз будьте внимательней!\n";
            }

            break;

        case "wait-bot-token":

            $answer = "Ошибка подключения нового бота! Попробуйте ещё раз!\n";

            $response = json_decode(file_get_contents("https://api.telegram.org/bot".$message->getSenderText()."/setWebhook?url=".$config['gateway-url']));
            file_put_contents('input.txt', print_r($response, true), FILE_APPEND);
            if ($response->ok) {
                if ($user->addNewBot($message->getSenderText())) {
                    $answer = "Новый бот подключен!\n";
                    $user->setScenarioDone($bot);
                }
            }

            break;
            
        case "waiting-number-to-delete-bot":
            
            $DBH = DB::getInstance();
            $stmt = $DBH->prepare("SELECT * FROM bots WHERE manager_id = :user_telegram_id");
            $stmt->bindParam(':user_telegram_id', $user->getUserTelegramId());
            $stmt->execute();

            $bots = $stmt->fetchAll();

            if (count($bots) > 0) {
                $counter = 1;

                foreach ($bots as $bot_row) {
                    
                    if ($message->getSenderText() == $counter) {
                        $DBH->exec("DELETE FROM bots WHERE id = ".$bot_row['id']);
                        
                        break;
                    }
                    
                    $counter++;
                }
            }
            
            
            $user->setScenarioDone($bot);
            $answer = "Бот был удалён!\n";
            
            break;
    }

    $user->sendMessage($answer, $bot);
}

else {

    $answer = "Неверная команда!\n/start - просмотреть весь список команд.";

    if ($message->getSenderText() == "/start") {

        $answer = "Управление ботом Help Desk Center:\n\n";
        $answer .= "/become_admin - получить права администратора.\n";
        $answer .= "/who_is_admin - узнать, кто администратор.\n";
        $answer .= "/add_bot - подключить нового бота.\n";
        $answer .= "/show_bots - просмотреть список ботов.\n";
        $answer .= "/delete_bot - удалить бота.\n";
        $answer .= "/cancel - отменить текущую операцию.";

    }

    if ($message->getSenderText() == "/cancel") {

        $answer = "В данный момент команда /cancel неприменима.\n";

    }

    if ($message->getSenderText() == "/become_admin") {

        $answer = "Пожалуйста, введите пароль суперпользователя:\n";
        $user->setScenario("wait-password-to-become-admin", $bot);
    }
    
    if ($message->getSenderText() == "/who_is_admin") {

        $admin = new Administrator();
        $answer = "В данный момент администратором является ".$admin->getFullName().".";
    }

    if ($message->getSenderText() == "/add_bot") {
            $answer = "Укажите токен нового бота:\n";
            $user->setScenario("wait-bot-token", $bot);
    }
    
    if ($message->getSenderText() == "/show_bots") {
        
       $DBH = DB::getInstance();
       $stmt = $DBH->prepare("SELECT * FROM bots WHERE manager_id = :user_telegram_id");
       $stmt->bindParam(':user_telegram_id', $user->getUserTelegramId());
       $stmt->execute();
       
       $bots = $stmt->fetchAll();
       
       if (count($bots) > 0) {
           
           $answer = "Список подключённых ботов:\n";
           $counter = 1;
           
           foreach ($bots as $bot_row) {
               list($bot_owner_id, $bot_hash) = explode(":", $bot_row['token']);
               $answer .= "$counter. Бот ".$bot_hash."\n";
               $counter++;
           }
       }
       
       else {
            $answer = "У Вас нет ни одного подключенного бота.";
       }
       
    }
    
    if ($message->getSenderText() == "/delete_bot") {
        
        $DBH = DB::getInstance();
        $stmt = $DBH->prepare("SELECT * FROM bots WHERE manager_id = :user_telegram_id");
        $stmt->bindParam(':user_telegram_id', $user->getUserTelegramId());
        $stmt->execute();
        
        $bots = $stmt->fetchAll();
       
        if (count($bots) > 0) {

            $answer = "Укажите номер бота, который нужно удалить:\n";
            
            $buttons = array();
            
            for ($i = 1; $i <= count($bots); $i++) {
                $buttons[] = "$i";
            }

            $user->sendMessage($answer, $bot, $buttons);
            $user->setScenario("waiting-number-to-delete-bot", $bot);
            
            exit();
        }

        else {
             $answer = "У Вас нет ни одного подключенного бота.";
        }
    }
    
    $user->sendMessage($answer, $bot);
}