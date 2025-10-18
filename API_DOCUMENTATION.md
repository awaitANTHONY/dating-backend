# Dating App API Documentation - Swipe & Match System

## Base URL
```
{{base_url}}/api/v1
```

## Authentication
All endpoints require Bearer Token authentication using Laravel Sanctum.

**Header:**
```
Authorization: Bearer {{your_token_here}}
Content-Type: application/json
Accept: application/json
```

---

## 🔄 User Interactions (Swipe System)
*Handled by: `UserInteractionController`*umentation - Swipe & Match System

## Base URL
```
{{base_url}}/api/v1
```

## Authentication
All endpoints require Bearer Token authentication using Laravel Sanctum.

**Header:**
```
Authorization: Bearer {{your_token_here}}
Content-Type: application/json
Accept: application/json
```

---

## 🔄 User Interactions (Swipe Syst## 📊 Response Status Codes

| Code | Description |
|------|-------------|
| 200  | Success |
| 400  | Bad Request (e.g., self-interaction) |
| 401  | Unauthorized (invalid/missing token) |
| 404  | Not Found (e.g., match not found) |
| 422  | Validation Error |
| 500  | Internal Server Error |

---

## 🚫 User Blocking System
*Handled by: `UserBlockController`*

### 8. Toggle Block/Unblock User
**POST** `/blocks/toggle`

Toggle the blocking status of a user. If the user is not blocked, this will block them. If they are already blocked, this will unblock them.

#### Request Body:
```json
{
    "target_user_id": 123,
    "reason": "Inappropriate behavior"
}
```

#### Parameters:
- `target_user_id` (integer, required): ID of the user to block/unblock
- `reason` (string, optional): Reason for blocking (only used when blocking, max 500 characters)

#### Success Response - Blocking (200):
```json
{
    "status": true,
    "data": {
        "action": "blocked",
        "block_id": 456,
        "target_user_id": 123,
        "reason": "Inappropriate behavior",
        "blocked_at": "2025-10-18T14:30:00.000000Z",
        "is_blocked": true
    },
    "message": "User blocked successfully"
}
```

#### Success Response - Unblocking (200):
```json
{
    "status": true,
    "data": {
        "action": "unblocked",
        "target_user_id": 123,
        "is_blocked": false
    },
    "message": "User unblocked successfully"
}
```

#### Error Response (400):
```json
{
    "status": false,
    "message": "Cannot block yourself"
}
```

---

### 9. Get Blocked Users
**GET** `/blocks`

Retrieve the list of users blocked by the current user.

#### Success Response (200):
```json
{
    "status": true,
    "data": [
        {
            "block_id": 456,
            "blocked_at": "2025-10-18T14:30:00.000000Z",
            "reason": "Inappropriate behavior",
            "user": {
                "id": 123,
                "name": "John Doe",
                "email": "john@example.com"
            }
        }
    ],
    "message": "Blocked users retrieved successfully"
}
```

---

### 10. Check Block Status
**GET** `/blocks/check/{target_user_id}`

Check the blocking status between the current user and another user.

#### URL Parameters:
- `target_user_id` (integer): ID of the user to check block status with

#### Success Response (200):
```json
{
    "status": true,
    "data": {
        "user_id": 789,
        "target_user_id": 123,
        "is_blocked_by_me": true,
        "is_blocked_by_them": false,
        "is_mutually_blocked": false
    },
    "message": "Block status retrieved successfully"
}
```

---

### 11. Get Block Statistics
**GET** `/blocks/stats`

Get blocking statistics for the current user.

#### Success Response (200):
```json
{
    "status": true,
    "data": {
        "users_blocked_by_me": 5,
        "users_who_blocked_me": 2
    },
    "message": "Block statistics retrieved successfully"
}
```

---

## 📋 Postman Collection Setupeate User Interaction (Swipe)
**POST** `/interactions`

Allows users to like, dislike, or pass on other users. Automatically creates a match if both users like each other.

#### Request Body:
```json
{
    "target_user_id": 123,
    "action": "like"
}
```

#### Parameters:
- `target_user_id` (integer, required): ID of the user being swiped on
- `action` (string, required): Action type - `"like"`, `"dislike"`, or `"pass"`

#### Success Response (200):
```json
{
    "status": true,
    "data": {
        "interaction_id": 456,
        "action": "like",
        "is_match": true,
        "match_id": 789
    },
    "message": "Interaction recorded successfully"
}
```

#### Error Responses:
**400 - Self Interaction:**
```json
{
    "status": false,
    "message": "Cannot interact with yourself"
}
```

**422 - Validation Error:**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "target_user_id": ["The target user id field is required."],
        "action": ["The selected action is invalid."]
    }
}
```

---

### 2. Get User Interactions History
**GET** `/interactions`

Retrieves the current user's interaction history.

#### Success Response (200):
```json
{
    "status": true,
    "data": [
        {
            "id": 1,
            "target_user_id": 123,
            "action": "like",
            "created_at": "2025-10-18T10:30:00.000000Z",
            "target_user": {
                "id": 123,
                "name": "John Doe",
                "email": "john@example.com"
            }
        }
    ],
    "message": "Interactions retrieved successfully"
}
```

---

### 3. Get Received Likes
**GET** `/interactions/likes`

Retrieves users who have liked the current user.

#### Success Response (200):
```json
{
    "status": true,
    "data": [
        {
            "id": 1,
            "user_id": 456,
            "action": "like",
            "created_at": "2025-10-18T10:30:00.000000Z",
            "user": {
                "id": 456,
                "name": "Jane Smith",
                "email": "jane@example.com"
            }
        }
    ],
    "message": "Received likes retrieved successfully"
}
```

---

## 💕 Matches
*Handled by: `UserInteractionController`*

### 4. Get User Matches
**GET** `/matches`

Retrieves all matches for the current user.

#### Success Response (200):
```json
{
    "status": true,
    "data": [
        {
            "match_id": 789,
            "matched_at": "2025-10-18T10:30:00.000000Z",
            "user": {
                "id": 123,
                "name": "John Doe",
                "email": "john@example.com"
            }
        }
    ],
    "message": "Matches retrieved successfully"
}
```

---

### 5. Check Match Status
**GET** `/matches/check/{target_user_id}`

Check if the current user is matched with a specific user.

#### URL Parameters:
- `target_user_id` (integer): ID of the user to check match status with

#### Success Response (200):
```json
{
    "status": true,
    "data": {
        "is_matched": true,
        "user_id": 789,
        "target_user_id": 123
    },
    "message": "Match status retrieved successfully"
}
```

---

### 6. Unmatch User
**DELETE** `/matches/{target_user_id}`

Remove a match with a specific user (soft delete).

#### URL Parameters:
- `target_user_id` (integer): ID of the user to unmatch with

#### Success Response (200):
```json
{
    "status": true,
    "message": "Successfully unmatched"
}
```

#### Error Response (404):
```json
{
    "status": false,
    "message": "No match found to unmatch"
}
```

---

### 7. Get Match Statistics
**GET** `/matches/stats`

Get match statistics for the current user.

#### Success Response (200):
```json
{
    "status": true,
    "data": {
        "total_matches": 25,
        "active_matches": 20
    },
    "message": "Match statistics retrieved successfully"
}
```

---

## 📋 Postman Collection Setup

### Environment Variables
Create a Postman environment with these variables:

```
base_url: http://your-app-domain.com
token: your_bearer_token_here
user_id: your_authenticated_user_id
target_user_id: 123
```

### Pre-request Scripts
Add this to your collection's pre-request script to automatically set the authorization header:

```javascript
pm.request.headers.add({
    key: 'Authorization',
    value: 'Bearer ' + pm.environment.get('token')
});

pm.request.headers.add({
    key: 'Accept',
    value: 'application/json'
});

pm.request.headers.add({
    key: 'Content-Type',
    value: 'application/json'
});
```

### Test Scripts
Add these test scripts to validate responses:

#### For successful responses:
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has status field", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('status');
    pm.expect(jsonData.status).to.be.true;
});

pm.test("Response has message", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('message');
});
```

---

## 🔧 Common Error Responses

### 401 - Unauthorized:
```json
{
    "message": "Unauthenticated."
}
```

### 500 - Server Error:
```json
{
    "status": false,
    "message": "Failed to process request",
    "error": "Internal server error"
}
```

---

## 📝 Sample Postman Requests

### 1. Swipe Like
```
POST {{base_url}}/api/v1/interactions
Content-Type: application/json
Authorization: Bearer {{token}}

{
    "target_user_id": {{target_user_id}},
    "action": "like"
}
```

### 2. Swipe Dislike
```
POST {{base_url}}/api/v1/interactions
Content-Type: application/json
Authorization: Bearer {{token}}

{
    "target_user_id": {{target_user_id}},
    "action": "dislike"
}
```

### 3. Get Matches
```
GET {{base_url}}/api/v1/matches
Authorization: Bearer {{token}}
```

### 4. Check Match Status
```
GET {{base_url}}/api/v1/matches/check/{{target_user_id}}
Authorization: Bearer {{token}}
```

### 5. Unmatch User
```
DELETE {{base_url}}/api/v1/matches/{{target_user_id}}
Authorization: Bearer {{token}}
```

### 6. Toggle Block/Unblock User
```
POST {{base_url}}/api/v1/blocks/toggle
Content-Type: application/json
Authorization: Bearer {{token}}

{
    "target_user_id": {{target_user_id}},
    "reason": "Inappropriate behavior"
}
```

### 7. Get Blocked Users
```
GET {{base_url}}/api/v1/blocks
Authorization: Bearer {{token}}
```

### 8. Check Block Status
```
GET {{base_url}}/api/v1/blocks/check/{{target_user_id}}
Authorization: Bearer {{token}}
```

---

## 📊 Response Status Codes

| Code | Description |
|------|-------------|
| 200  | Success |
| 400  | Bad Request (e.g., self-interaction) |
| 401  | Unauthorized (invalid/missing token) |
| 404  | Not Found (e.g., match not found) |
| 422  | Validation Error |
| 500  | Internal Server Error |

---

## 🧪 Testing Workflow

1. **Setup Authentication**: Get Bearer token from login endpoint
2. **Create Interactions**: Test like/dislike/pass actions
3. **Verify Matches**: Check if mutual likes create matches
4. **Test Blocking**: Block users and verify interactions/matches are removed
5. **Test Unblocking**: Unblock users and verify they can interact again
6. **Test Edge Cases**: Try self-interaction, self-blocking, invalid user IDs
7. **Test Statistics**: Verify match and block counts are accurate

---

## 💡 Tips for Postman Testing

1. **Use Collections**: Group related requests together
2. **Environment Variables**: Use variables for dynamic data
3. **Pre/Post Scripts**: Automate token management and validation
4. **Mock Servers**: Create mock responses for frontend development
5. **Documentation**: Use Postman's documentation feature to share with team
