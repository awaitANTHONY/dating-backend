<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;

class FirebaseService
{
    protected $database;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(public_path('uploads/files/firebase_json.json'));
        $this->database = $factory->createDatabase();
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    // Chat Groups
    public function getChatGroup($groupId)
    {
        return $this->database->getReference('chat_groups/' . $groupId)->getValue();
    }

    public function setChatGroup($groupId, $data)
    {
        return $this->database->getReference('chat_groups/' . $groupId)->set($data);
    }

    public function updateChatGroup($groupId, $data)
    {
        return $this->database->getReference('chat_groups/' . $groupId)->update($data);
    }

    // Chat Messages
    public function pushMessage($groupId, $messageData)
    {
        return $this->database->getReference('chat_messages/' . $groupId)->push($messageData);
    }

    public function getMessages($groupId)
    {
        return $this->database->getReference('chat_messages/' . $groupId)->getValue();
    }

    // User Profiles
    public function getUser($userId)
    {
        return $this->database->getReference('users/' . $userId)->getValue();
    }

    public function setUser($userId, $data)
    {
        return $this->database->getReference('users/' . $userId)->set($data);
    }

    public function updateUser($userId, $data)
    {
        return $this->database->getReference('users/' . $userId)->update($data);
    }
}
