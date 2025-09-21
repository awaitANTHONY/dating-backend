<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Auth;

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
        $user = $request->user();
        $receiverId = $request->input('receiver_id');
        if (!$receiverId) {
            return response()->json(['status' => false, 'message' => 'receiver_id is required.']);
        }
        $groupId = $user->id < $receiverId ? $user->id . '_' . $receiverId : $receiverId . '_' . $user->id;
        $groupRef = $this->firebase->getDatabase()->getReference('chat_groups/' . $groupId);
        $group = $groupRef->getValue();
        if (!$group) {
            $groupData = [
                'members' => [
                    $user->id => true,
                    $receiverId => true,
                ],
                'type' => 'private',
                'last_message' => '',
                'last_timestamp' => null,
            ];
            $groupRef->set($groupData);
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
        $this->firebase->pushMessage($groupId, $msgData);
        $this->firebase->updateChatGroup($groupId, [
            'last_message' => $message,
            'last_timestamp' => $timestamp,
        ]);
        return response()->json(['status' => true]);
    }

    // 3. Chat list for current user
    public function chatList(Request $request)
    {
        $user = $request->user();
        $groups = $this->firebase->getDatabase()->getReference('chat_groups')->getValue() ?? [];
        $userGroups = [];
        foreach ($groups as $groupId => $group) {
            if (isset($group['members'][$user->id])) {
                $userGroups[] = array_merge(['group_id' => $groupId], $group);
            }
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
