<?php

require_once('config.php');
require_once('libs/telegram.php');
require_once('libs/helpdesk.php');

if ($input = file_get_contents('php://input')) {
    
    /*
     * Message from user (it can be client, manager, admin).
     */
    $message = new Message($input);
    
    /*
     * Current bot (it can be main admin bot or manager's bot):
     */
    $bot = new Bot();
    
    /*
     * User that have sent message.
     */
    $user = new User(
            $message->getSenderId(),
            $message->getSenderFirstName(), 
            $message->getSenderLastName()
    );

    
    /*
     * Administrator:
     */
    if ($user->isAdmin() && $bot->isMain()) {
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
        } else {
            $answer = 'Неверная команда! /start - просмотреть весь список команд.';
            
            if ($message->getSenderText() == "/start") {

                $answer = "Управление ботом Help Desk Center:\n\n";
                $answer .= "Команды отсутствуют.\n";
            }
            
            $user->sendMessage($answer, $bot);
        }
        exit;
    }
    
    /*
     * Manager:
     */
    if ($user->isManager($bot)) {
        
        if ($user->hasActiveScenario($bot)) {
            switch($user->getScenario($bot)) {
                case "wait-for-answer-message":
                    $history = new History($bot);
                    if ($client_history = $history->getHistoryByInProcess()) {
                        $client = $client_history->getUser();
                        $client->sendMessage($message->getSenderText(), $bot);
                        $client_history->delete();
                        $user->setScenarioDone($bot);
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
                $user->sendMessage("Вы не указали получателя сообщения!\n", $bot, $keyboard);
            }
        }
    }
    
    /*
     * Client:
     */
    if ($user->isClient($bot)) {
        
        if ($bot->isMain()) {
            
            if ($user->hasActiveScenario($bot)) {
                /*
                 * Comlete started scenario:
                 */
                
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
                
                
            }
            else {
                
                /*
                 * Scenario init:
                 */
                
                $answer = 'Неверная команда! /start - просмотреть весь список команд.';
            
                if ($message->getSenderText() == "/start") {

                    $answer = "Управление ботом Help Desk Center:\n\n";
                    $answer .= "/become_admin - получить права администратора.\n";
                    $answer .= "/become_manager - получить права менеджера.\n";
                    
                    if ($user->isManager()) {
                        $answer .= "/add_bot - подключить нового бота.\n";
                    }
                    
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
                
                if ($message->getSenderText() == "/add_bot") {
                    if ($user->isManager()) {
                        $answer = "Укажите токен нового бота:\n";
                        $user->setScenario("wait-bot-token", $bot);
                    }
                }
            }
            
            $user->sendMessage($answer, $bot);
        }
        else {
            /*
             * Write to history:
             */
            $history = new History($bot);
            $history->writeRequest($user);

            /*
             * Send message to manager:
             */
            $keyboard = $history->getArrayOfNames();
            
            $message_to_manager = "/".$user->getCommandFullName()." : ".$message->getSenderText();
            $manager = $bot->getManager();
            $manager->sendMessage($message_to_manager, $bot, $keyboard);
        }
    }
}