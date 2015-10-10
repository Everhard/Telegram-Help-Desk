<?php

if ($user->hasActiveScenario($bot)) {
    
    if ($message->getSenderText() == "/cancel") {
        $user->setScenarioDone($bot);
        $answer = "Текущее действие было отменено!\n";
        $user->sendMessage($answer, $bot);
    }
    
    switch($user->getScenario($bot)) {
        
        case "manager-pending-decision":
            
            $admin = new Administrator();
            if ($message->getSenderText() == "Одобрить") {
                $admin->makeManagerApprove();
                $admin->sendMessage("Спасибо! Запрос был одобрен!");
                $admin->setScenarioDone($bot);
                if ($admin->hasManagerRequests()) {
                    $admin->makeManagerRequestAsk();
                }
            } elseif ($message->getSenderText() == "Отклонить") {
                $admin->makeManagerDecline();
                $admin->sendMessage("Запрос был отклонён!");
                $admin->setScenarioDone($bot);
                if ($admin->hasManagerRequests()) {
                    $admin->makeManagerRequestAsk();
                }
            } else {
                $admin->sendMessage("Вы ввели неверную команду!");
            }
            
            break;
            
        case "waiting-number-to-dismiss-manager":
            
            $DBH = DB::getInstance();
            $stmt = $DBH->query("SELECT * FROM users WHERE is_manager = 1");

            $users = $stmt->fetchAll();
            
            if (count($users) > 0) {
                
                $counter = 1;
                
                foreach ($users as $user_row) {
                    
                    if ($message->getSenderText() == $counter) {
                        $DBH->exec("UPDATE users SET is_manager = 0 WHERE id = ".$user_row['id']);
                        $DBH->exec("DELETE FROM bots WHERE manager_id = ".$user_row['user_telegram_id']);
                        
                        break;
                    }
                    
                    $counter++;
                } 
            }
            
            $answer = "Менеджер был уволен!\n";
            
            $user->setScenarioDone($bot);
            $user->sendMessage($answer, $bot);
            
            break;
            
        case "waiting-message-to-mass-send":
            
            $mass_message = new MassMessage();
            if ($mass_message->send("Новость: ".$message->getSenderText())) {
                $answer = "Сообщение было разослано всем клиентам!";
            } else {
                $answer = "Не зарегистрировано ни одного клиента!";
            }
            $user->setScenarioDone($bot);
            $user->sendMessage($answer, $bot);
            
            break;
            
        case "waiting-message-to-group-send":
            
            $mass_message = new MassMessage("group");
            if ($mass_message->send("Новость: ".$message->getSenderText())) {
                $answer = "Сообщение было разослано всей группе!";
            } else {
                $answer = "У этого менеджера нет клиентов!";
            }
            $user->setScenarioDone($bot);
            $user->sendMessage($answer, $bot);
            
            break;
            
        case "waiting-manager-number-for-group-message":
            
            Settings::save("group_message_mananger", $message->getSenderText());
            
            $answer = "Напишите сообщение (новость) для массовой рассылки:\n";
            $user->setScenario("waiting-message-to-group-send", $bot);
            
            $user->sendMessage($answer, $bot);
            
            break;
    }
}

else {
    $answer = "Неверная команда!\n/start - просмотреть весь список команд.";

    if ($message->getSenderText() == "/start") {

        $answer = "Управление ботом Help Desk Center:\n\n";
        $answer .= "/who_is_admin - узнать, кто администратор.\n";
        $answer .= "/mass_message - массовая рассылка.\n";
        $answer .= "/group_message - групповая рассылка.\n";
        $answer .= "/dismiss_manager - уволить менеджера.\n";
    }
    
    if ($message->getSenderText() == "/who_is_admin") {
        $answer = "В данный момент Вы являетесь администратором.\n";
    }
    
    if ($message->getSenderText() == "/dismiss_manager") {

        $DBH = DB::getInstance();
        $stmt = $DBH->query("SELECT * FROM users WHERE is_manager = 1");
        
        $users = $stmt->fetchAll();
        
        if (count($users) > 0) {
            
            $answer = "Укажите номер менеджера, которого нужно удалить:\n";
            
            $counter = 1;
            
            foreach($users as $user_row) {
                $answer .= "$counter. $user_row[first_name] $user_row[last_name].\n";
                $counter++;
            }
            
            $buttons = array();
            
            for ($i = 1; $i <= count($users); $i++) {
                $buttons[] = "$i";
            }
            
            $user->sendMessage($answer, $bot, $buttons);
            $user->setScenario("waiting-number-to-dismiss-manager", $bot);
            
            exit();            
        }
        
        else {
            $answer = "В данный момент не зарегистрировано ни одного менеджера!\n";
        }
    }
    
    if ($message->getSenderText() == "/mass_message") {
        $answer = "Напишите сообщение (новость) для массовой рассылки:\n";
        $user->setScenario("waiting-message-to-mass-send", $bot);
    }
    
    if ($message->getSenderText() == "/group_message") {
        
        $DBH = DB::getInstance();
        $stmt = $DBH->query("SELECT * FROM users WHERE is_manager = 1");
        $users = $stmt->fetchAll();
        
        if (count($users) > 0) {
            
            $answer = "Клиентам какого менеджера разослать сообщение?\n";
            
            $counter = 1;
            
            foreach($users as $user_row) {
                $answer .= "$counter. $user_row[first_name] $user_row[last_name].\n";
                $counter++;
            }
            
            $buttons = array();
            
            for ($i = 1; $i <= count($users); $i++) {
                $buttons[] = "$i";
            }
            
            $user->sendMessage($answer, $bot, $buttons);
            $user->setScenario("waiting-manager-number-for-group-message", $bot);
            
            exit();
                
        } else {
            $answer = "В данный момент не зарегистрировано ни одного менеджера!\n";
        }
    }

    $user->sendMessage($answer, $bot);
}