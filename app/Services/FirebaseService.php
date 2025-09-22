<?php

namespace App\Services;


use Kreait\Firebase\Database;

class FirebaseService
{
    protected $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * @return \Kreait\Firebase\Database
     */
    public function getDatabase()
    {
        return $this->database;
    }

    // Chat Groups
    public function getChatGroup($groupId)
    {
        return $this->database->getReference('chat_groups/' . $groupId)->getValue();
    }

    public function getAllChatGroups()
    {
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
        // Prefer an embedded inverted index under `chat_groups/by_user/{userId}`
        // to avoid dynamic `.indexOn` rules while keeping the index inside
        // the `chat_groups` tree.
        try {
            $refs = $this->database->getReference('chat_groups/by_user/' . $userId)->getValue() ?? [];

            if (empty($refs)) {
                // Fall back to the direct query if the by_user index doesn't exist.
                $ref = $this->database->getReference('chat_groups');
                $query = $ref->orderByChild('members/' . $userId)->equalTo(true);
                return $query->getValue() ?? [];
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
            \Log::warning('Firebase read for chat_groups/by_user failed', [
                'user' => $userId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return [];
        }
    }

    public function setChatGroup($groupId, $data)
    {
        $ref = $this->database->getReference('chat_groups/' . $groupId)->set($data);

        // Maintain embedded inverted index under chat_groups/by_user/{userId}/{groupId}
        if (!empty($data['members']) && is_array($data['members'])) {
            foreach ($data['members'] as $memberId => $_) {
                try {
                    $this->database->getReference('chat_groups/by_user/' . $memberId . '/' . $groupId)->set(true);
                } catch (\Throwable $e) {
                    \Log::warning('Failed to write chat_groups/by_user index on setChatGroup', ['group' => $groupId, 'member' => $memberId, 'error' => $e->getMessage()]);
                }
            }
        }

        return $ref;
    }

    public function updateChatGroup($groupId, $data)
    {
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
                        \Log::warning('Failed to add chat_groups/by_user entry', ['group' => $groupId, 'member' => $memberId, 'error' => $e->getMessage()]);
                    }
                }

                // Remove members
                $toRemove = array_diff_key($existingMembers, $newMembers);
                foreach ($toRemove as $memberId => $_) {
                    try {
                        $this->database->getReference('chat_groups/by_user/' . $memberId . '/' . $groupId)->remove();
                    } catch (\Throwable $e) {
                        \Log::warning('Failed to remove chat_groups/by_user entry', ['group' => $groupId, 'member' => $memberId, 'error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Failed to sync chat_groups/by_user index', ['group' => $groupId, 'error' => $e->getMessage()]);
            }
        }

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
