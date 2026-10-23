-- Defence Panel — Neon schema
-- Run against DATABASE_DIRECT_URL (bypasses pooled connection)

-- Reports: uploaded PDFs, full text, page-split content
CREATE TABLE IF NOT EXISTS reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    filename TEXT NOT NULL,
    full_text TEXT NOT NULL,
    pages JSONB NOT NULL DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Weaknesses: findings from the AI weakness scan
CREATE TABLE IF NOT EXISTS weaknesses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    report_id UUID NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    claim TEXT NOT NULL,
    page INT NOT NULL,
    issue_type TEXT NOT NULL,
    severity TEXT NOT NULL CHECK (severity IN ('low', 'medium', 'high', 'critical')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_weaknesses_report_id ON weaknesses(report_id);

-- Sessions: a live panel run against a report
CREATE TABLE IF NOT EXISTS sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    report_id UUID NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    difficulty TEXT NOT NULL DEFAULT 'normal' CHECK (difficulty IN ('friendly', 'normal', 'brutal')),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'completed', 'abandoned')),
    started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    ended_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_sessions_report_id ON sessions(report_id);

-- Turns: each examiner question + student answer + score
CREATE TABLE IF NOT EXISTS turns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    session_id UUID NOT NULL REFERENCES sessions(id) ON DELETE CASCADE,
    examiner TEXT NOT NULL CHECK (examiner IN ('hostile_external', 'methodology_purist', 'so_what_skeptic')),
    question TEXT NOT NULL,
    cited_page INT,
    is_followup BOOLEAN NOT NULL DEFAULT false,
    answer TEXT,
    accuracy INT CHECK (accuracy BETWEEN 0 AND 10),
    evidence INT CHECK (evidence BETWEEN 0 AND 10),
    composure INT CHECK (composure BETWEEN 0 AND 10),
    feedback TEXT,
    model_answer TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_turns_session_id ON turns(session_id);
