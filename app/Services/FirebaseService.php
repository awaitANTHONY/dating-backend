<?php

namespace App\Services;

use Kreait\Firebase\Database;
use Kreait\Firebase\Factory;

class FirebaseService
{
    protected $database;
    protected $auth;

    public function __construct(Database $database = null)
    {
        $this->database = $database;
        
        // Initialize Firebase Auth
        if (!$this->auth) {
            try {
                $credentialsPath = storage_path(get_option('firebase_json'));
                $factory = (new Factory)
                    ->withServiceAccount($credentialsPath);
                $this->auth = $factory->createAuth();
            } catch (\Exception $e) {
                \Log::error('Firebase Auth initialization failed: ' . $e->getMessage());
                $this->auth = null;
            }
        }
    }

    /**
     * Create a custom Firebase authentication token for a user
     * @param string $uid User ID
     * @return string|null Firebase custom token or null on failure
     */
    public function createCustomToken(string $uid): ?string
    {
        if (!$this->auth) {
            return null;
        }
        
        try {
            return $this->auth->createCustomToken($uid)->toString();
        } catch (\Exception $e) {
            \Log::error('Firebase custom token creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return \Kreait\Firebase\Database|null
     */
    public function getDatabase()
    {
        return $this->database;
    }

        /**
         * Update a JSON file in Laravel storage (e.g., Firebase credentials).
         * @param string $relativePath Path relative to storage/app (e.g., 'private/files/filename.json')
         * @param array $data Data to encode as JSON
         * @return bool True on success, false on failure
         */
        public function updateJsonFile($relativePath, array $data)
        {
            $fullPath = storage_path('app/' . ltrim($relativePath, '/'));
            try {
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($json === false) {
                    return false;
                }
                file_put_contents($fullPath, $json);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }
    // Chat Groups
    public function getChatGroup($groupId)
    {
        if (!$this->database) {
            return null;
        }
        return $this->database->getReference('chat_groups/' . $groupId)->getValue();
    }

    public function getAllChatGroups()
    {
        if (!$this->database) {
            return [];
        }
        return $this->database->getReference('chat_groups')->getValue() ?? [];
    }

    /**
     * Return chat groups for a given single user id using an inverted index.
     * The inverted index lives at `user_chat_groups/{userId}` and maps groupId => true.
     *
     * @param int|string $userId
     * @return array
     */
    public function getChatGroupsForUser($userId)
    {
        if (!$this->database) {
            return [];
        }

        // Prefer an embedded inverted index under `chat_groups/by_user/{userId}`
        // to avoid dynamic `.indexOn` rules while keeping the index inside
        // the `chat_groups` tree.
        try {
            $refs = $this->database->getReference('chat_groups/by_user/' . $userId)->getValue() ?? [];

            if (empty($refs)) {
                // Fall back to fetching all chat groups and filtering manually
                // This is more reliable than Firebase queries with complex rules
                $allGroups = $this->getAllChatGroups();
                $result = [];
                
                foreach ($allGroups as $groupId => $group) {
                    // Check if user is a member of this group
                    // Method 1: Check if group has members field
                    if (isset($group['members'][$userId]) && $group['members'][$userId]) {
                        $result[$groupId] = $group;
                    }
                    // Method 2: Check if groupId contains the userId (common pattern: user1_user2)
                    elseif (str_contains($groupId, '_' . $userId . '_') || str_contains($groupId, $userId . '_') || str_contains($groupId, '_' . $userId)) {
                        $result[$groupId] = $group;
                    }
                }
                
                return $result;
            }

            $result = [];
            foreach ($refs as $groupId => $_) {
                $group = $this->getChatGroup($groupId);
                if ($group) {
                    $result[$groupId] = $group;
                }
            }

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setChatGroup($groupId, $data)
    {
        if (!$this->database) {
            return false;
        }

        $ref = $this->database->getReference('chat_groups/' . $groupId)->set($data);

        // Maintain embedded inverted index under chat_groups/by_user/{userId}/{groupId}
        if (!empty($data['members']) && is_array($data['members'])) {
            foreach ($data['members'] as $memberId => $_) {
                try {
                    $this->database->getReference('chat_groups/by_user/' . $memberId . '/' . $groupId)->set(true);
                } catch (\Throwable $e) {
                   //
                }
            }
        }

        return $ref;
    }

    public function updateChatGroup($groupId, $data)
    {
        if (!$this->database) {
            return false;
        }

        // Sync embedded by_user index when members change
        if (array_key_exists('members', $data)) {
            try {
                $existing = $this->getChatGroup($groupId) ?? [];
                $existingMembers = $existing['members'] ?? [];
                $newMembers = is_array($data['members']) ? $data['members'] : [];

                // Add new members
                $toAdd = array_diff_key($newMembers, $existingMembers);
                foreach ($toAdd as $memberId => $_) {
                    try {
                        $this->database->getReference('chat_groups/by_user/' . $memberId . '/' . $groupId)->set(true);
                    } catch (\Throwable $e) {
                        //
                    }
                }

                // Remove members
                $toRemove = array_diff_key($existingMembers, $newMembers);
                foreach ($toRemove as $memberId => $_) {
                    try {
                        $this->database->getReference('chat_groups/by_user/' . $memberId . '/' . $groupId)->remove();
                    } catch (\Throwable $e) {
                        //
                    }
                }
            } catch (\Throwable $e) {
                //
            }
        }

        return $this->database->getReference('chat_groups/' . $groupId)->update($data);
    }

    // Chat Messages
    public function pushMessage($groupId, $messageData)
    {
        if (!$this->database) {
            return false;
        }

        return $this->database->getReference('chat_messages/' . $groupId)->push($messageData);
    }

    public function getMessages($groupId)
    {
        if (!$this->database) {
            return [];
        }

        return $this->database->getReference('chat_messages/' . $groupId)->getValue();
    }

    // User Profiles
    public function getUser($userId)
    {
        if (!$this->database) {
            return null;
        }

        return $this->database->getReference('users/' . $userId)->getValue();
    }

    public function setUser($userId, $data)
    {
        if (!$this->database) {
            return false;
        }

        return $this->database->getReference('users/' . $userId)->set($data);
    }

    public function updateUser($userId, $data)
    {
        if (!$this->database) {
            return false;
        }

        return $this->database->getReference('users/' . $userId)->update($data);
    }
}
