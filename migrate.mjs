import { Client } from "pg";
import { readFileSync } from "fs";
import { fileURLToPath } from "url";
import { dirname, join } from "path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const sql = readFileSync(join(__dirname, "backend/migrations/001_init.sql"), "utf8");

const client = new Client({
  connectionString: process.env.DATABASE_DIRECT_URL,
  ssl: { rejectUnauthorized: false },
});

async function main() {
  await client.connect();
  console.log("Connected to Neon (direct)...");
  await client.query(sql);
  console.log("Migration 001_init.sql applied successfully.");
  await client.end();
}

main().catch((err) => {
  console.error("Migration failed:", err.message);
  process.exit(1);
});
