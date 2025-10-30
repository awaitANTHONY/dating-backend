<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FirebaseService;

class ChatController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    // 1. Start chat (private or group)
    public function startChat(Request $request)
    {
        // Get current user (may be null in quick local tests); use a fallback
        // placeholder id for unauthenticated calls during development.
        $user = $request->user();
        $user_id = $user->id ?? 28;

        $receiverId = $request->input('receiver_id');
        if (! $receiverId) {
            return response()->json(['status' => false, 'message' => 'receiver_id is required.']);
        }

        $groupId = $user_id < $receiverId
            ? $user_id . '_' . $receiverId
            : $receiverId . '_' . $user_id;

        \Log::info('ChatController::startChat', [
            'user_id' => $user_id,
            'receiver_id' => $receiverId,
            'group_id' => $groupId,
            'firebase_available' => $this->firebase->getDatabase() !== null
        ]);

        $group = $this->firebase->getChatGroup($groupId);

        if (! $group) {
            $groupData = [
                'members' => [
                    $user_id => true,
                    $receiverId => true,
                ],
                'type' => 'private',
                'last_message' => '',
                'last_timestamp' => null,
            ];

            \Log::info('Setting chat group data', ['group_id' => $groupId, 'data' => $groupData]);

            // Persist via our FirebaseService helper
            $result = $this->firebase->setChatGroup($groupId, $groupData);
            
            \Log::info('Firebase setChatGroup result', ['result' => $result]);
        }

        return response()->json(['status' => true, 'group_id' => $groupId]);
    }

    // 2. Send message
    public function sendMessage(Request $request)
    {
        $user = $request->user();
        $receiverId = $request->input('receiver_id');
        $message = $request->input('message');
        if (!$receiverId || !$message) {
            return response()->json(['status' => false, 'message' => 'receiver_id and message are required.']);
        }
        $groupId = $user->id < $receiverId ? $user->id . '_' . $receiverId : $receiverId . '_' . $user->id;
        $timestamp = now()->timestamp;
        $msgData = [
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'message' => $message,
            'timestamp' => $timestamp,
            'seen_by' => [$user->id => true],
        ];

        \Log::info('ChatController::sendMessage', [
            'group_id' => $groupId,
            'message_data' => $msgData,
            'firebase_available' => $this->firebase->getDatabase() !== null
        ]);

        $messageResult = $this->firebase->pushMessage($groupId, $msgData);
        $updateResult = $this->firebase->updateChatGroup($groupId, [
            'last_message' => $message,
            'last_timestamp' => $timestamp,
        ]);

        \Log::info('Firebase operation results', [
            'message_result' => $messageResult,
            'update_result' => $updateResult
        ]);

        return response()->json(['status' => true]);
    }

    // 3. Chat list for current user
    public function chatList(Request $request)
    {
        $user = $request->user();
        $user = $request->user();
        $userId = $user->id ?? 28;

        // Use the FirebaseService method that reads the inverted index
        // `user_chat_groups/{userId}` so we don't need to fetch all groups.
        $groups = $this->firebase->getChatGroupsForUser($userId);

        $userGroups = [];
        foreach ($groups as $groupId => $group) {
            $userGroups[] = array_merge(['group_id' => $groupId], $group);
        }
        usort($userGroups, function($a, $b) {
            return ($b['last_timestamp'] ?? 0) <=> ($a['last_timestamp'] ?? 0);
        });
        return response()->json(['status' => true, 'groups' => $userGroups]);
    }

    // 4. Get messages for a group
    public function messages(Request $request, $groupId)
    {
        $messages = $this->firebase->getMessages($groupId) ?? [];
        $result = [];
        foreach ($messages as $msgId => $msg) {
            $result[] = array_merge(['id' => $msgId], $msg);
        }
        usort($result, function($a, $b) {
            return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
        });
        return response()->json(['status' => true, 'messages' => $result]);
    }
}
