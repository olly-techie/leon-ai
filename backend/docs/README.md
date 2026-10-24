# Defence Panel API

## Overview
This is the backend API for the **Defence Panel** — an AI-powered project defence/examination simulator. 
It manages PDF report uploads, AI-powered weakness scanning, and panel sessions with three examiner personas.

The API is **PHP-based** (8.3), using **Doctrine DBAL** for PostgreSQL, **OpenRouter** for LLM calls, 
and **JWT** for authentication.

---

## 🔐 Authentication

All session-related endpoints require a Bearer JWT token. Obtain one by registering or logging in.

### Register
`POST /api/auth/register`
- Body: `{ "email": "user@example.com", "password": "securepass123" }`
- Returns: `{ "token": "...", "user": { "id": "...", "email": "..." } }`

### Login
`POST /api/auth/login`
- Body: `{ "email": "user@example.com", "password": "securepass123" }`
- Returns: `{ "token": "...", "user": { "id": "...", "email": "..." } }`

### Using the token
Include in the `Authorization` header:
```
Authorization: Bearer <your-jwt-token>
```

> **Public endpoints** (no auth required): `GET /health`, `POST /api/auth/register`, `POST /api/auth/login`

---

## 📡 Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| `GET` | `/health` | Return API status & timestamp | ❌ |
| `POST` | `/api/auth/register` | Create a new user account | ❌ |
| `POST` | `/api/auth/login` | Authenticate and get JWT token | ❌ |
| `POST` | `/api/upload` | Upload & scan a PDF project report | ✅ |
| `POST` | `/api/session/start` | Start a new panel session | ✅ |
| `POST` | `/api/session/answer` | Submit an answer, get scores + next question | ✅ |
| `GET` | `/api/session/report` | Get the final scorecard for a session | ✅ |

---

## 📤 POST /api/upload

Upload a PDF project report. The AI will scan it and identify weaknesses.

- **Headers**: `Content-Type: multipart/form-data`
- **Body**: `pdf` (file) — max 15MB, must be a valid PDF
- **Response**:
  ```json
  {
    "report_id": "uuid",
    "weaknesses": [
      {
        "claim": "Thin literature review",
        "page": 5,
        "issue_type": "literature_review",
        "severity": "high"
      }
    ]
  }
  ```

**Config**: `MAX_UPLOAD_SIZE` (default: 15728640 = 15MB) in `.env`

---

## 🎯 POST /api/session/start

Begin a panel examination session for a report.

- **Body**: `{ "report_id": "uuid", "difficulty": "normal" }`
- **difficulty**: `friendly` | `normal` | `brutal` (default: `normal`)
- **Response**:
  ```json
  {
    "session_id": "uuid",
    "next_turn": {
      "examiner": "hostile_external",
      "question": "What is your main contribution?",
      "cited_page": 3,
      "is_followup": false
    }
  }
  ```

---

## 💬 POST /api/session/answer

Submit your answer to the current question. Gets scores and the next examiner question.

- **Body**: `{ "session_id": "uuid", "answer_text": "Your answer here" }`
- **Response**:
  ```json
  {
    "scores": {
      "accuracy": 7,
      "evidence": 5,
      "composure": 8
    },
    "feedback": "Good structure, but cite more specific data...",
    "model_answer": "A concise defence-grade response...",
    "next_turn": {
      "examiner": "methodology_purist",
      "question": "...",
      "cited_page": 8,
      "is_followup": true
    },
    "session_complete": false
  }
  ```

- After 6 turns (`MAX_SESSION_TURNS`, configurable in `.env`), the session auto-completes.

---

## 📊 GET /api/session/report

Retrieve the final scorecard for a completed session.

- **Query params**: `?session_id=<uuid>`
- **Response**:
  ```json
  {
    "session_id": "uuid",
    "difficulty": "normal",
    "status": "completed",
    "averages": {
      "accuracy": 6,
      "evidence": 5,
      "composure": 7,
      "overall": 6
    },
    "weakness_map": [
      {"claim": "...", "page": 5, "issue_type": "writing", "severity": "low"}
    ],
    "dangerous_questions": [
      {
        "examiner": "hostile_external",
        "question": "...",
        "cited_page": 3,
        "your_answer": "...",
        "model_answer": "..."
      }
    ],
    "turns": [
      {
        "examiner": "hostile_external",
        "question": "...",
        "answer": "...",
        "accuracy": 7,
        "evidence": 5,
        "composure": 8,
        "feedback": "...",
        "model_answer": "..."
      }
    ]
  }
  ```

---

## ⚙️ Configuration (.env)

All values are read from `backend/.env` with sensible defaults:

| Variable | Default | Description |
|----------|---------|-------------|
| `MAX_UPLOAD_SIZE` | `15728640` | Max upload size in bytes (15MB) |
| `MAX_SESSION_TURNS` | `6` | Turns before session auto-completes |
| `RATE_LIMIT_AUTH` | `10,60` | Auth: 10 requests per 60 seconds |
| `RATE_LIMIT_UPLOAD` | `10,300` | Upload: 10 requests per 300 seconds |
| `RATE_LIMIT_SESSION_START` | `20,300` | Session start: 20 per 300s |
| `RATE_LIMIT_SESSION_ANSWER` | `60,60` | Answer: 60 per 60s |

---

## 🛠️ Development

### Run migrations
```bash
cd backend
php migrate.php
```
*(Uses `DATABASE_DIRECT_URL` to run against PostgreSQL directly)*

### Run tests
```bash
cd backend
vendor/bin/phpunit tests/
```

### Lint / syntax check
```bash
cd backend
php -l src/Controllers/*.php src/Services/*.php public/index.php
```

---

## 📦 Tech Stack

- **PHP 8.3** — PSR-4 autoloaded, no framework
- **PostgreSQL** — via Doctrine DBAL
- **OpenRouter** — LLM gateway (fast: Gemini Flash, strong: Gemini Pro)
- **JWT** — HS256, 7-day expiry
- **smalot/pdfparser** — PDF text extraction
- **Redis** (optional) — rate limiter backend, falls back to temp files
- **OpenAPI 3.0** — `backend/docs/openapi.yaml`

---

## 📄 OpenAPI Spec

Full machine-readable spec: `backend/docs/openapi.yaml`

Generated to describe all 12 endpoints, request/response schemas, 
security schemes, and example payloads.

---

## 🚀 Quick Start (Docker-like)

```bash
# 1. Copy env
cp backend/.env.example backend/.env
# 2. Fill in: OPENROUTER_API_KEY, JWT_SECRET, DATABASE_URL

# 3. Migrate DB
cd backend && php migrate.php

# 4. Start server
# PHP built-in:
php -S localhost:8000 -t backend/public

# 5. Register / login to get token
# 6. Upload a PDF report
# 7. Start a session and start defending!
```