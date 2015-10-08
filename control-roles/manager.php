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
        if ($user->isManager()) {
            $answer = "Укажите токен нового бота:\n";
            $user->setScenario("wait-bot-token", $bot);
        }
    }
    
    $user->sendMessage($answer, $bot);
}