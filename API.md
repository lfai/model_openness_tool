# API

## Overview

This API provides read-only access to a collection of models. It is open to the public and does not require an API key for access.

### Response Format

- All responses are in **JSON** format.

### Rate Limiting

- **Limit**: 10,000 requests per hour per IP address.
- Exceeding this limit will result in temporary rate-limiting.
- Rate-limited responses include the following structure:
```json
{
  "error": {
    "code": 429,
    "message": "Rate limit exceeded. Try again later."
  }
}
```

---

## Endpoints

### **GET /api/v1/models**

Lists all models with pagination.

#### Query Parameters:
- `page` (optional): The page number to retrieve (default: `1`). Pages are 1-indexed.
- `limit` (optional): The number of models per page (default: `100`).

#### Example Request:
```http
GET /api/v1/models?page=2&limit=50
```

#### Example Response:
```json
{
  "pager": {
    "total_items": 235,
    "total_pages": 5,
    "current_page": 2
  },
  "models": [...]
}
```

---

### **GET /api/v1/model/{model}**

Retrieves details of a specific model.

#### Path Parameters:
- `model`: The ID of the model to retrieve. Model IDs can be found using the `/api/v1/models` endpoint.

#### Example Request:
```http
GET /api/v1/model/1130
```

#### Example Response:
```json
{
  "id": "1130",
  "framework": {
    "name": "Model Openness Framework",
    "version": "1.0",
    "date": "2024-12-15"
  },
  "release": {...},
  "classification": {
    "class": 1,
    "label": "Class I - Open Science",
    "progress": {
      "1": 100,
      "2": 100,
      "3": 100
    }
  }
}
```

