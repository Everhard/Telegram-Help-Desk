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
            
    }

    $user->sendMessage($answer, $bot);
}
else {

    $answer = "Неверная команда!\n/start - просмотреть весь список команд.";

    if ($message->getSenderText() == "/start") {

        $answer = "Управление ботом Help Desk Center:\n\n";
        $answer .= "/become_admin - получить права администратора.\n";
        $answer .= "/become_manager - получить права менеджера.\n";
        $answer .= "/who_is_admin - узнать, кто администратор.\n";
        $answer .= "/cancel - отменить текущую операцию.";

    }

    if ($message->getSenderText() == "/cancel") {

        $answer = "В данный момент команда /cancel неприменима.\n";

    }

    if ($message->getSenderText() == "/become_admin") {

        $answer = "Пожалуйста, введите пароль суперпользователя:\n";
        $user->setScenario("wait-password-to-become-admin", $bot);
    }

    if ($message->getSenderText() == "/become_manager") {

        $admin = new Administrator();
        $admin->storeManagerRequest($user);
        $admin->makeManagerRequestAsk();

        $answer = "Спасибо! Запрос был послан администратору!\n";
        $answer .= "О результатах его решения Вы узнаете мгновенно.\n";
    }
    
    if ($message->getSenderText() == "/who_is_admin") {

        $admin = new Administrator();
        $answer = "В данный момент администратором является ".$admin->getFullName().".";
    }
    
    $user->sendMessage($answer, $bot);
}