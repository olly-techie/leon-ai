# Defence Panel

> Upload your final-year project report. A panel of three AI examiners grills you out loud — using questions pulled from the weak spots in your own report — then scores every answer.

Built at the **Open//Build Hackathon** (OSCA Ile-Ife x GDG OAU), 25–29 Oct 2026.

**Repo:** [github.com/olly-techie/leon-ai](https://github.com/olly-techie/leon-ai) · **Live demo:** [coming soon](#)

## The problem

Every final-year student dreads the project defence. Practice today means rehearsing with friends who have not read the report, or hoping the supervisor gives feedback before the real day. Nobody can practise against someone who has actually read the work and knows where it is weak.

## How it works

1. **Upload** your project report (PDF).
2. **Weakness scan** — the AI reads the full report and lists its real gaps: thin literature review, unjustified sample size, no limitations section, results that do not match the objectives.
3. **Live panel session** — three examiners with different personalities ask questions that cite exact pages, interrupt weak answers, and follow up.
4. **Scorecard** — every answer is scored, with a model answer, a weakness map, and the three most dangerous questions to prepare for.

### Why this is not just a prompt in a UI

- Questions are grounded in your own document, with page citations.
- A multi-agent panel with shared state and an orchestrator decides who speaks next and whether to follow up or move on.
- Scoring is checked against the report text, so feedback is specific rather than generic.
- Voice interaction makes it feel like a real defence.

### The panel

| Examiner | Focus |
|---|---|
| **The hostile external** | Challenges claims, demands evidence, interrupts |
| **The methodology purist** | Sample size, data collection, validity, statistics |
| **The "so what?" skeptic** | Real-world value, novelty, limitations, future work |

## Features

- PDF upload and text extraction with page numbers
- Weakness scan returning structured findings (claim, page, issue type, severity)
- Three examiner personas with follow-up questions
- Per-answer scoring (accuracy, evidence, composure) with model answers
- Final scorecard with weakness map and "three most dangerous questions"
- Voice in and out (browser Web Speech API)
- Difficulty setting: friendly or brutal

## Tech stack

| Layer | Choice |
|---|---|
| Frontend | React (TSX), Vite |
| Backend | PHP 8.3 on Apache (Docker, deployed to Render) |
| Database | Neon (Postgres) |
| AI | OpenRouter — Gemini models (fast tier for panel turns, strong tier for scan & scoring) |
| PDF parsing | smalot/pdfparser, fallback to raw-PDF Gemini upload |
| Voice | Browser Web Speech API (Chrome) |

## Getting started

### Prerequisites

- PHP 8.3+, Composer
- Node.js 20+, npm
- Docker (optional, for local container run)
- A Neon Postgres database
- An OpenRouter API key with Gemini access

### Backend

```bash
git clone https://github.com/olly-techie/leon-ai.git
cd leon-ai/backend
composer install
cp .env.example .env   # fill in DB + OpenRouter credentials
# run schema migration against Neon (direct connection)
php migrate.php
# local Apache/PHP dev server
php -S localhost:8000 -t public
```

### Frontend

```bash
cd frontend
npm install
npm run dev            # http://localhost:5173
```

### Environment variables

**Backend (`backend/.env`)**

```
DATABASE_URL=postgres://user:pass@host/neondb?sslmode=require   # pooled for app
DATABASE_DIRECT_URL=postgres://user:pass@host/neondb?sslmode=require  # for migrations
OPENROUTER_API_KEY=...
OPENROUTER_FAST_MODEL=google/gemini-2.5-flash
OPENROUTER_STRONG_MODEL=google/gemini-2.5-pro
CORS_ORIGIN=http://localhost:5173
```

**Frontend (`frontend/.env`)**

```
VITE_API_BASE_URL=http://localhost:8000
```

## API

All `/api/upload`, `/api/session/*` routes require `Authorization: Bearer <token>`.

| Endpoint | Auth | Purpose |
|---|---|---|
| `POST /api/auth/register` | — | Body: `email`, `password` → `token` + user |
| `POST /api/auth/login` | — | Body: `email`, `password` → `token` + user |
| `POST /api/upload` | Bearer | Upload PDF → `report_id` + weakness list |
| `POST /api/session/start` | Bearer | Body: `report_id`, `difficulty` → `session_id` + first question |
| `POST /api/session/answer` | Bearer | Body: `session_id`, `answer_text` → score, feedback, next turn |
| `GET /api/session/report?session_id=` | Bearer | Final scorecard |
| `GET /health` | — | Health check (ping before demo to warm the instance) |

## Project structure

```
.
├── backend/          # PHP 8.3 API, AI pipeline, PDF ingestion
├── frontend/         # React + Vite UI
├── project-idea.md   # Full project brief
└── README.md
```

## Demo

1. Upload any project report (or use a prepared one).
2. Watch the weakness map appear in under 20 seconds.
3. The panel opens with a question that cites a specific page.
4. Speak a deliberately weak answer — an examiner interrupts with a follow-up.
5. End the session and read the scorecard.

## Team

- **Olayiwola Abdullah** — backend, AI pipeline, multi-agent panel
- **Philip Jonathan** — frontend, product, demo

## License

MIT
