# Defence Panel

> Upload your final-year project report. A panel of three AI examiners grills you out loud, using questions pulled from the weak spots in your own report, then scores every answer.

## Event

- **Hackathon:** Open//Build Hackathon (OSCA Ile-Ife x GDG OAU)
- **Build window:** 25th to 29th Oct 2026
- **Virtual demo day:** 31st Oct 2026
- **Team:** 2 people — Backend and AI (Olly), Frontend and product (teammate)

## The problem

Every final-year student dreads the project defence. Practice today means rehearsing with friends who have not read the report, or hoping the supervisor gives feedback before the real day. Nobody can practise against someone who has actually read the work and knows where it is weak.

## The solution

1. **Upload** the report (PDF).
2. **Weakness scan:** the AI reads the full report and lists its real gaps (thin literature review, unjustified sample size, no limitations section, results that do not match the objectives).
3. **Live panel session:** three examiners with different personalities ask questions that cite exact pages, interrupt weak answers, and follow up.
4. **Scorecard:** every answer is scored, with a better model answer, a weakness map, and the three most dangerous questions to prepare for.

## Why this is not just a prompt in a UI

- Questions are grounded in the student's own document, with page citations.
- A multi-agent panel with shared state and an orchestrator decides who speaks next and whether to follow up or move on.
- Scoring is checked against the report text, so feedback is specific rather than generic.
- Voice interaction makes it feel like a real defence.

## Examiner personas

| Persona | Focus |
|---|---|
| The hostile external examiner | Challenges claims, demands evidence, interrupts |
| The methodology purist | Sample size, data collection, validity, statistics |
| The "so what?" skeptic | Real-world value, novelty, limitations, future work |

## Features

### Must have
- PDF upload and text extraction with page numbers
- Weakness scan returning structured JSON
- Three personas with follow-up questions
- Per-answer scoring (accuracy, evidence, composure) with a model answer
- Final scorecard

### Should have
- Voice in and out (browser speech-to-text and text-to-speech)
- Weakness map visual
- "Your three most dangerous questions" list
- Difficulty setting (friendly or brutal)

### Stretch
- pgvector retrieval for grounding answer scoring
- Shareable scorecard link
- Real streaming of examiner replies

## Demo script (about 2 minutes)

1. A judge picks any project report (or we use a prepared one).
2. Upload: the weakness map appears in under 20 seconds.
3. The panel opens with a question that cites a specific page.
4. Speak a deliberately weak answer: an examiner interrupts with a follow-up.
5. End the session: the scorecard appears.
6. Close with real numbers, such as "tested by N students, average score moved from X to Y on a second attempt."

## Tech stack

| Layer | Choice |
|---|---|
| Frontend | React (TSX), Vite |
| Backend | PHP 8.3 on Apache, deployed to Render via Docker |
| Database | Neon (Postgres), pooled connection for the app, direct connection for schema |
| AI | OpenRouter with Gemini models (fast model for panel turns, stronger model for scan and scoring) |
| PDF parsing | smalot/pdfparser, fallback to sending the raw PDF to Gemini |
| Voice | Browser Web Speech API (Chrome) |

## AI pipeline

1. **Ingest:** extract text per page, add `[p.N]` markers, store in Neon.
2. **Weakness scan (strong model, runs once):** full report in context, returns JSON of `{claim, page, issue_type, severity}`.
3. **Panel engine (fast model, per turn):** orchestrator picks the next examiner and decides follow-up or new topic using shared state (weaknesses already probed, answers so far, running score).
4. **Answer evaluation (per answer):** scores against the report text, returns rubric scores, feedback, and a model answer.
5. **Session report:** aggregates everything into the scorecard.

All model outputs are validated against a fixed schema, with one automatic retry on failure.

## API contract (draft)

| Endpoint | Purpose |
|---|---|
| `POST /api/upload` | Upload PDF, returns `report_id` and weakness list |
| `POST /api/session/start` | Body: `report_id`, `difficulty`. Returns `session_id` and first question |
| `POST /api/session/answer` | Body: `session_id`, `answer_text`. Returns score, feedback, and next examiner turn |
| `GET /api/session/report?session_id=` | Returns the final scorecard |

Example turn shape:

```json
{
  "examiner": "methodology_purist",
  "question": "Chapter 3 says you used 40 samples. Why is that enough?",
  "cited_page": 41,
  "is_followup": false
}
```

Example answer result shape:

```json
{
  "scores": { "accuracy": 6, "evidence": 4, "composure": 7 },
  "feedback": "You did not justify the sample size.",
  "model_answer": "...",
  "next_turn": {
    "examiner": "hostile_external",
    "question": "...",
    "cited_page": 12,
    "is_followup": true
  }
}
```

## Data model (Neon)

- `reports`: id, filename, full_text, pages (jsonb), created_at
- `weaknesses`: id, report_id, claim, page, issue_type, severity
- `sessions`: id, report_id, difficulty, status, started_at, ended_at
- `turns`: id, session_id, examiner, question, cited_page, answer, accuracy, evidence, composure, feedback, model_answer

## 5-day plan

| Day | Backend and AI (Olly) | Frontend and product (teammate) |
|---|---|---|
| 25 | Docker and Render deploy, Neon schema, CORS, PDF ingestion, weakness scan | Upload flow and session UI shell against mock data |
| 26 | Panel engine: personas, orchestrator, follow-ups | Chat and voice interface |
| 27 | Answer scoring, model answers, session report | Scorecard and weakness map visuals |
| 28 | Test on 5 to 10 real reports, tune prompts | Get 10 real students to try it, collect quotes and numbers |
| 29 | Bug fixes, lock the demo path, warm-up checks | Pitch deck, demo script, backup recording |

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Render free instance cold start | Ping `/health` before demo, or use a paid instance for demo week |
| Slow AI responses | Fast model for panel turns, scan runs once with a loading state |
| Messy or scanned PDFs | Fallback to sending the raw PDF to Gemini, plus a paste-text option |
| OpenRouter rate limits | Add credit, set fallback models, cache results |
| PHP streaming issues | Return each examiner reply as one JSON response and fake typing in React |
| Neon idle wake-up delay | Run a query a few minutes before presenting |
| Demo fails live | Record a full backup run on a known-good report |

## Success metrics

- Weakness map in under 20 seconds
- First question in under 3 seconds after starting a session
- At least 10 real students tried it
- A before-and-after score improvement on a second attempt

## Open decisions

- [ ] Final project name
- [ ] Real streaming or one-shot replies
- [ ] Whether to use pgvector or rely on full-report context only
- [ ] Which Gemini models for the fast and strong tiers
