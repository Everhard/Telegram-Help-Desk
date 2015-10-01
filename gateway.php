<?php

require_once('config.php');
require_once('libs/telegram.php');
require_once('libs/helpdesk.php');

$input = '{"update_id":914635613,"message":{"message_id":25,"from":{"id":109075721,"first_name":"Andrew","last_name":"Dorokhov"},"chat":{"id":109075721,"first_name":"Andrew","last_name":"Dorokhov"},"date":1443650284,"text":"1111"}}';

if ($input = file_get_contents('php://input')) {
    
    $message = new Message($input);
    
    $bot = new Bot();
    
    $user = new User(array(
        "first-name" => $message->getSenderFirstName(),
        "last-name" => $message->getSenderLastName(),
        "user-telegram-id" => $message->getSenderId(),
    ));
    
    /*
     * Administrator:
     */
    if ($user->isAdmin() && $bot->getId() == 1) {
        echo "Admin";
    }
    
    /*
     * Manager:
     */
    if ($user->isManager($bot)) {
        echo "Manager";
    }
    
    /*
     * Client:
     */
    if ($user->isClient($bot)) {
        
        if ($bot->isMain()) {
            
            if ($user->hasActiveScenario()) {
                /*
                 * Comlete started scenario:
                 */
                
                if ($message->getText() == "/cancel") {
                    $user->setScenarioDone();
                    $answer = "Текущее действие было отменено!\n";
                }
                
                else switch($user->getScenario()) {
                    
                    case "wait-password-to-become-admin":
                        if ($message->getText() == $config['admin-password']) {
                            
                            $answer = "Спасибо, ".$user->getFirstName()."! Теперь Вы новый администратор!\n";
                            $user->makeAdmin();
                            $user->setScenarioDone();
                        }
                        else {
                            
                            $answer = "Вы указали неправильный пароль!\n";
                            $answer .= "Попробуйте ещё раз! Но в этот раз будьте внимательней!\n";
                        }
                        
                        break;
                }
                
                
            }
            else {
                
                /*
                 * Scenario init:
                 */
                
                $answer = 'Неверная команда! /start - просмотреть весь список команд.';
            
                if ($message->getText() == "/start") {

                    $answer = "Управление ботом Help Desk Center:\n\n";
                    $answer .= "/become_admin - получить права администратора.\n";
                    $answer .= "/become_manager - получить права менеджера.\n";
                    $answer .= "/cancel - отменить текущую операцию.";

                }

                if ($message->getText() == "/become_admin") {

                    $answer = "Пожалуйста, введите пароль суперпользователя:\n";
                    $user->setScenario("wait-password-to-become-admin");
                }
                
                if ($message->getText() == "/become_manager") {

                    $admin = new Administrator();
                    $admin->sendMessage("Пользователь хочет стать менеджером!");
                    
                    $answer = "Спасибо! Запрос был послан администратору!\n";
                    $answer .= "О результатах его решения Вы узнаете мгновенно.\n";
                    $user->setScenario("wait-password-to-become-admin");
                }
            }
            
            $bot->getTelegram()->sendMessage($user->getUserTelegramId(), $answer);
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
            $message_to_manager = "/".$user->getCommandFullName()." : ".$message;
            $manager = $bot->getManager();
            $manager->sendMessage($message_to_manager, $bot);
        }
    }
    
    #file_put_contents('input.txt', "f", FILE_APPEND);
    
}




//$telegram_bot = new TelegramBot();
//
//if ($telegram_bot->getWebhookUpdates()) {
//    
//}