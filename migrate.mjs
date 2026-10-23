import { Client } from "pg";
import { readFileSync, readdirSync } from "fs";
import { fileURLToPath } from "url";
import { dirname, join } from "path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const dir = join(__dirname, "backend/migrations");
const files = readdirSync(dir).filter((f) => f.endsWith(".sql")).sort();

const client = new Client({
  connectionString: process.env.DATABASE_DIRECT_URL,
  ssl: { rejectUnauthorized: false },
});

async function main() {
  await client.connect();
  console.log("Connected to Neon (direct)...");
  for (const file of files) {
    const sql = readFileSync(join(dir, file), "utf8");
    await client.query(sql);
    console.log(`Migration ${file} applied.`);
  }
  await client.end();
}

main().catch((err) => {
  console.error("Migration failed:", err.message);
  process.exit(1);
});
