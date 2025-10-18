# Profile Discovery API - Filter Parameters Documentation

## Overview
The `/api/v1/profiles/recommendations` endpoint supports two modes:
- **Recommendations Mode**: When no filters are provided, returns smart compatibility-based matches (cached for 1 hour)
- **Search Mode**: When any filter is provided, returns filtered search results (real-time, not cached)

---

## Endpoint
```
POST /api/v1/profiles/recommendations
```

## Authentication
Requires Bearer token authentication.

---

## Filter Parameters

### Basic Search
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `q` | string | Search by name or email | `"john"` |

### Demographics
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `gender` | string | Filter by gender | `"male"`, `"female"` |
| `min_age` | integer | Minimum age | `25` |
| `max_age` | integer | Maximum age | `35` |
| `min_height` | integer | Minimum height (cm) | `160` |
| `max_height` | integer | Maximum height (cm) | `180` |

### Location
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `radius` | integer | Search radius in kilometers | `50` |

### Lifestyle
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `alkohol` | string | Alcohol preference | `"dont_drink"`, `"drink_socially"`, `"drink_frequently"`, `"prefer_not_to_say"` |
| `smoke` | string | Smoking preference | `"dont_smoke"`, `"smoke_occasionally"`, `"smoke_regularly"`, `"prefer_not_to_say"` |

### Background & Status
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `religion_id` | integer | Religion ID | `1`, `2`, `3` |
| `relationship_status_id` | integer | Relationship status ID | `1`, `2`, `3` |
| `ethnicity_id` | integer | Ethnicity ID | `1`, `2`, `3` |
| `education_id` | integer | Education level ID | `1`, `2`, `3` |
| `carrer_field_id` | integer | Career field ID | `1`, `2`, `3` |

### Interests & Goals
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `interests` | array | Array of interest IDs | `[1, 2, 3]` |
| `languages` | array | Array of language IDs | `[1, 2]` |
| `relation_goals` | array | Array of relationship goal IDs | `[1, 2]` |

---

## Request Examples

### 1. Get Recommendations (No Filters)
```json
POST /api/v1/profiles/recommendations
{
  // No parameters - returns smart recommendations
}
```

### 2. Basic Search
```json
POST /api/v1/profiles/recommendations
{
  "q": "sarah",
  "gender": "female"
}
```

### 3. Age and Location Filter
```json
POST /api/v1/profiles/recommendations
{
  "min_age": 25,
  "max_age": 35,
  "radius": 20
}
```

### 4. Lifestyle Preferences
```json
POST /api/v1/profiles/recommendations
{
  "alkohol": "dont_drink",
  "smoke": "dont_smoke",
  "religion_id": 1
}
```

### 5. Complex Search
```json
POST /api/v1/profiles/recommendations
{
  "gender": "male",
  "min_age": 28,
  "max_age": 40,
  "radius": 50,
  "education_id": 3,
  "interests": [1, 5, 8],
  "languages": [1, 2],
  "alkohol": "drink_socially"
}
```

---

## Response Format

### Success Response
```json
{
  "status": true,
  "data": [
    {
      "id": 123,
      "name": "John Doe",
      "email": "john@example.com",
      "image": "profile_image_url",
      "bio": "User bio",
      "gender": "male",
      "age": 30,
      "height": 175,
      "distance": 15.2,
      "match_score": 85, // Only in recommendations mode
      "compatibility_details": { // Only in recommendations mode
        "relation_goals_match": true,
        "language_match": true,
        "interests_match": false,
        "religion_match": true,
        "lifestyle_compatible": true,
        "age_compatible": true,
        "within_distance": true
      },
      // Detailed information
      "relation_goals_details": [...],
      "interests_details": [...],
      "languages_details": [...],
      "relationship_status_details": {...},
      "ethnicity_details": {...},
      "education_details": {...},
      "career_field_details": {...}
    }
  ]
}
```

### Error Response
```json
{
  "status": false,
  "message": "User profile information not found. Please complete your profile first."
}
```

---

## Behavior Differences

### Recommendations Mode (No Filters)
- **Caching**: Results cached for 1 hour per user
- **Sorting**: By compatibility score (highest first)
- **Scoring**: Includes match_score and compatibility_details
- **Logic**: Smart matching based on preferences, location, and compatibility
- **Blocked Users**: Automatically excluded

### Search Mode (With Filters)
- **Caching**: No caching (real-time results)
- **Sorting**: By distance (closest first)
- **Scoring**: No match scoring
- **Logic**: Filter-based search with distance sorting
- **Blocked Users**: Automatically excluded

---

## Notes

1. **Arrays**: When sending arrays (interests, languages, relation_goals), use JSON array format
2. **IDs**: Reference IDs should correspond to values in respective lookup tables
3. **Distance**: Calculated using Haversine formula in kilometers
4. **Blocking**: Users who have blocked each other are automatically excluded from results
5. **Cache**: Recommendations are cached for performance; search results are always fresh
6. **Authentication**: All requests require valid Bearer token

---

## Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request (missing profile information) |
| 401 | Unauthorized (invalid token) |
| 500 | Internal Server Error |