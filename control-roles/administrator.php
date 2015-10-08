<?php

if ($user->hasActiveScenario($bot)) {
    /*
     * Comlete started scenario:
     */
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
            
    }
}

else {
    $answer = "Неверная команда!\n/start - просмотреть весь список команд.";

    if ($message->getSenderText() == "/start") {

        $answer = "Управление ботом Help Desk Center:\n\n";
        $answer .= "/who_is_admin - узнать, кто администратор.\n";
    }
    
    if ($message->getSenderText() == "/who_is_admin") {

        $answer = "В данный момент Вы являетесь администратором.\n";
    }

    $user->sendMessage($answer, $bot);
}