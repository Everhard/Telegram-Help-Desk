<?php

if ($user->hasActiveScenario($bot)) {
    switch($user->getScenario($bot)) {
        case "wait-for-answer-message":
            $history = new History($bot);
            if ($client_history = $history->getHistoryByInProcess()) {
                $client = $client_history->getUser();
                $client->sendMessage($message->getSenderText(), $bot);
                $client_history->delete();
                $user->setScenarioDone($bot);
                
                if ($names = $history->getArrayOfNames()) {
                    $user->sendMessage("Выберите следующего клиента для ответа:\n", $bot, $names);
                }
            }
            break;
    }
}
else {
    $history = new History($bot);
    if ($client_history = $history->getHistoryByName(substr($message->getSenderText(), 1))) {
        $user->sendMessage("Напишите ответ:\n", $bot);
        $client_history->makeStatusInProcess();
        $user->setScenario("wait-for-answer-message", $bot);
    } else {
        $keyboard = $history->getArrayOfNames();
        
        if ($keyboard) {
            $user->sendMessage("Вы не указали получателя сообщения!\n", $bot, $keyboard);
        }
    }
}